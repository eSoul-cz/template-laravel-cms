<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\SiteSettings\Pages;

use App\Infrastructure\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Infrastructure\Models\SiteSetting;
use Filament\Resources\Pages\ListRecords;

final class ListSiteSettings extends ListRecords
{
    protected static string $resource = SiteSettingResource::class;

    public function mount(): void
    {
        parent::mount();

        // There is exactly one settings record.
        $this->redirect(SiteSettingResource::getUrl('edit', ['record' => SiteSetting::current()]));
    }
}
