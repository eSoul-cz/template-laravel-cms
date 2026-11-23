<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use App\Application\Contract\Cms\CaptchaVerifier;
use App\Application\Dto\Cms\CaptchaResult;
use App\Domain\Cms\CaptchaVerdict;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v3. The secret is `cms.forms.recaptcha.secret`. Without one the check is skipped with a
 * warning in a non-production environment, and the endpoint is refused in production: a form that silently
 * has no protection is a misconfiguration, not a feature.
 */
final readonly class RecaptchaVerifier implements CaptchaVerifier
{
    private const string VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(private FormOrigins $origins) {}

    public function verify(?string $token, string $action): CaptchaResult
    {
        $secret = config('cms.forms.recaptcha.secret');

        if ( ! is_string($secret) || $secret === '') {
            if (app()->isProduction()) {
                return new CaptchaResult(CaptchaVerdict::Misconfigured);
            }

            Log::warning('Form reCAPTCHA is not verified: CMS_FORMS_RECAPTCHA_SECRET is empty (allowed outside production only).');

            return new CaptchaResult(CaptchaVerdict::Skipped);
        }

        if ($token === null || $token === '') {
            return new CaptchaResult(CaptchaVerdict::Missing);
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, ['secret' => $secret, 'response' => $token]);
        } catch (ConnectionException $exception) {
            Log::warning('reCAPTCHA verification is unreachable: ' . $exception->getMessage());

            return new CaptchaResult(CaptchaVerdict::Unavailable);
        }

        if ( ! $response->successful()) {
            Log::warning('reCAPTCHA verification answered HTTP ' . $response->status() . '.');

            return new CaptchaResult(CaptchaVerdict::Unavailable);
        }

        $score = $response->json('score');
        $score = is_int($score) || is_float($score) ? (float) $score : null;

        if ($response->json('success') !== true || $response->json('action') !== $action || $score === null) {
            return new CaptchaResult(CaptchaVerdict::Failed, $score);
        }

        if ($score < config()->float('cms.forms.recaptcha.min_score')) {
            return new CaptchaResult(CaptchaVerdict::Failed, $score);
        }

        $hosts = $this->origins->hosts();
        $hostname = $response->json('hostname');

        if ($hosts !== [] && ( ! is_string($hostname) || ! in_array(strtolower($hostname), $hosts, true))) {
            return new CaptchaResult(CaptchaVerdict::Failed, $score);
        }

        return new CaptchaResult(CaptchaVerdict::Passed, $score);
    }
}
