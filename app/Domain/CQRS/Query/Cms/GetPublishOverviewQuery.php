<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PublishOverview;
use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * The state of the Publish page: the publish in progress, the last successful one, whether content changed
 * after it, and, when asked for and while nothing is running, what blocks a publish (building the export
 * reads every media file, so the cheap callers leave it out).
 *
 * @implements QueryInterface<PublishOverview>
 */
final readonly class GetPublishOverviewQuery implements QueryInterface
{
    public function __construct(public bool $withProblems = false) {}
}
