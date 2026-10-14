<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the internal endpoints n8n calls — never a browser.
 *
 * The shared secret is the same token the app already sends to the outbound
 * n8n webhook (N8N_WEBHOOK_TOKEN, mirrored to n8n as ANTERAJA_WEBHOOK_TOKEN),
 * so the two directions of the integration authenticate with one value.
 */
final class VerifyInternalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('coldchain.n8n.token', '');

        // An endpoint that cannot check its caller must not answer at all.
        if ($expected === '') {
            return response()->json([
                'message' => 'Endpoint internal belum dikonfigurasi.',
                'code' => 'INTERNAL_TOKEN_UNCONFIGURED',
            ], 503);
        }

        $given = (string) $request->header('X-Anteraja-Token', '');

        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        return $next($request);
    }
}
