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
            ->with('buyer')
            ->firstOrFail();

        Gate::authorize('view', $package);

        $buyer = $package->buyer;
        if (! $buyer || $buyer->latitude === null || $buyer->longitude === null) {
            return response()->noContent();
        }

        $courierLat = (float) $request->validated('lat');
        $courierLng = (float) $request->validated('lng');

        $distanceKm = GeoDistance::haversine(
            $courierLat,
            $courierLng,
            (float) $buyer->latitude,
            (float) $buyer->longitude
        );

        $etaMinutes = GeoDistance::etaMinutes($distanceKm);

        return response()->json([
            'distance_km' => $distanceKm,
            'eta_minutes' => $etaMinutes,
        ]);
    }
}
