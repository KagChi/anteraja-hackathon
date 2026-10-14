<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Courier\MarkArrived;
use App\Enums\GpsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\MarkArrivedRequest;
use App\Models\Package;
use App\Models\Staff;
use App\Services\Gps\GpsValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class MarkArrivedController extends Controller
{
    public function __invoke(
        MarkArrivedRequest $request,
        string $trackingNumber,
        MarkArrived $action,
        GpsValidator $gps,
    ): JsonResponse {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        $lat = $request->fixLatitude();
        $lng = $request->fixLongitude();

        // Arriving records where the courier is without refusing the step; the
        // hub only needs the distance, and walking the last block is normal.
        $gps->validate(
            package: $package,
            courier: $courier,
            action: GpsAction::ARRIVE,
            signals: $request->gpsSignals(),
            latitude: $lat,
            longitude: $lng,
            enforceGeofence: false,
        );

        $arrived = $action->execute($package, $courier, $lat, $lng);

        return response()->json([
            'message' => 'Status berhasil diperbarui: Tiba di lokasi.',
            'package' => $arrived->fresh(['statusRef', 'latestMilestone']),
        ]);
    }
}
