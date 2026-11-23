<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\PublishStatus;
use App\Infrastructure\Models\Publish;
use Illuminate\Console\Command;

/**
 * Cleans /_cms/ (disk `cms-public`). Every export refreshes the mtime of the files it references, so a file older
 * than the start of a successful publish was not used by any later build. The anchor is the last successful
 * publish older than `--days`: files that dropped out of the site by then are deleted. A page opened before a
 * deploy keeps its images for at least `--days` days, and the images of the deployed site are never deleted (the
 * site was built from an export made after that publish). Scheduled daily by CmsServiceProvider.
 */
final class PrunePublicMedia extends Command
{
    protected $signature = 'cms:prune-public-media {--days=7 : How long to keep files the site no longer references}';

    protected $description = 'Delete site media (/_cms/) that no recent build references.';

    public function handle(PublicMediaStore $media): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::FAILURE;
        }

        $anchor = Publish::query()
            ->where('status', PublishStatus::Success)
            ->where('started_at', '<=', now()->subDays($days))
            ->latest('started_at')
            ->first();

        if ($anchor?->started_at === null) {
            $this->info("No successful publish older than {$days} days, nothing to prune.");

            return self::SUCCESS;
        }

        $deleted = $media->prune($anchor->started_at);
        $this->info("Deleted {$deleted} file(s) unused since {$anchor->started_at->toIso8601String()}.");

        return self::SUCCESS;
    }
}
