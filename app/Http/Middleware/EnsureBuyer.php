<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Buyer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBuyer
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Buyer) {
            return response()->json([
                'message' => 'Akses khusus pembeli terdaftar.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun pembeli dinonaktifkan.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}
