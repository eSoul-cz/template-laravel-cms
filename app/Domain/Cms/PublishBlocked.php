<?php

declare(strict_types=1);

namespace App\Domain\Cms;

use RuntimeException;

/** A publish cannot be started; `problems` are the reasons shown in the admin. */
final class PublishBlocked extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode("\n", $problems));
    }
}
