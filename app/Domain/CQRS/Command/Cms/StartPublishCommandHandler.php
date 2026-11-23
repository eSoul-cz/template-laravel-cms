<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Contract\Cms\BuildTrigger;
use App\Application\Dto\Cms\PublishOutcome;
use App\Domain\Cms\PublishBlocked;
use App\Domain\Cms\PublishStatus;
use App\Domain\CQRS\Cms\CurrentPublish;
use App\Domain\CQRS\Query\Cms\ExportContentQuery;
use App\Infrastructure\Models\Publish;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Esoul\Cqrs\Contracts\QueryBusInterface;
use LogicException;
use Throwable;

#[HandlesCommand(StartPublishCommand::class)]
final readonly class StartPublishCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CurrentPublish $publishes,
        private BuildTrigger $builds,
        private QueryBusInterface $queryBus,
    ) {}

    /** @throws PublishBlocked */
    public function handle(CommandInterface $command): PublishOutcome
    {
        if ( ! $command instanceof StartPublishCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        if ($this->publishes->get() instanceof Publish) {
            throw new PublishBlocked(['A publish is already queued or running.']);
        }

        if ( ! $this->builds->isConfigured()) {
            throw new PublishBlocked(['Jenkins is not configured (CMS_JENKINS_JOB_URL, CMS_JENKINS_USER, CMS_JENKINS_API_TOKEN).']);
        }

        $problems = $this->queryBus->execute(new ExportContentQuery())->problems;

        if ($problems !== []) {
            throw new PublishBlocked($problems);
        }

        $publish = Publish::query()->create([
            'user_id' => $command->user->id,
            'status' => PublishStatus::Queued,
            'started_at' => now(),
        ]);

        try {
            $publish->update(['jenkins_queue_url' => $this->builds->triggerBuild(['CMS_PUBLISH_ID' => (string) $publish->id])]);
        } catch (Throwable $exception) {
            report($exception);

            $publish->update([
                'status' => PublishStatus::Failed,
                'message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return new PublishOutcome($publish->id, $publish->status, $publish->message, $publish->jenkins_queue_url);
    }
}
