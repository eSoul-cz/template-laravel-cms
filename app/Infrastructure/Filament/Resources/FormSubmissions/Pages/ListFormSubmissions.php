<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\FormSubmissions\Pages;

use App\Infrastructure\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Filament\Resources\Pages\ListRecords;

final class ListFormSubmissions extends ListRecords
{
    protected static string $resource = FormSubmissionResource::class;
}
