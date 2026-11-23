<?php

declare(strict_types=1);

namespace App\Domain\Cms;

enum PublishStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued, self::Running => 'info',
            self::Success => 'success',
            self::Failed => 'danger',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Success || $this === self::Failed;
    }
}
