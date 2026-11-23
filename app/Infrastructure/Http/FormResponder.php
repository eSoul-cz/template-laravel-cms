<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Contract\Cms\FormRecipients;
use App\Domain\Cms\FormOutcome;
use App\Infrastructure\Cms\FormOrigins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the outcome of a form post into the answer of the endpoint. A plain HTML post is answered with a 303
 * back to `return_to` (`?form=<code>#form-result`); a request that accepts JSON gets `{status, code, message}`.
 */
final readonly class FormResponder
{
    public const string RESULT_ANCHOR = 'form-result';

    public function __construct(private FormOrigins $origins, private FormRecipients $recipients) {}

    /** @param  array<string, array<string>>  $errors  validation errors by field, JSON answers only */
    public function respond(Request $request, FormOutcome $outcome, array $errors = []): Response
    {
        $message = $outcome->message($outcome->isSent() ? null : $this->recipients->contact());

        if ($request->wantsJson()) {
            return response()->json([
                'status' => $outcome->isSent() ? 'sent' : 'error',
                'code' => $outcome->value,
                'message' => $message,
                ...($errors === [] ? [] : ['errors' => $errors]),
            ], $this->httpStatus($outcome));
        }

        // A forbidden origin gets no redirect: it is not trusted, and neither is the address it asks for.
        if ($outcome === FormOutcome::OriginForbidden) {
            return response($message, 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return new RedirectResponse($this->redirectUrl($request, $outcome), 303);
    }

    public function httpStatus(FormOutcome $outcome): int
    {
        return match ($outcome) {
            FormOutcome::Sent => 200,
            FormOutcome::Invalid, FormOutcome::CaptchaMissing => 422,
            FormOutcome::OriginForbidden, FormOutcome::CaptchaFailed => 403,
            FormOutcome::RateLimited => 429,
            FormOutcome::CaptchaUnavailable, FormOutcome::Unavailable, FormOutcome::Failed => 503,
        };
    }

    /** Origin of the page that posted: the `Origin` header, else the origin of the `Referer`. */
    public function requestOrigin(Request $request): ?string
    {
        foreach (['Origin', 'Referer'] as $header) {
            $value = $request->headers->get($header);

            if ($value !== null && ($origin = $this->origins->originOf($value)) !== null) {
                return $origin;
            }
        }

        return null;
    }

    private function redirectUrl(Request $request, FormOutcome $outcome): string
    {
        $target = parse_url($this->returnTo($request));
        $query = [];
        parse_str($target['query'] ?? '', $query);
        $query['form'] = $outcome->value;

        return ($target['scheme'] ?? 'https') . '://' . ($target['host'] ?? '')
            . (isset($target['port']) ? ':' . $target['port'] : '')
            . ($target['path'] ?? '/')
            . '?' . http_build_query($query)
            . '#' . self::RESULT_ANCHOR;
    }

    /**
     * `return_to` when it is on an allowed origin, else `/` of the site that posted (the middleware has checked
     * it: with one allowed origin that is the first allowed origin), else this application.
     */
    private function returnTo(Request $request): string
    {
        $returnTo = $request->input('return_to');

        if (is_string($returnTo) && $this->origins->originOf($returnTo) !== null && $this->origins->permits($returnTo)) {
            return $returnTo;
        }

        $origin = $this->requestOrigin($request) ?? $this->origins->allowed()[0] ?? config()->string('app.url');

        return rtrim($origin, '/') . '/';
    }
}
