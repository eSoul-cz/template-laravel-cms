<?php

declare(strict_types=1);

namespace App\Application\Cms;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Time of the last content change, kept in the cache. Content reaches the site only through a publish, so
 * the admin shows pending changes. An empty cache (new deploy, cleared cache) is estimated from the latest
 * `updated_at` by the publish overview query.
 */
final class ContentChanges
{
    private const string CACHE_KEY = 'cms.content_changed_at';

    public static function touch(): void
    {
        Cache::forever(self::CACHE_KEY, now()->toIso8601String());
    }

    public static function cachedAt(): ?CarbonImmutable
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_string($cached) ? CarbonImmutable::parse($cached) : null;
    }
}
