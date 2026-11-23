<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use App\Application\Contract\Cms\FormRateLimiter;
use Illuminate\Cache\RateLimiter;

/**
 * Counts the attempts of a client in the cache store (CACHE_STORE: `file` or `database`, never a directory
 * under the webroot). The key holds the address hash only.
 */
final readonly class CacheFormRateLimiter implements FormRateLimiter
{
    public function __construct(private RateLimiter $limiter) {}

    public function attempt(string $form, string $clientHash): bool
    {
        $attempts = $this->limiter->hit(
            "forms:{$form}:{$clientHash}",
            config()->integer('cms.forms.rate_limit.decay_seconds'),
        );

        return $attempts <= config()->integer('cms.forms.rate_limit.attempts');
    }
}
