<?php

declare(strict_types=1);

namespace App\Domain\Cms;

/**
 * How a form submission ended. `code` is what the frontend sees (`?form=<code>` and the JSON `code`); the
 * human messages are all here, in English, so one place holds the wording.
 */
enum FormOutcome: string
{
    case Sent = 'sent';
    case Invalid = 'invalid';
    case OriginForbidden = 'origin_forbidden';
    case RateLimited = 'rate_limited';
    case CaptchaMissing = 'captcha_missing';
    case CaptchaFailed = 'captcha_failed';
    case CaptchaUnavailable = 'captcha_unavailable';
    /** The server is not set up to accept forms safely (no reCAPTCHA secret in production). */
    case Unavailable = 'unavailable';
    /** Neither the database, nor the e-mail, nor the last-resort file took the submission. */
    case Failed = 'failed';

    public function isSent(): bool
    {
        return $this === self::Sent;
    }

    /** @param  string|null  $contact  contact e-mail of the site, offered as the alternative to the form */
    public function message(?string $contact = null): string
    {
        $alternative = $contact === null ? '' : " or contact us at {$contact}";

        return match ($this) {
            self::Sent => 'Thank you, your message has been sent.',
            self::Invalid => 'Please check the form and try again.',
            self::OriginForbidden => 'This website is not allowed to send messages.',
            self::RateLimited => "Too many messages from your address. Please try again later{$alternative}.",
            self::CaptchaMissing => "Enable JavaScript{$alternative} to send this form.",
            self::CaptchaFailed => "We could not verify that you are human. Please try again{$alternative}.",
            self::CaptchaUnavailable => "The spam check is not available right now. Please try again later{$alternative}.",
            self::Unavailable => "The form is temporarily unavailable. Please try again later{$alternative}.",
            self::Failed => "Your message could not be saved. Please try again later{$alternative}.",
        };
    }
}
