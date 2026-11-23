<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Contract\Cms\FormMailer;
use App\Application\Dto\Cms\FormMessage;
use App\Infrastructure\Models\FormSubmission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

final class FormsDeliveryTest extends FormsTestCase
{
    public function test_a_failing_mail_keeps_the_record_and_still_succeeds(): void
    {
        $this->failMail();

        $this->submit(json: true)->assertOk()->assertJsonPath('code', 'sent');

        self::assertSame('failed', FormSubmission::query()->sole()->mail_status->value);
        self::assertFileDoesNotExist($this->lastResortFile());
    }

    public function test_without_a_recipient_the_mail_is_failed_and_logged(): void
    {
        config(['cms.forms.recipients' => []]);
        Log::spy();

        $this->submit(json: true)->assertOk()->assertJsonPath('code', 'sent');

        self::assertSame('failed', FormSubmission::query()->sole()->mail_status->value);
        Mail::assertNothingSent();
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_failing_database_does_not_stop_the_mail(): void
    {
        FormSubmission::creating(static fn () => throw new RuntimeException('database is down'));

        $this->submit(json: true)->assertOk()->assertJsonPath('code', 'sent');

        Mail::assertSentCount(1);
        self::assertSame(0, FormSubmission::query()->count());
        self::assertFileDoesNotExist($this->lastResortFile());
    }

    public function test_when_both_fail_the_submission_goes_to_the_last_resort_file(): void
    {
        FormSubmission::creating(static fn () => throw new RuntimeException('database is down'));
        $this->failMail();

        $this->submit(json: true)->assertOk()->assertJsonPath('code', 'sent');
        $this->submit(['name' => 'Second'], client: '203.0.113.8')->assertStatus(303);

        $lines = file($this->lastResortFile(), FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(2, $lines);

        $first = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($first);
        self::assertSame('Jane Doe', $first['name']);
        self::assertSame('jane@example.org', $first['email']);
        self::assertSame('Hello, I would like an offer.', $first['message']);
        self::assertSame(hash_hmac('sha256', self::CLIENT, config()->string('app.key')), $first['client_hash']);
        self::assertStringNotContainsString(self::CLIENT, implode("\n", $lines));
        self::assertSame(0, FormSubmission::query()->count());
    }

    public function test_when_the_last_resort_file_fails_too_the_post_fails(): void
    {
        FormSubmission::creating(static fn () => throw new RuntimeException('database is down'));
        $this->failMail();
        // A directory cannot be created below a regular file.
        $blocker = tempnam(sys_get_temp_dir(), 'forms-blocker');
        self::assertIsString($blocker);
        config(['cms.forms.last_resort_path' => $blocker . '/forms']);

        try {
            $this->submit(json: true)->assertStatus(503)->assertJsonPath('code', 'failed');
            $this->submit()->assertRedirect('https://example.com/?form=failed#form-result');
        } finally {
            unlink($blocker);
        }
    }

    private function failMail(): void
    {
        $this->app->instance(FormMailer::class, new class implements FormMailer
        {
            public function send(FormMessage $message): void
            {
                throw new RuntimeException('SMTP is down');
            }
        });
    }

    private function lastResortFile(): string
    {
        return $this->lastResort . '/' . now()->format('Y-m') . '.jsonl';
    }
}
