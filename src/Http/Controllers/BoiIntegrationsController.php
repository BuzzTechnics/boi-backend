<?php

namespace Boi\Backend\Http\Controllers;

use Boi\Backend\Services\BOI;
use Boi\Backend\Support\BoiIntegrationsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * BVN / NIN validation and related integration entry points that do not depend on host application models.
 * Host apps (e.g. Glow) typically extend this controller to add application-specific actions.
 */
class BoiIntegrationsController extends Controller
{
    /** Shown when identity verification can't be reached (boi-api down, or its upstream gateway timed out). */
    private const VERIFICATION_UNAVAILABLE = 'Identity verification is temporarily unavailable. Please try again in a few minutes.';

    public function boiValidate(Request $request): JsonResponse
    {
        $request->validate([
            'bvn' => 'nullable|string|max:11|min:11',
            'nin' => 'nullable|string|max:11|min:11',
        ]);

        if (! $request->filled('bvn') && ! $request->filled('nin')) {
            throw ValidationException::withMessages([
                'bvn' => ['Either bvn or nin is required.'],
            ]);
        }

        $token = $request->header('X-XSRF-TOKEN');

        if ($http = BoiIntegrationsClient::http()) {
            try {
                $upstream = $http->post('/api/identity/validate', [
                    'bvn' => $request->bvn,
                    'nin' => $request->nin,
                ]);
            } catch (ConnectionException $e) {
                // boi-api itself unreachable / timed out.
                return response()->json(['message' => self::VERIFICATION_UNAVAILABLE], 503);
            }

            if ($upstream->failed()) {
                $status = $upstream->status();
                $body = $upstream->json();
                $message = is_array($body) ? ($body['message'] ?? null) : null;

                // Never surface a bare 5xx / "Server Error" to the applicant — that
                // usually means the upstream verification gateway is down or timed out.
                if ($status >= 500 || $message === null || $message === '' || strcasecmp((string) $message, 'Server Error') === 0) {
                    return response()->json(['message' => self::VERIFICATION_UNAVAILABLE], 503);
                }

                return response()->json($body, $status ?: 502);
            }

            $result = $upstream->json();
        } else {
            if ($request->bvn) {
                try {
                    $result = BOI::customerBvn($request->bvn);
                } catch (RequestException $e) {
                    return response()->json([
                        'message' => 'We couldn\'t verify your BVN. Please check the number and try again, or try again later.',
                    ], 422);
                }
            }

            if ($request->nin) {
                try {
                    $result = BOI::customerNin($request->nin);
                } catch (RequestException $e) {
                    return response()->json([
                        'message' => 'We couldn\'t verify your NIN. Please check the number and try again, or try again later.',
                    ], 422);
                }
            }
        }

        if ($request->bvn) {
            cache([$request->bvn => "bvn_$token"], 60);
        }

        if ($request->nin) {
            cache([$request->nin => "nin_$token"], 60);
        }

        return response()->json($result);
    }
}
