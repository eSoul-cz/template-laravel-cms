<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Cms;

use App\Domain\Cms\PublishStatus;
use App\Infrastructure\Models\Publish;
use LogicException;

/** Which publish is in progress and which one last reached the site; shared by the publish queries and commands. */
final readonly class CurrentPublish
{
    /**
     * The publish in progress. The Jenkins callback is best effort, so a publish without a result for longer
     * than `cms.jenkins.timeout_minutes` is marked failed when read; otherwise the Publish button would stay
     * blocked forever.
     */
    public function get(): ?Publish
    {
        Publish::query()
            ->whereIn('status', [PublishStatus::Queued, PublishStatus::Running])
            ->where('started_at', '<', now()->subMinutes($this->timeoutMinutes()))
            ->update([
                'status' => PublishStatus::Failed,
                'message' => 'Timed out: Jenkins reported no result in time.',
                'finished_at' => now(),
            ]);

        return Publish::query()
            ->whereIn('status', [PublishStatus::Queued, PublishStatus::Running])
            ->latest('id')
            ->first();
    }

    public function lastSuccessful(): ?Publish
    {
        return Publish::query()->where('status', PublishStatus::Success)->latest('started_at')->first();
    }

    /**
     * An invalid limit (0, "abc") would end every new publish at once and allow overlapping ones: a
     * configuration error is better than a silent default.
     */
    private function timeoutMinutes(): int
    {
        $minutes = filter_var(config('cms.jenkins.timeout_minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($minutes === false) {
            throw new LogicException('CMS_PUBLISH_TIMEOUT must be a positive integer (minutes).');
        }

        return $minutes;
    }
}
