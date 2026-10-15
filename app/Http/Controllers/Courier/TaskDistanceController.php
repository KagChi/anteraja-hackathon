<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\DistanceRequest;
use App\Models\Package;
use App\Support\GeoDistance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class TaskDistanceController extends Controller
{
    public function __invoke(DistanceRequest $request, string $trackingNumber): JsonResponse|Response
    {
        $package = Package::where('tracking_number', $trackingNumber)
            ->firstOrFail();

        Gate::authorize('view', $package);

        if ($package->recipient_latitude === null || $package->recipient_longitude === null) {
            return response()->noContent();
        }

        $courierLat = (float) $request->validated('lat');
        $courierLng = (float) $request->validated('lng');

        $distanceKm = GeoDistance::haversine(
            $courierLat,
            $courierLng,
            $package->recipient_latitude,
            $package->recipient_longitude
        );

        $etaMinutes = GeoDistance::etaMinutes($distanceKm);

        return response()->json([
            'distance_km' => $distanceKm,
            'eta_minutes' => $etaMinutes,
        ]);
    }
}
