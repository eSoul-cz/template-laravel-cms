<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * Path mode of the admin (documented exception of the astro-cms template): the backend shares the domain of the static site
 * and lives under `/admin`, so its session cookie is scoped to that path (`SESSION_PATH=/admin`).
 *
 * Livewire posts the updates of every Filament page to its own endpoint, `/livewire-<hash>/update`, which is outside `/admin`:
 * the cookie would not be sent and the panel would break. This moves the update endpoint to `/admin/livewire/update`. The script
 * and the signed preview URLs stay where they are (GET, no session needed); uploads stay too: a same-origin browser request passes
 * the forgery check without the session token and the signed URL is the authorization.
 */
final class AdminPathMode
{
    public const string UPDATE_PATH = '/admin/livewire/update';

    public static function apply(): void
    {
        Livewire::setUpdateRoute(static fn (array $handle) => Route::post(self::UPDATE_PATH, $handle));
    }
}
