<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Cms\ContentChanges;
use App\Application\Dto\Cms\PublishOverview;
use App\Domain\CQRS\Cms\CurrentPublish;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryBusInterface;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use LogicException;

#[HandlesQuery(GetPublishOverviewQuery::class)]
final readonly class GetPublishOverviewQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CurrentPublish $publishes,
        private QueryBusInterface $queryBus,
    ) {}

    public function handle(QueryInterface $query): PublishOverview
    {
        if ( ! $query instanceof GetPublishOverviewQuery) {
            throw new LogicException('Unsupported query: ' . $query::class);
        }

        $current = $this->publishes->get();
        $last = $this->publishes->lastSuccessful();
        $changedAt = $this->lastChangedAt();
        $lastStartedAt = $last?->started_at;

        return new PublishOverview(
            currentId: $current?->id,
            currentStartedAt: $current?->started_at,
            lastPublishedAt: $last === null ? null : ($last->finished_at ?? $last->started_at),
            lastPublishedBy: $last?->user?->name,
            // Content changed after the last successful build started (the build reads the export at its start).
            hasUnpublishedChanges: $changedAt !== null && ($lastStartedAt === null || $changedAt->greaterThan($lastStartedAt)),
            problems: $query->withProblems && $current === null ? $this->queryBus->execute(new ExportContentQuery())->problems : [],
        );
    }

    /** Empty cache (new deploy, cleared cache): estimated from the latest `updated_at`. */
    private function lastChangedAt(): ?CarbonImmutable
    {
        $cached = ContentChanges::cachedAt();

        if ($cached instanceof CarbonImmutable) {
            return $cached;
        }

        $latest = collect([Page::query()->max('updated_at'), SiteSetting::query()->max('updated_at')])
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->map(static fn (string $value): CarbonImmutable => CarbonImmutable::parse($value))
            ->max();

        return $latest instanceof CarbonImmutable ? $latest : null;
    }
}
