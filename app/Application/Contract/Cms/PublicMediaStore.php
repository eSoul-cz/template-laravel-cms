<?php

declare(strict_types=1);

namespace App\Application\Contract\Cms;

use Carbon\CarbonInterface;

/**
 * Site media on the `cms-public` disk (web server /_cms/, docs/contract.md). The name is the first 20
 * characters of the content sha256 plus an extension from the type, so a file never changes: an edited
 * image gets a new name and reaches the site only after a publish.
 */
interface PublicMediaStore
{
    /** Private uploads of the admin. */
    public const string MEDIA_DISK = 'cms-media';

    /** Public content-addressed copy. */
    public const string DISK = 'cms-public';

    public const string URL_PREFIX = '/_cms/';

    /** File name on the site, or null for a type the site must not serve. */
    public function name(string $sha256, ?string $mime): ?string;

    /**
     * Makes sure all files of the export are on the site and touches each one (the use time drives
     * {@see self::prune()}).
     *
     * @param  array<string, array{path: string, sha256: string}>  $files  name => source on the media disk
     */
    public function store(array $files): void;

    /**
     * Deletes files that no export referenced since `$unusedSince`.
     *
     * @return int number of deleted files
     */
    public function prune(CarbonInterface $unusedSince): int;
}
