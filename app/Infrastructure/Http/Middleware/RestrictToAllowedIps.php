<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/cms-api` only from the addresses of the build: Jenkins calls the export and the callback, never a browser.
 * Tokens protect the content; the allowlist shrinks the surface (a leaked token from another network is not
 * enough). An empty `CMS_API_ALLOWED_IPS` means no restriction until infrastructure confirms the build address.
 */
final class RestrictToAllowedIps
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_values(array_filter(
            (array) config('cms.api_allowed_ips', []),
            is_string(...),
        ));

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            Log::warning('cms-api request from a non-allowed address', ['ip' => $request->ip(), 'path' => $request->path()]);

            return response()->json(['message' => 'forbidden'], 403);
        }

        return $next($request);
    }
}
