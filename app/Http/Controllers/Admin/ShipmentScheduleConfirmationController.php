<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Packages\RequestScheduleConfirmation;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleWindowRequest;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;

final class ShipmentScheduleConfirmationController extends Controller {
    public function __invoke(
        ScheduleWindowRequest $request,
        string $trackingNumber,
        RequestScheduleConfirmation $action
    ): JsonResponse {
        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();

        /** @var Staff $admin */
        $admin = $request->user();

        [$start, $end] = $request->toWindow() ?? [null, null];

        $updated = $action->execute($package, $admin, $start, $end);

        return response()->json([
            'message' => "Permintaan konfirmasi jadwal resi {$trackingNumber} dikirim ke penerima.",
            'package' => $updated->load(['buyer', 'courier', 'statusRef']),
        ]);
    }
}
