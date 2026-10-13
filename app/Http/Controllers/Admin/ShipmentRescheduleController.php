<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RescheduleShipment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RescheduleShipmentRequest;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;

final class ShipmentRescheduleController extends Controller
{
    public function __invoke(
        RescheduleShipmentRequest $request,
        string $trackingNumber,
        RescheduleShipment $action
    ): JsonResponse {
        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();

        /** @var Staff $admin */
        $admin = $request->user();

        [$start, $end] = $request->toWindow();

        $updated = $action->execute($package, $start, $end, $admin);

        return response()->json([
            'message' => "Shipment {$trackingNumber} berhasil dijadwalkan ulang dan menunggu konfirmasi pembeli.",
            'package' => $updated->load(['buyer', 'statusRef']),
        ]);
    }
}
