<?php

declare(strict_types=1);

namespace App\Application\Contract\Cms;

use App\Application\Dto\Cms\FormMessage;
use Throwable;

/** Keeps a submission in a file outside the webroot when neither the database nor the e-mail took it. */
interface FormLastResort
{
    /** @throws Throwable when the file could not be written */
    public function append(FormMessage $message, string $clientHash, ?float $recaptchaScore): void;
}
