<?php

declare(strict_types=1);

use App\Infrastructure\Http\Controllers\ContactFormController;
use Illuminate\Support\Facades\Route;

// Prefix /forms and the origin check come from CmsServiceProvider. Called by the browsers of the visitors of
// the static site: a plain cross-origin form post, so no CSRF token, no session and no cookies (none of the
// `web` middleware runs; docs/forms.md).
Route::post('contact', ContactFormController::class)->name('forms.contact');
