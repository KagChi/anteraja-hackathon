<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\GpsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The courier is outside the radius the hub tolerates for this service type, so
 * proof of delivery is refused until the hub approves the exception.
 */
final class OutsideGeofence extends RuntimeException
{
    public function __construct(
        public readonly int $distanceM,
        public readonly int $radiusM,
        public readonly GpsAction $action,
        public readonly ?int $lockRequestId = null,
    ) {
        parent::__construct('Anda berada di luar radius titik pengantaran. Ajukan tinjauan ke hub.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'OUTSIDE_GEOFENCE',
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'distance_m' => $this->distanceM,
            'radius_m' => $this->radiusM,
            'lock_request_id' => $this->lockRequestId,
        ], 422);
    }
}
