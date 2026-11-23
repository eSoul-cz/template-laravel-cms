<?php

declare(strict_types=1);

namespace App\Application\Contract\Cms;

/** Who receives the form submissions and which address a visitor can use instead of the form. */
interface FormRecipients
{
    /** @return list<string> e-mail addresses; empty when nothing is configured */
    public function all(): array;

    /** Public contact address of the site, shown when the form cannot be used; null when unknown. */
    public function contact(): ?string;
}
