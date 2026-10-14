<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGeofenceRequest;
use App\Models\GeofencePolicy;
use App\Models\Staff;
use App\Services\Gps\GeofenceEvaluator;
use Illuminate\Http\JsonResponse;

/**
 * Pengaturan radius GPS: the radius the hub tolerates per service segment.
 *
 * Editing it changes how strictly the courier app is validated at the
 * destination, so every write records who made it.
 */
final class GeofenceController extends Controller
{
    public function index(GeofenceEvaluator $geofence): JsonResponse
    {
        return response()->json($this->payload($geofence));
    }

    public function update(UpdateGeofenceRequest $request, GeofenceEvaluator $geofence): JsonResponse
    {
        /** @var Staff $admin */
        $admin = $request->user();
        $policies = $request->policies();

        foreach (array_keys(GeofencePolicy::SEGMENTS) as $serviceType) {
            if (! isset($policies[$serviceType])) {
                continue;
            }

            GeofencePolicy::updateOrCreate(
                ['service_type' => $serviceType],
                ['radius_m' => $policies[$serviceType], 'updated_by' => $admin->id],
            );
        }

        // The courier endpoints read the radii through this cache, so drop it
        // instead of waiting out the TTL.
        $geofence->forget();

        return response()->json([
            'message' => 'Radius GPS berhasil diperbarui.',
        ] + $this->payload($geofence));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(GeofenceEvaluator $geofence): array
    {
        $radii = $geofence->radii();
        $segments = [];

        foreach (GeofencePolicy::SEGMENTS as $serviceType => $segment) {
            $segments[] = [
                'service_type' => $serviceType,
                'label' => $segment['label'],
                'sla' => $segment['sla'],
                'description' => $segment['description'],
                'min' => $segment['min'],
                'max' => $segment['max'],
                'step' => $segment['step'],
                'radius_m' => $radii[$serviceType] ?? GeofencePolicy::DEFAULT_RADIUS_M[$serviceType],
                'default_m' => GeofencePolicy::DEFAULT_RADIUS_M[$serviceType],
            ];
        }

        $updatedAt = GeofencePolicy::query()->max('updated_at');

        return [
            'segments' => $segments,
            'updated_at' => $updatedAt !== null ? (string) $updatedAt : null,
        ];
    }
}
