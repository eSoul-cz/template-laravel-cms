<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\PublishOutcome;
use App\Domain\Cms\PublishStatus;
use App\Infrastructure\Models\Publish;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use LogicException;

#[HandlesCommand(FinishPublishCommand::class)]
final readonly class FinishPublishCommandHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): PublishOutcome
    {
        if ( ! $command instanceof FinishPublishCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        $publish = Publish::query()->findOrFail($command->publishId);

        // A late result of a publish already ended by the timeout or by an admin: the real result is recorded
        // with a note. Builds do not overlap (Jenkinsfile `disableConcurrentBuilds()`).
        $late = $publish->status->isFinished();

        $publish->update([
            'status' => $command->success ? PublishStatus::Success : PublishStatus::Failed,
            'jenkins_build_url' => $command->buildUrl ?? $publish->jenkins_build_url,
            'content_hash' => $command->contentHash ?? $publish->content_hash,
            'message' => $late ? trim('Result arrived after the publish had been ended. ' . $command->message) : $command->message,
            'finished_at' => now(),
        ]);

        return new PublishOutcome($publish->id, $publish->status, $publish->message, $publish->jenkins_queue_url);
    }
}
