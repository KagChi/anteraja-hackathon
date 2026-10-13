<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Client\RequestReschedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\RescheduleRequest;
use App\Models\Package;
use App\Services\Client\ClientLinkService;
use Illuminate\Http\JsonResponse;

final class ScheduleRescheduleController extends Controller
{
    public function __invoke(
        RescheduleRequest $request,
        RequestReschedule $action,
        ClientLinkService $linkService,
    ): JsonResponse {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        $updated = $action->execute($package, $request->toData());

        return response()->json([
            'success' => true,
            'message' => 'Permintaan perubahan jadwal berhasil dikirim.',
            'redirect_url' => $linkService->tracking($updated),
        ]);
    }
}
