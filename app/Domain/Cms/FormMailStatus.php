<?php

declare(strict_types=1);

namespace App\Domain\Cms;

/** Whether the notification e-mail of a stored form submission left the application. */
enum FormMailStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
