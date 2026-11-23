<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Cms\FormOutcome;
use App\Infrastructure\Cms\FormOrigins;
use App\Infrastructure\Http\FormResponder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The form endpoint has no CSRF token (the static site cannot have one) and no session, so this is its
 * browser-side gate: the `Origin` header (or the origin of the `Referer`) must be in `cms.forms.allowed_origins`.
 * Browsers always send `Origin` on a cross-origin POST. The answer carries the CORS header for that origin, so
 * a script on the site can read the JSON result.
 */
final readonly class RequireAllowedFormOrigin
{
    public function __construct(private FormOrigins $origins, private FormResponder $responder) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $this->responder->requestOrigin($request);

        if ( ! $this->origins->permits($origin)) {
            return $this->responder->respond($request, FormOutcome::OriginForbidden);
        }

        $response = $next($request);

        if ($origin !== null) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin', false);
        }

        return $response;
    }
}
