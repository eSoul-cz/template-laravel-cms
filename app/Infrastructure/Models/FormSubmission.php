<?php

declare(strict_types=1);

namespace App\Infrastructure\Models;

use App\Domain\Cms\FormMailStatus;
use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A form submission kept as the backup of the notification e-mail. Personal data: delete it after the
 * retention period of the project (docs/forms.md).
 *
 * @property int $id
 * @property string $form
 * @property string $name
 * @property string $email
 * @property string $message
 * @property string $client_hash HMAC-SHA256 of the client address, never the address
 * @property string|null $recaptcha_score
 * @property FormMailStatus $mail_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['form', 'name', 'email', 'message', 'client_hash', 'recaptcha_score', 'mail_status'])]
#[UseFactory(FormSubmissionFactory::class)]
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recaptcha_score' => 'decimal:2',
            'mail_status' => FormMailStatus::class,
        ];
    }
}
