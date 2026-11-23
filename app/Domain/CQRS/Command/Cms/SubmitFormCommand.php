<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Domain\Cms\FormOutcome;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * A visitor submitted a form of the static site. The handler runs the pipeline honeypot, rate limit,
 * reCAPTCHA, delivery, last resort and returns how it ended; it throws nothing for a rejected submission.
 *
 * @implements CommandInterface<FormOutcome>
 */
final readonly class SubmitFormCommand implements CommandInterface
{
    /**
     * @param  string  $clientHash  HMAC of the client address; the address itself never reaches the domain
     */
    public function __construct(
        public string $form,
        public string $name,
        public string $email,
        public string $message,
        public ?string $honeypot,
        public ?string $recaptchaToken,
        public string $clientHash,
    ) {}
}
