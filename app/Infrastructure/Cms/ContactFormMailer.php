<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use App\Application\Contract\Cms\FormMailer;
use App\Application\Contract\Cms\FormRecipients;
use App\Application\Dto\Cms\FormMessage;
use App\Infrastructure\Mail\ContactFormMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/** Sends the submission over the configured mailer (the normal MAIL_* settings), synchronously. */
final readonly class ContactFormMailer implements FormMailer
{
    public function __construct(private FormRecipients $recipients) {}

    public function send(FormMessage $message): void
    {
        $recipients = $this->recipients->all();

        if ($recipients === []) {
            throw new RuntimeException('No form recipient is configured (CMS_FORMS_RECIPIENTS or the contact e-mail of the site settings).');
        }

        Mail::to($recipients)->send(new ContactFormMail($message));
    }
}
