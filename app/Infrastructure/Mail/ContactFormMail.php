<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Dto\Cms\FormMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The notification of a form submission; replying answers the visitor. Sent synchronously, not queued. */
final class ContactFormMail extends Mailable
{
    public function __construct(public readonly FormMessage $submission) {}

    public function envelope(): Envelope
    {
        // A name with a line break must not become a second header.
        $name = trim((string) preg_replace('/\s+/', ' ', $this->submission->name));

        return new Envelope(
            subject: "Website form ({$this->submission->form}): {$name}",
            replyTo: [new Address($this->submission->email, $name)],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-form');
    }
}
