<?php

declare(strict_types=1);

namespace App\Application\Contract\Cms;

use App\Application\Dto\Cms\FormMessage;
use Throwable;

/** Sends the notification e-mail of a form submission, synchronously. */
interface FormMailer
{
    /** @throws Throwable when there is no recipient or the mail could not be sent */
    public function send(FormMessage $message): void;
}
