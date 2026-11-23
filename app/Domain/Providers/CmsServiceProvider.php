<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use App\Application\Contract\Cms\BuildTrigger;
use App\Application\Contract\Cms\CaptchaVerifier;
use App\Application\Contract\Cms\FormLastResort;
use App\Application\Contract\Cms\FormMailer;
use App\Application\Contract\Cms\FormRateLimiter;
use App\Application\Contract\Cms\FormRecipients;
use App\Application\Contract\Cms\PublicMediaStore;
use App\Infrastructure\Cms\AdminPathMode;
use App\Infrastructure\Cms\CacheFormRateLimiter;
use App\Infrastructure\Cms\ConfiguredFormRecipients;
use App\Infrastructure\Cms\ContactFormMailer;
use App\Infrastructure\Cms\JenkinsClient;
use App\Infrastructure\Cms\LastResortFile;
use App\Infrastructure\Cms\PublicMedia;
use App\Infrastructure\Cms\RecaptchaVerifier;
use App\Infrastructure\Console\Commands\PrunePublicMedia;
use App\Infrastructure\Http\Middleware\AcceptJson;
use App\Infrastructure\Http\Middleware\RequireAllowedFormOrigin;
use App\Infrastructure\Http\Middleware\RestrictToAllowedIps;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Wires the CMS: the contracts of App\Application\Contract\Cms, media disks, the /cms-api and /forms routes, the prune command and its schedule. */
final class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PublicMediaStore::class, PublicMedia::class);
        $this->app->singleton(BuildTrigger::class, JenkinsClient::class);
        $this->app->singleton(FormRateLimiter::class, CacheFormRateLimiter::class);
        $this->app->singleton(CaptchaVerifier::class, RecaptchaVerifier::class);
        $this->app->singleton(FormRecipients::class, ConfiguredFormRecipients::class);
        $this->app->singleton(FormMailer::class, ContactFormMailer::class);
        $this->app->singleton(FormLastResort::class, LastResortFile::class);

        // Disks are added here so that config/filesystems.php of the Laravel skeleton stays untouched.
        $disks = config()->array('filesystems.disks');
        $disks['cms-media'] ??= ['driver' => 'local', 'root' => config('cms.media_root'), 'url' => '/cms-media', 'visibility' => 'public', 'throw' => true];
        $disks['cms-public'] ??= ['driver' => 'local', 'root' => config('cms.public_media_root'), 'url' => '/_cms', 'visibility' => 'public', 'throw' => true];
        config()->set('filesystems.disks', $disks);
    }

    public function boot(): void
    {
        // The allowlist goes first: it rejects a foreign address before model binding or the token.
        Route::middleware([RestrictToAllowedIps::class, AcceptJson::class, SubstituteBindings::class])
            ->prefix('cms-api')
            ->group(base_path('routes/cms-api.php'));

        // The public form endpoint for the browsers of the visitors. Deliberately not in the `web` group: a plain
        // cross-origin post has no CSRF token and the endpoint needs no cookie or session. What protects it is the
        // origin check, the honeypot, the rate limit and reCAPTCHA (docs/forms.md).
        Route::middleware([RequireAllowedFormOrigin::class])
            ->prefix('forms')
            ->group(base_path('routes/forms.php'));

        // Backend under /admin of the site's domain: the Livewire update endpoint moves under /admin (see AdminPathMode).
        if (config()->boolean('cms.admin_path_mode')) {
            AdminPathMode::apply();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PrunePublicMedia::class]);
        }

        // Site media that no build of the last 7 days referenced (see PrunePublicMedia).
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command(PrunePublicMedia::class)->dailyAt('03:30');
        });
    }
}
