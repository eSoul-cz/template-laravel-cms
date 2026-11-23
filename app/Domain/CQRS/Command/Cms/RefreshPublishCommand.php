<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\PublishOutcome;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * Pulls the state of a publish from Jenkins when the callback did not arrive. A Jenkins error (e.g. 404
 * after the queue item was dropped) is thrown so the admin sees it; the publish keeps its state.
 *
 * @implements CommandInterface<PublishOutcome>
 */
final readonly class RefreshPublishCommand implements CommandInterface
{
    public function __construct(public int $publishId) {}
}
