<?php

declare(strict_types=1);

namespace App\Application\Contract\Cms;

/** Limits how often one client may submit a form. The client is known only by its hash. */
interface FormRateLimiter
{
    /** Counts an attempt; false when the client is over the limit (the attempt is then not counted). */
    public function attempt(string $form, string $clientHash): bool;
}
