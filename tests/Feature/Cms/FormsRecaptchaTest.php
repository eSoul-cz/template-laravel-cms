<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Infrastructure\Models\FormSubmission;
use App\Infrastructure\Models\SiteSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class FormsRecaptchaTest extends FormsTestCase
{
    public function test_the_token_is_verified_with_the_secret_at_google(): void
    {
        $this->submit()->assertStatus(303);

        Http::assertSentCount(1);
        Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request->method() === 'POST'
            && $request->isForm()
            && $request['secret'] === 'secret'
            && $request['response'] === 'token');
    }

    public function test_a_post_without_a_token_is_rejected_with_the_contact_alternative(): void
    {
        SiteSetting::current()->update(['contact_email' => 'office@example.com']);

        $this->submit(['recaptcha_token' => ''], json: true)
            ->assertStatus(422)
            ->assertJsonPath('code', 'captcha_missing')
            ->assertJsonPath('message', 'Enable JavaScript or contact us at office@example.com to send this form.');

        $this->submit(['recaptcha_token' => ''])->assertRedirect('https://example.com/?form=captcha_missing#form-result');
        self::assertSame(0, FormSubmission::query()->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_the_contact_alternative_falls_back_to_the_first_recipient(): void
    {
        $this->submit(['recaptcha_token' => ''], json: true)
            ->assertJsonPath('message', 'Enable JavaScript or contact us at owner@example.com to send this form.');
    }

    public function test_a_low_score_is_rejected(): void
    {
        $this->fakeRecaptcha(['score' => 0.3]);

        $this->submit(json: true)->assertStatus(403)->assertJsonPath('code', 'captcha_failed');

        self::assertSame(0, FormSubmission::query()->count());
        Mail::assertNothingSent();
    }

    public function test_the_score_threshold_is_configurable(): void
    {
        $this->fakeRecaptcha(['score' => 0.3]);
        config(['cms.forms.recaptcha.min_score' => 0.2]);

        $this->submit()->assertRedirect('https://example.com/?form=sent#form-result');
    }

    public function test_a_failed_wrong_action_or_wrong_hostname_is_rejected(): void
    {
        foreach ([['success' => false], ['action' => 'login'], ['hostname' => 'evil.example.net'], ['score' => null]] as $answer) {
            $this->fakeRecaptcha($answer);

            $this->submit(json: true)->assertStatus(403)->assertJsonPath('code', 'captcha_failed');
        }

        self::assertSame(0, FormSubmission::query()->count());
    }

    public function test_an_unreachable_verifier_rejects_the_post(): void
    {
        $this->fakeVerifier(static fn () => throw new ConnectionException('timeout'));

        $this->submit(json: true)->assertStatus(503)->assertJsonPath('code', 'captcha_unavailable');

        $this->fakeVerifier(static fn () => Http::response('oops', 500));

        $this->submit(json: true)->assertStatus(503)->assertJsonPath('code', 'captcha_unavailable');
        self::assertSame(0, FormSubmission::query()->count());
        Mail::assertNothingSent();
    }

    public function test_without_a_secret_outside_production_the_check_is_skipped_with_a_warning(): void
    {
        config(['cms.forms.recaptcha.secret' => null]);
        Log::spy();

        $this->submit(['recaptcha_token' => ''])->assertRedirect('https://example.com/?form=sent#form-result');

        Log::shouldHaveReceived('warning')->once();
        Http::assertNothingSent();
        self::assertNull(FormSubmission::query()->sole()->recaptcha_score);
    }

    public function test_without_a_secret_in_production_the_endpoint_is_unavailable(): void
    {
        config(['cms.forms.recaptcha.secret' => '']);
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->submit(json: true)->assertStatus(503)->assertJsonPath('code', 'unavailable');
        $this->submit()->assertRedirect('https://example.com/?form=unavailable#form-result');

        self::assertSame(0, FormSubmission::query()->count());
        Mail::assertNothingSent();
    }
}
