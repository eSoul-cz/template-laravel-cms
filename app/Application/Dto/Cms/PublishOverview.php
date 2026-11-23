<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

use Carbon\CarbonInterface;

/** What the Publish page shows: the running publish, the last successful one and whether content waits for a publish. */
final readonly class PublishOverview
{
    public function __construct(
        public ?int $currentId,
        public ?CarbonInterface $currentStartedAt,
        public ?CarbonInterface $lastPublishedAt,
        public ?string $lastPublishedBy,
        public bool $hasUnpublishedChanges,
        /** @var list<string> Why the content cannot be published now; empty when it can, or when a publish is running. */
        public array $problems,
    ) {}
}
