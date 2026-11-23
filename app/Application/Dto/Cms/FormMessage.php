<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

/** What a visitor wrote in a form. */
final readonly class FormMessage
{
    public function __construct(
        public string $form,
        public string $name,
        public string $email,
        public string $message,
    ) {}
}
