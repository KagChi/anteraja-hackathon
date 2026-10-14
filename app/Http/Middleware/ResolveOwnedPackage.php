<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Buyer;
use App\Models\Package;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOwnedPackage
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

        $param = $request->route('package') ?? $request->route('tracking_number') ?? $request->route('resi');

        $resi = $param instanceof Package ? $param->tracking_number : (string) $param;

        /** @var Package|null $package */
        $package = Package::query()
            ->where('buyer_id', $user->id)
            ->where('tracking_number', $resi)
            ->first();

        if ($package === null) {
            return response()->json([
                'message' => 'Paket tidak ditemukan.',
                'code' => 'PACKAGE_NOT_FOUND',
            ], 404);
        }

        $request->attributes->set('client_package', $package);

        return $next($request);
    }
}
