<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\SiteSettings;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Infrastructure\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Infrastructure\Filament\Resources\SiteSettings\Pages\ListSiteSettings;
use App\Infrastructure\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Site-wide settings: one record, so the list page redirects to its edit form. */
final class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'site settings';

    protected static ?string $pluralModelLabel = 'Site settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('site_name')->required()->maxLength(255),
            Textarea::make('description')->rows(2)->maxLength(300)->helperText('Default meta description.'),
            TextInput::make('contact_email')->email()->maxLength(255),
            Textarea::make('footer_text')->rows(2)->maxLength(500),
            FileUpload::make('og_image')
                ->label('Social image')
                ->helperText('Open Graph image used when sharing the site (1200 x 630 px recommended).')
                ->disk(PublicMediaStore::MEDIA_DISK)
                ->directory('images')
                ->visibility('public')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/avif'])
                ->maxSize(10240),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListSiteSettings::route('/'),
            'edit' => EditSiteSetting::route('/{record}/edit'),
        ];
    }
}
