<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Contract\Cms\BuildTrigger;
use App\Application\Dto\Cms\PublishOutcome;
use App\Domain\Cms\PublishStatus;
use App\Infrastructure\Models\Publish;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use LogicException;

#[HandlesCommand(RefreshPublishCommand::class)]
final readonly class RefreshPublishCommandHandler implements CommandHandlerInterface
{
    public function __construct(private BuildTrigger $builds) {}

    public function handle(CommandInterface $command): PublishOutcome
    {
        if ( ! $command instanceof RefreshPublishCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        $publish = Publish::query()->findOrFail($command->publishId);

        if ( ! $publish->status->isFinished() && $publish->jenkins_queue_url !== null) {
            $status = $this->builds->buildStatus($publish->jenkins_queue_url, $publish->jenkins_build_url);
            $state = PublishStatus::from($status['state']);

            $publish->update([
                'status' => $state,
                'jenkins_build_url' => $status['build_url'] ?? $publish->jenkins_build_url,
                'finished_at' => $state->isFinished() ? now() : null,
            ]);
        }

        return new PublishOutcome($publish->id, $publish->status, $publish->message, $publish->jenkins_queue_url);
    }
}
