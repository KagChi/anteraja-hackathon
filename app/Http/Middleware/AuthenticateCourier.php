<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the courier API for both callers it serves.
 *
 * The console and the feature tests sign in with the session guard the admin
 * panel uses; the Android app sends a Sanctum bearer token it got from
 * /api/courier/auth/login. Resolving the token here, rather than through
 * `auth:sanctum`, keeps the app's default guard untouched, so a token request
 * cannot change how an unrelated session request authenticates.
 */
final class AuthenticateCourier
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            $token = $request->bearerToken();

            if ($token !== null) {
                // Resolve the token directly rather than through the sanctum
                // guard: the guard caches its user for the life of the
                // process, so a revoked token would keep authenticating until
                // the process restarted.
                $accessToken = PersonalAccessToken::findToken($token);

                $expired = $accessToken?->expires_at !== null && $accessToken->expires_at->isPast();

                if ($accessToken !== null && $accessToken->tokenable !== null && ! $expired) {
                    auth()->setUser($accessToken->tokenable->withAccessToken($accessToken));
                }
            }
        }

        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // A session caller carries no token, and the ability middleware reads
        // one; pair them with a transient token so the same ability check
        // applies to both paths.
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() === null) {
            $user->withAccessToken(new TransientToken());
        }

        return $next($request);
    }
}
