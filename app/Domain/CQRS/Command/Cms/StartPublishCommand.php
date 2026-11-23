<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\PublishOutcome;
use App\Domain\Cms\PublishBlocked;
use App\Infrastructure\Models\User;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * The Publish button: check the content, ask the build server to build the site, record the publish.
 * Throws {@see PublishBlocked} when a publish cannot start; a build server that fails
 * leaves a failed publish, which is the outcome.
 *
 * @implements CommandInterface<PublishOutcome>
 */
final readonly class StartPublishCommand implements CommandInterface
{
    public function __construct(public User $user) {}
}
