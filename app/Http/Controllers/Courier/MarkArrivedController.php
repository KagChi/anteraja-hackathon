<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Courier\MarkArrived;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MarkArrivedController extends Controller
{
    public function __invoke(Request $request, string $trackingNumber, MarkArrived $action): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        $lat = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $lng = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        $arrived = $action->execute($package, $courier, $lat, $lng);

        return response()->json([
            'message' => 'Status berhasil diperbarui: Tiba di lokasi.',
            'package' => $arrived->fresh(['statusRef', 'latestMilestone']),
        ]);
    }
}
