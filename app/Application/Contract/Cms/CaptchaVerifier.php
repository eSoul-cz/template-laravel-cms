<?php

declare(strict_types=1);

namespace App\Application\Contract\Cms;

use App\Application\Dto\Cms\CaptchaResult;

/** Checks the reCAPTCHA token of a submission. */
interface CaptchaVerifier
{
    /** @param  string  $action  the reCAPTCHA action the page asked for (the form name) */
    public function verify(?string $token, string $action): CaptchaResult;
}
