<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\FormSubmissions;

use App\Domain\Cms\FormMailStatus;
use App\Infrastructure\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Infrastructure\Filament\Resources\FormSubmissions\Pages\ViewFormSubmission;
use App\Infrastructure\Models\FormSubmission;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Messages from the forms of the website (POST /forms/contact). Read-only: a submission is created by a visitor,
 * never edited; deleting is allowed so the retention period of the project can be kept (docs/forms.md).
 */
final class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'form submission';

    protected static ?string $pluralModelLabel = 'Form submissions';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Submission')->columns(2)->schema([
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('form'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('mail_status')
                    ->label('E-mail')
                    ->badge()
                    ->formatStateUsing(static fn (FormMailStatus $state): string => $state->label())
                    ->color(static fn (FormMailStatus $state): string => $state->color()),
                TextEntry::make('recaptcha_score')->label('reCAPTCHA score')->placeholder('not verified'),
                TextEntry::make('message')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('mail_status')
                    ->label('E-mail')
                    ->badge()
                    ->formatStateUsing(static fn (FormMailStatus $state): string => $state->label())
                    ->color(static fn (FormMailStatus $state): string => $state->color()),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListFormSubmissions::route('/'),
            'view' => ViewFormSubmission::route('/{record}'),
        ];
    }
}
