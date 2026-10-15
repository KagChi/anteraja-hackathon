<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Packages\RequestScheduleConfirmation;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShipmentScheduleConfirmationController extends Controller {
    public function __invoke(
        Request $request,
        string $trackingNumber,
        RequestScheduleConfirmation $action
    ): JsonResponse {
        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();

        /** @var Staff $admin */
        $admin = $request->user();

        $updated = $action->execute($package, $admin);

        return response()->json([
            'message' => "Permintaan konfirmasi jadwal resi {$trackingNumber} dikirim ke penerima.",
            'package' => $updated->load(['buyer', 'courier', 'statusRef']),
        ]);
    }
}
