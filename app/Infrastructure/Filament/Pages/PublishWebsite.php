<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Pages;

use App\Application\Dto\Cms\PublishOverview;
use App\Domain\Cms\PublishBlocked;
use App\Domain\Cms\PublishStatus;
use App\Domain\CQRS\Command\Cms\MarkPublishFailedCommand;
use App\Domain\CQRS\Command\Cms\RefreshPublishCommand;
use App\Domain\CQRS\Command\Cms\StartPublishCommand;
use App\Domain\CQRS\Query\Cms\GetPublishOverviewQuery;
use App\Infrastructure\Models\Publish;
use App\Infrastructure\Models\User;
use BackedEnum;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Throwable;

/**
 * "Publish": changes from the admin reach the public site only through a new build of the static site.
 */
final class PublishWebsite extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Publish';

    protected static ?string $title = 'Publish';

    public static function getNavigationBadge(): ?string
    {
        return self::overview()->hasUnpublishedChanges ? '!' : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'publish';
    }

    public function content(Schema $schema): Schema
    {
        $overview = self::overview(withProblems: true);

        return $schema->components([
            Section::make('Status')
                ->description('Builds the static site from the content saved here and deploys it. Until then visitors see the previous version.')
                ->schema([
                    Text::make($this->statusLine($overview))->weight('bold'),
                    Text::make($overview->hasUnpublishedChanges ? 'There are unpublished changes.' : 'The site is up to date.')
                        ->color($overview->hasUnpublishedChanges ? 'warning' : 'success'),
                    Text::make('The content cannot be published yet:')->color('danger')->visible($overview->problems !== []),
                    ...array_map(static fn (string $problem): Text => Text::make("• {$problem}")->color('danger'), $overview->problems),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('History')
            ->query(Publish::query()->with('user')->latest('id'))
            ->poll('10s')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static fn (PublishStatus $state): string => $state->label())
                    ->color(static fn (PublishStatus $state): string => $state->color()),
                TextColumn::make('user.name')->label('User')->placeholder('—'),
                TextColumn::make('started_at')->dateTime('Y-m-d H:i'),
                TextColumn::make('finished_at')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextColumn::make('jenkins_build_url')
                    ->label('Build')
                    ->formatStateUsing(static fn (): string => 'Jenkins')
                    ->url(static fn (Publish $record): ?string => $record->jenkins_build_url, shouldOpenInNewTab: true)
                    ->placeholder('—'),
                TextColumn::make('message')->limit(80)->placeholder('—'),
            ])
            ->paginated([10, 25]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStatus')
                ->label('Refresh status')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(static fn (): bool => self::overview()->currentId !== null)
                ->action(static function (): void {
                    $currentId = self::overview()->currentId;

                    if ($currentId === null) {
                        return;
                    }

                    try {
                        CommandBus::dispatch(new RefreshPublishCommand($currentId));
                    } catch (Throwable $exception) {
                        // The technical text goes to the log only; the admin gets a readable message.
                        report($exception);
                        Notification::make()->danger()->title('Jenkins status could not be read')->body('See the application log for details.')->persistent()->send();
                    }
                }),
            Action::make('markFailed')
                ->label('Mark as failed')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Use this when the build is stuck. A late result from Jenkins is still recorded.')
                ->visible(static fn (): bool => self::overview()->currentId !== null)
                ->action(static function (): void {
                    $currentId = self::overview()->currentId;
                    $user = auth()->user();

                    if ($currentId !== null && $user instanceof User) {
                        CommandBus::dispatch(new MarkPublishFailedCommand($currentId, $user));
                        Notification::make()->success()->title('The publish was marked as failed')->send();
                    }
                }),
            Action::make('publish')
                ->label('Publish')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->requiresConfirmation()
                ->modalHeading('Publish the site?')
                ->modalDescription('The static site is rebuilt from the saved content and deployed. This takes a few minutes.')
                ->disabled(static fn (): bool => self::overview()->currentId !== null)
                ->action(static function (): void {
                    $user = auth()->user();

                    if ( ! $user instanceof User) {
                        return;
                    }

                    try {
                        $outcome = CommandBus::dispatch(new StartPublishCommand($user));
                    } catch (PublishBlocked $blocked) {
                        Notification::make()->danger()->title('The site cannot be published')->body(implode("\n", $blocked->problems))->persistent()->send();

                        return;
                    }

                    $outcome->status === PublishStatus::Failed
                        ? Notification::make()->danger()->title('Jenkins could not be started')->body($outcome->message)->send()
                        : Notification::make()->success()->title('The publish was started')->send();
                }),
        ];
    }

    private static function overview(bool $withProblems = false): PublishOverview
    {
        return QueryBus::execute(new GetPublishOverviewQuery($withProblems));
    }

    private function statusLine(PublishOverview $overview): string
    {
        if ($overview->currentId !== null) {
            return 'A publish is running since ' . $overview->currentStartedAt?->format('Y-m-d H:i') . '.';
        }

        return $overview->lastPublishedAt === null
            ? 'The site has not been published yet.'
            : 'Last published ' . $overview->lastPublishedAt->format('Y-m-d H:i') . ' by ' . ($overview->lastPublishedBy ?? '—') . '.';
    }
}
