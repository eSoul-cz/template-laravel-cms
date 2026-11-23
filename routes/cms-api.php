<?php

declare(strict_types=1);

use App\Infrastructure\Http\Controllers\ExportController;
use App\Infrastructure\Http\Controllers\PublishCallbackController;
use App\Infrastructure\Http\Middleware\AuthenticateCmsToken;
use Illuminate\Support\Facades\Route;

// Prefix /cms-api and the IP allowlist come from CmsServiceProvider. Called by the build of the static site
// (Jenkins), never by a browser: no session, no CSRF.
Route::get('v1/export', ExportController::class)
    ->middleware(AuthenticateCmsToken::class . ':export_token')
    ->name('cms-api.export');

// After the build, Jenkins reports the result of a publish started from the admin.
Route::post('v1/publishes/{publish}/status', PublishCallbackController::class)
    ->middleware(AuthenticateCmsToken::class . ':jenkins.callback_token')
    ->name('cms-api.publishes.status');
