<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\Pages\Pages;

use App\Infrastructure\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;
}
