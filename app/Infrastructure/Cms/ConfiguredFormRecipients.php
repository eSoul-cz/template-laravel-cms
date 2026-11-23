<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use App\Application\Contract\Cms\FormRecipients;
use App\Infrastructure\Models\SiteSetting;
use Illuminate\Database\QueryException;

/** Recipients from `cms.forms.recipients`, the contact e-mail of the site settings when none is configured. */
final readonly class ConfiguredFormRecipients implements FormRecipients
{
    public function all(): array
    {
        $recipients = $this->configured();

        if ($recipients !== []) {
            return $recipients;
        }

        $contact = $this->siteContact();

        return $contact === null ? [] : [$contact];
    }

    public function contact(): ?string
    {
        return $this->siteContact() ?? $this->configured()[0] ?? null;
    }

    /** @return list<string> */
    private function configured(): array
    {
        $recipients = [];

        foreach (config()->array('cms.forms.recipients') as $recipient) {
            if (is_string($recipient) && $recipient !== '') {
                $recipients[] = $recipient;
            }
        }

        return $recipients;
    }

    /** The form must keep working when the database is down, so a failed read is "no contact". */
    private function siteContact(): ?string
    {
        try {
            $contact = SiteSetting::query()->value('contact_email');
        } catch (QueryException) {
            return null;
        }

        return is_string($contact) && $contact !== '' ? $contact : null;
    }
}
