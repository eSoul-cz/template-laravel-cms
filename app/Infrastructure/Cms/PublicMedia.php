<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Site media on the `cms-public` disk (web server /_cms/, docs/contract.md). The name is the first 20 characters
 * of the content sha256 plus an extension from the type, so a file never changes: an edited image gets a new
 * name and reaches the site only after a publish (the HTML references it only after the next build).
 *
 * The export writes the files before the build links them and touches every file it references (mtime): the
 * prune command uses that to find files the site no longer uses.
 */
final class PublicMedia implements PublicMediaStore
{
    /** Same allow-list as the frontend (frontend/src/lib/cms/media.ts): images only, nothing executable. */
    private const array EXTENSIONS = [
        'image/webp' => 'webp',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/avif' => 'avif',
    ];

    /** File name on the site, or null for a type the site must not serve. */
    public function name(string $sha256, ?string $mime): ?string
    {
        $extension = self::EXTENSIONS[strtolower((string) $mime)] ?? null;

        return $extension === null || strlen($sha256) !== 64 ? null : substr($sha256, 0, 20) . '.' . $extension;
    }

    /**
     * Makes sure all files of the export are on the site. Missing ones are copied through a temporary file and
     * a rename, so the web server never sees half a file; existing ones get a fresh mtime.
     *
     * @param  array<string, array{path: string, sha256: string}>  $files  name => source on the media disk
     *
     * @throws RuntimeException when a file cannot be written or changed while copying
     */
    public function store(array $files): void
    {
        $disk = Storage::disk(self::DISK);

        foreach ($files as $name => $file) {
            $target = $disk->path($name);

            if ( ! is_file($target)) {
                $temporary = $disk->path('.' . $name . '.' . bin2hex(random_bytes(4)) . '.tmp');

                // The source may change between the hash and the copy: it must not appear under the name of other content.
                if ( ! @copy($file['path'], $temporary) || hash_file('sha256', $temporary) !== $file['sha256']) {
                    @unlink($temporary);

                    throw new RuntimeException("Cannot publish {$file['path']} as {$name}.");
                }

                @chmod($temporary, 0644);

                if ( ! @rename($temporary, $target)) {
                    @unlink($temporary);

                    throw new RuntimeException("Cannot write {$name} to " . $disk->path('') . '.');
                }
            }

            if ( ! @touch($target)) {
                throw new RuntimeException("Cannot update the use time of {$name}.");
            }
        }
    }

    /**
     * Deletes files that no export referenced since `$unusedSince`. Unfinished copies (`.…tmp`) older than a
     * day too.
     *
     * @return int number of deleted files
     */
    public function prune(CarbonInterface $unusedSince): int
    {
        $disk = Storage::disk(self::DISK);
        $deleted = 0;

        foreach ($disk->files() as $name) {
            $limit = str_starts_with($name, '.') ? now()->subDay() : $unusedSince;
            $modified = @filemtime($disk->path($name));

            if ($modified !== false && $modified < $limit->getTimestamp() && @unlink($disk->path($name))) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
