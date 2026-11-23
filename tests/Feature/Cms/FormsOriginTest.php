<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Infrastructure\Models\FormSubmission;
use Illuminate\Support\Facades\Mail;

final class FormsOriginTest extends FormsTestCase
{
    public function test_a_foreign_origin_is_forbidden(): void
    {
        foreach (['https://evil.example.net', 'https://example.com.evil.net', 'http://example.com', 'https://example.com:8443', 'null'] as $origin) {
            $this->submit(headers: ['Origin' => $origin])->assertForbidden();
            $this->submit(headers: ['Origin' => $origin], json: true)->assertForbidden()->assertJsonPath('code', 'origin_forbidden');
        }

        self::assertSame(0, FormSubmission::query()->count());
        Mail::assertNothingSent();
    }

    public function test_a_forbidden_origin_is_never_redirected_and_gets_no_cors_header(): void
    {
        $response = $this->submit(['return_to' => 'https://evil.example.net/'], ['Origin' => 'https://evil.example.net']);

        $response->assertForbidden();
        self::assertFalse($response->headers->has('Location'));
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function test_a_post_without_origin_or_referer_is_forbidden(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::CLIENT])
            ->post(route('forms.contact'), $this->validInput())
            ->assertForbidden();
    }

    public function test_the_origin_of_the_referer_is_accepted_when_there_is_no_origin_header(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::CLIENT])
            ->post(route('forms.contact'), $this->validInput(), ['Referer' => 'https://example.com/contact/'])
            ->assertRedirect('https://example.com/?form=sent#form-result');

        $this->withServerVariables(['REMOTE_ADDR' => self::CLIENT])
            ->post(route('forms.contact'), $this->validInput(), ['Referer' => 'https://evil.example.net/contact/'])
            ->assertForbidden();
    }

    public function test_an_empty_list_rejects_everything_in_production(): void
    {
        config(['cms.forms.allowed_origins' => []]);

        $this->submit()->assertForbidden();
        $this->submit(headers: ['Origin' => ''])->assertForbidden();
    }

    public function test_an_empty_list_accepts_any_origin_locally(): void
    {
        config(['cms.forms.allowed_origins' => []]);
        $this->app->detectEnvironment(static fn (): string => 'local');

        $this->submit(headers: ['Origin' => 'http://localhost:4321'])
            ->assertRedirect('http://localhost:4321/?form=sent#form-result');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post(route('forms.contact'), $this->validInput())
            ->assertRedirect(config()->string('app.url') . '/?form=sent#form-result');
    }

    public function test_several_origins_can_be_allowed(): void
    {
        config(['cms.forms.allowed_origins' => [self::SITE, 'https://www.example.com']]);
        $this->fakeRecaptcha(['hostname' => 'www.example.com']);

        $this->submit(headers: ['Origin' => 'https://www.example.com'])
            ->assertRedirect('https://www.example.com/?form=sent#form-result');
    }
}
