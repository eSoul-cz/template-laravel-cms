<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\PublishOutcome;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * Result reported by the `post {}` block of the Jenkins pipeline.
 *
 * @implements CommandInterface<PublishOutcome>
 */
final readonly class FinishPublishCommand implements CommandInterface
{
    public function __construct(
        public int $publishId,
        public bool $success,
        public ?string $buildUrl,
        public ?string $contentHash,
        public ?string $message,
    ) {}
}
