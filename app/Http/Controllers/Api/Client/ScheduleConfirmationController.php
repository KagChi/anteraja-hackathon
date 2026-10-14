<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Actions\Client\ConfirmSchedule;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\Client\ClientLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ScheduleConfirmationController extends Controller
{
    public function __invoke(
        Request $request,
        ConfirmSchedule $action,
        ClientLinkService $linkService,
    ): JsonResponse {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        $updated = $action->execute($package);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal pengantaran berhasil dikonfirmasi.',
            'redirect_url' => $linkService->tracking($updated),
            'data' => [
                'status' => $updated->status->value,
                'tracking_number' => $updated->tracking_number,
            ],
        ]);
    }
}
