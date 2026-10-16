<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Packages\RequestScheduleConfirmation;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleWindowRequest;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class RequestConfirmationController extends Controller {
    public function __invoke(
        ScheduleWindowRequest $request,
        string $trackingNumber,
        RequestScheduleConfirmation $action
    ): JsonResponse {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        [$start, $end] = $request->toWindow() ?? [null, null];

        $updated = $action->execute($package, $courier, $start, $end);

        return response()->json([
            'message' => "Permintaan konfirmasi jadwal resi {$trackingNumber} dikirim ke penerima.",
            'package' => $updated->fresh(['statusRef', 'courier']),
        ]);
    }
}
