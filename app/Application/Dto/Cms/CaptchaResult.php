<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

use App\Domain\Cms\CaptchaVerdict;

final readonly class CaptchaResult
{
    public function __construct(public CaptchaVerdict $verdict, public ?float $score = null) {}
}
