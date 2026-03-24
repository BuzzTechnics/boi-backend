<?php

namespace Boi\Backend\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Restricts API requests to the app URL's host (referer/origin) and validates BVN/NIN token flow.
 */
final class TrustedSources
{
    public function handle(Request $request, Closure $next)
    {
        $allowedDomain = parse_url((string) config('app.url'), PHP_URL_HOST);
        $referer = $request->headers->get('referer');
        $origin = $request->headers->get('origin');

        if ($referer && parse_url($referer, PHP_URL_HOST) !== $allowedDomain) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if ($origin && parse_url($origin, PHP_URL_HOST) !== $allowedDomain) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if (! $referer && ! $origin) {
            // Same-origin axios/fetch often omits Origin; Referer may be stripped (Referrer-Policy).
            // Trust Sec-Fetch-Site (Chromium) or a Sanctum SPA CSRF header instead.
            $secFetchSite = $request->headers->get('Sec-Fetch-Site');
            if (in_array($secFetchSite, ['same-origin', 'same-site'], true)) {
                return $next($request);
            }
            if ($request->headers->has('X-XSRF-TOKEN')) {
                return $next($request);
            }

            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if ($request->bvn && 'bvn_'.$request->header('X-XSRF-TOKEN') === cache($request->bvn)) {
            return response()->json(['error' => 'Unauthorized'], 412);
        }

        if ($request->nin && 'nin_'.$request->header('X-XSRF-TOKEN') === cache($request->nin)) {
            return response()->json(['error' => 'Unauthorized'], 412);
        }

        return $next($request);
    }
}
