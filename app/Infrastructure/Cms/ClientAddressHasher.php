<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

/** The client address is kept only as an HMAC-SHA256 with the application key: enough to count and to recognise, not to read. */
final readonly class ClientAddressHasher
{
    public function hash(?string $address): string
    {
        return hash_hmac('sha256', $address ?? '', config()->string('app.key'));
    }
}
