<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer token from the `cms.<key>` config (`export_token` for the export, `jenkins.callback_token` for the
 * callback). An unset token disables the endpoint (404); a wrong token answers 401.
 */
final class AuthenticateCmsToken
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next, string $key): Response
    {
        $expected = config("cms.{$key}");

        if ( ! is_string($expected) || $expected === '') {
            return response()->json(['message' => 'not found'], 404);
        }

        $given = $request->bearerToken();

        if ( ! is_string($given) || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
