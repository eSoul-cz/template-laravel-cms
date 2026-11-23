<?php

declare(strict_types=1);

namespace App\Domain\Cms;

/** The result of the reCAPTCHA check of a submission. */
enum CaptchaVerdict
{
    /** The token was verified and scored high enough. */
    case Passed;
    /** No secret configured outside production: not verified (and warned about in the log). */
    case Skipped;
    /** A secret is configured but the submission carries no token (no JavaScript). */
    case Missing;
    /** Verification failed or the score was too low. */
    case Failed;
    /** Google could not be reached or answered with an error. */
    case Unavailable;
    /** No secret configured in production: the endpoint must not be open. */
    case Misconfigured;
}
