<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use App\Application\Contract\Cms\FormLastResort;
use App\Application\Dto\Cms\FormMessage;
use RuntimeException;

/** One JSON line per submission in `<cms.forms.last_resort_path>/YYYY-MM.jsonl`, outside the webroot. The address is the hash only. */
final readonly class LastResortFile implements FormLastResort
{
    public function append(FormMessage $message, string $clientHash, ?float $recaptchaScore): void
    {
        $directory = config()->string('cms.forms.last_resort_path');

        if ( ! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}.");
        }

        $line = json_encode([
            'at' => now()->toIso8601String(),
            'form' => $message->form,
            'name' => $message->name,
            'email' => $message->email,
            'message' => $message->message,
            'client_hash' => $clientHash,
            'recaptcha_score' => $recaptchaScore,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $file = $directory . '/' . now()->format('Y-m') . '.jsonl';

        if (file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Cannot write {$file}.");
        }
    }
}
