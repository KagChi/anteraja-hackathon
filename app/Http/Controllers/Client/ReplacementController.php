<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Client\RequestReplacement;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\Client\ClientLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReplacementController extends Controller
{
    public function __invoke(
        Request $request,
        RequestReplacement $action,
        ClientLinkService $linkService,
    ): JsonResponse {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        $result = $action->execute($package);

        if ($result->id !== $package->id) {
            return response()->json([
                'success' => true,
                'message' => 'Pengajuan pengiriman ulang berhasil. Silakan konfirmasi jadwal paket pengganti.',
                'redirect_url' => $linkService->schedule($result),
                'replacement_tracking_number' => $result->tracking_number,
                'replacement_token' => $result->client_token,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan pengiriman ulang berhasil diajukan ke tim operasional.',
        ]);
    }
}
