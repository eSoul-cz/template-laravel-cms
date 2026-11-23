<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

use App\Domain\Cms\PublishStatus;

/** The state a publish was left in by a command. */
final readonly class PublishOutcome
{
    public function __construct(
        public int $id,
        public PublishStatus $status,
        public ?string $message,
        public ?string $queueUrl,
    ) {}
}
