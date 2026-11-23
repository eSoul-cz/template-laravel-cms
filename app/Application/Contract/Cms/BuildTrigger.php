<?php

declare(strict_types=1);

namespace App\Application\Contract\Cms;

/** Starts and watches the build that turns the saved content into the static site. */
interface BuildTrigger
{
    public function isConfigured(): bool;

    /**
     * @param  array<string, string>  $parameters
     * @return string URL of the queued build
     */
    public function triggerBuild(array $parameters): string;

    /**
     * Poll fallback for when the callback of the build did not arrive: queue item first, then the build.
     *
     * @return array{state: 'queued'|'running'|'success'|'failed', build_url: string|null}
     */
    public function buildStatus(string $queueUrl, ?string $buildUrl): array;
}
