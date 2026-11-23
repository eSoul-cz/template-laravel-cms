<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Cms\FormMailStatus;
use App\Infrastructure\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'form' => 'contact',
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'message' => fake()->paragraph(),
            'client_hash' => hash('sha256', fake()->ipv4()),
            'recaptcha_score' => 0.9,
            'mail_status' => FormMailStatus::Sent,
        ];
    }
}
