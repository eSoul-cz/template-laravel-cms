<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\SiteSettings\Pages;

use App\Infrastructure\Filament\Resources\SiteSettings\SiteSettingResource;
use Filament\Resources\Pages\EditRecord;

final class EditSiteSetting extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;
}
