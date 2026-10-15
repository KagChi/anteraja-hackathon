<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Packages\RequestScheduleConfirmation;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class RequestConfirmationController extends Controller {
    public function __invoke(
        Request $request,
        string $trackingNumber,
        RequestScheduleConfirmation $action
    ): JsonResponse {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        $updated = $action->execute($package, $courier);

        return response()->json([
            'message' => "Permintaan konfirmasi jadwal resi {$trackingNumber} dikirim ke penerima.",
            'package' => $updated->fresh(['statusRef', 'courier']),
        ]);
    }
}
