<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Infrastructure\Mail\ContactFormMail;
use App\Infrastructure\Models\FormSubmission;
use App\Infrastructure\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

final class FormsTest extends FormsTestCase
{
    public function test_a_post_is_stored_mailed_and_redirected_back(): void
    {
        $this->submit()
            ->assertStatus(303)
            ->assertRedirect('https://example.com/?form=sent#form-result');

        $submission = FormSubmission::query()->sole();
        self::assertSame('contact', $submission->form);
        self::assertSame('Jane Doe', $submission->name);
        self::assertSame('jane@example.org', $submission->email);
        self::assertSame('0.90', $submission->recaptcha_score);
        self::assertSame('sent', $submission->mail_status->value);

        Mail::assertSent(ContactFormMail::class, static fn (ContactFormMail $mail): bool => $mail->hasTo('owner@example.com')
            && $mail->hasReplyTo('jane@example.org')
            && $mail->submission->message === 'Hello, I would like an offer.');
    }

    public function test_the_endpoint_needs_no_session_and_sets_no_cookie(): void
    {
        $response = $this->submit();

        $response->assertStatus(303);
        self::assertSame([], $response->headers->getCookies());
    }

    public function test_json_clients_get_a_json_answer_with_the_cors_header(): void
    {
        $this->submit(json: true)
            ->assertOk()
            ->assertExactJson(['status' => 'sent', 'code' => 'sent', 'message' => 'Thank you, your message has been sent.'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://example.com');

        self::assertSame(1, FormSubmission::query()->count());
    }

    public function test_return_to_on_an_allowed_origin_is_used_and_keeps_its_query(): void
    {
        $this->submit(['return_to' => 'https://example.com/kontakt/?a=1&form=old#top'])
            ->assertRedirect('https://example.com/kontakt/?a=1&form=sent#form-result');
    }

    public function test_return_to_on_a_foreign_origin_is_invalid_and_never_followed(): void
    {
        $this->submit(['return_to' => 'https://evil.example.net/phish'])
            ->assertRedirect('https://example.com/?form=invalid#form-result');

        $this->submit(['return_to' => 'https://evil.example.net/phish'], json: true)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['return_to']);

        self::assertSame(0, FormSubmission::query()->count());
    }

    public function test_invalid_input_is_reported_by_field(): void
    {
        $this->submit(['name' => '', 'email' => 'not-an-email', 'message' => str_repeat('x', 5001)], json: true)
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid')
            ->assertJsonValidationErrors(['name', 'email', 'message']);

        $this->submit(['name' => str_repeat('x', 121)], json: true)->assertJsonValidationErrors(['name']);
        $this->submit(['name' => ''])->assertRedirect('https://example.com/?form=invalid#form-result');

        self::assertSame(0, FormSubmission::query()->count());
        Mail::assertNothingSent();
    }

    public function test_a_filled_honeypot_gets_a_fake_success_and_nothing_happens(): void
    {
        $this->submit(['website' => 'https://spam.example'])->assertRedirect('https://example.com/?form=sent#form-result');
        $this->submit(['website' => 'https://spam.example'], json: true)->assertOk()->assertJsonPath('code', 'sent');
        // Not even a robot that also forgot the other fields learns that the post was rejected.
        $this->submit(['website' => 'x', 'name' => '', 'email' => ''], json: true)->assertOk()->assertJsonPath('code', 'sent');

        self::assertSame(0, FormSubmission::query()->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_the_honeypot_field_name_is_configurable(): void
    {
        config(['cms.forms.honeypot_field' => 'company_url']);

        $this->submit(['company_url' => 'x'])->assertRedirect('https://example.com/?form=sent#form-result');
        self::assertSame(0, FormSubmission::query()->count());

        // The old name is now an ordinary unknown field.
        $this->submit(['website' => 'x'])->assertRedirect('https://example.com/?form=sent#form-result');
        self::assertSame(1, FormSubmission::query()->count());
    }

    public function test_the_sixth_post_of_a_client_is_rejected_and_clients_are_independent(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->submit()->assertStatus(303);
        }

        $this->submit(json: true)->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        $this->submit()->assertRedirect('https://example.com/?form=rate_limited#form-result');
        $this->submit(client: '203.0.113.8')->assertRedirect('https://example.com/?form=sent#form-result');

        self::assertSame(6, FormSubmission::query()->count());
        Mail::assertSentCount(6);
    }

    public function test_the_rate_limit_is_configurable(): void
    {
        config(['cms.forms.rate_limit.attempts' => 1]);

        $this->submit()->assertRedirect('https://example.com/?form=sent#form-result');
        $this->submit()->assertRedirect('https://example.com/?form=rate_limited#form-result');
    }

    public function test_the_client_address_is_stored_only_as_a_hash(): void
    {
        $this->submit()->assertStatus(303);

        $submission = FormSubmission::query()->sole();
        self::assertSame(hash_hmac('sha256', self::CLIENT, config()->string('app.key')), $submission->client_hash);
        self::assertStringNotContainsString(self::CLIENT, json_encode($submission->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_the_recipient_falls_back_to_the_contact_email_of_the_site_settings(): void
    {
        config(['cms.forms.recipients' => []]);
        SiteSetting::current()->update(['contact_email' => 'office@example.com']);

        $this->submit()->assertStatus(303);

        Mail::assertSent(ContactFormMail::class, static fn (ContactFormMail $mail): bool => $mail->hasTo('office@example.com'));
    }

    public function test_the_subject_cannot_carry_a_header_injection(): void
    {
        $this->submit(['name' => "Jane\r\nBcc: victim@example.org"])->assertStatus(303);

        Mail::assertSent(ContactFormMail::class, static function (ContactFormMail $mail): bool {
            $subject = $mail->envelope()->subject;

            return is_string($subject) && ! str_contains($subject, "\n") && ! str_contains($subject, "\r");
        });
    }
}
