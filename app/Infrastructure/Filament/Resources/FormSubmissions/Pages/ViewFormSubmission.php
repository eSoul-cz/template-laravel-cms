<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\FormSubmissions\Pages;

use App\Infrastructure\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
