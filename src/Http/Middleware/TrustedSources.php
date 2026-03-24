<?php

namespace Boi\Backend\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Restricts API requests so Referer/Origin match this request's host (not only APP_URL),
 * and validates BVN/NIN token flow.
 */
final class TrustedSources
{
    public function handle(Request $request, Closure $next)
    {
        $trustedHost = $this->trustedHost($request);
        $referer = $request->headers->get('referer');
        $origin = $request->headers->get('origin');

        if ($referer) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            if ($refererHost === null || $refererHost === false || $refererHost === '') {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }
            if (strcasecmp((string) $refererHost, $trustedHost) !== 0) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }
        }

        if ($origin) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            if ($originHost === null || $originHost === false || $originHost === '') {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }
            if (strcasecmp((string) $originHost, $trustedHost) !== 0) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }
        }

        if (! $referer && ! $origin) {
            // Same-origin axios/fetch often omits Origin; Referer may be stripped (Referrer-Policy).
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

    /**
     * Host the client is actually talking to (avoids APP_URL vs real URL mismatches: http/https, www, Herd, etc.).
     */
    private function trustedHost(Request $request): string
    {
        $host = $request->getHost();
        if ($host !== '') {
            return $host;
        }

        $fromConfig = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($fromConfig) && $fromConfig !== '' ? $fromConfig : '';
    }
}
