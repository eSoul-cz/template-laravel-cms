<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\PublishOutcome;
use App\Infrastructure\Models\User;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * Manual end of a stuck publish; who ended it is recorded in `message`. A publish that is already finished
 * is left as it is.
 *
 * @implements CommandInterface<PublishOutcome>
 */
final readonly class MarkPublishFailedCommand implements CommandInterface
{
    public function __construct(
        public int $publishId,
        public User $user,
    ) {}
}
