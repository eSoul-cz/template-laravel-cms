<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Shared set-up of the form endpoint tests: an allowed site, a reCAPTCHA secret and a verifier that approves. */
abstract class FormsTestCase extends TestCase
{
    protected const string SITE = 'https://example.com';

    protected const string CLIENT = '203.0.113.7';

    protected string $lastResort;

    /** What Google answers; tests swap it (a second Http::fake() would not replace the first). */
    private Closure $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lastResort = sys_get_temp_dir() . '/forms-last-resort-' . bin2hex(random_bytes(6));

        config([
            'cms.forms.allowed_origins' => [self::SITE],
            'cms.forms.recipients' => ['owner@example.com'],
            'cms.forms.recaptcha.secret' => 'secret',
            'cms.forms.recaptcha.min_score' => 0.5,
            'cms.forms.last_resort_path' => $this->lastResort,
        ]);

        Mail::fake();
        Http::fake(['www.google.com/recaptcha/api/siteverify' => fn () => ($this->verifier)()]);
        $this->fakeRecaptcha();
    }

    protected function tearDown(): void
    {
        new Filesystem()->deleteDirectory($this->lastResort);

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $response  fields of Google's answer that differ from an approval */
    protected function fakeRecaptcha(array $response = []): void
    {
        $this->fakeVerifier(static fn () => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'contact',
            'hostname' => 'example.com',
            ...$response,
        ]));
    }

    protected function fakeVerifier(Closure $verifier): void
    {
        $this->verifier = $verifier;
    }

    /** @return array<string, string> */
    protected function validInput(): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'jane@example.org',
            'message' => 'Hello, I would like an offer.',
            'recaptcha_token' => 'token',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $headers
     */
    protected function submit(array $input = [], array $headers = [], string $client = self::CLIENT, bool $json = false): TestResponse
    {
        return $this
            ->withServerVariables(['REMOTE_ADDR' => $client])
            ->post(route('forms.contact'), [...$this->validInput(), ...$input], [
                'Origin' => self::SITE,
                ...($json ? ['Accept' => 'application/json'] : []),
                ...$headers,
            ]);
    }
}
