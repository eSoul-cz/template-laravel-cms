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

#[HandlesCommand(MarkPublishFailedCommand::class)]
final readonly class MarkPublishFailedCommandHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): PublishOutcome
    {
        if ( ! $command instanceof MarkPublishFailedCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        $publish = Publish::query()->findOrFail($command->publishId);

        if ( ! $publish->status->isFinished()) {
            $publish->update([
                'status' => PublishStatus::Failed,
                'message' => "Marked as failed by {$command->user->name}.",
                'finished_at' => now(),
            ]);
        }

        return new PublishOutcome($publish->id, $publish->status, $publish->message, $publish->jenkins_queue_url);
    }
}
