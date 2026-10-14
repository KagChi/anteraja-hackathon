<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Enums\GpsAction;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Staff;
use App\Services\Gps\GpsValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Facades\Gate;

/**
 * A courier asking the hub to review the GPS verdict that stopped them.
 *
 * This is the escape hatch from the FRD-06 block: the incident stays flagged,
 * but once the hub approves the request the courier can retry the action.
 */
final class GpsReviewController extends Controller
{
    public function __invoke(Request $request, string $trackingNumber, GpsValidator $gps): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        $validated = $request->validate([
            'action' => ['required', new Enum(GpsAction::class)],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $action = GpsAction::from((string) $validated['action']);
        $lock = $gps->requestReview($package, $courier, $action);

        return response()->json([
            'message' => 'Permintaan tinjauan GPS dikirim ke hub.',
            'data' => [
                'id' => $lock->id,
                'action' => $lock->action?->value,
                'action_label' => $lock->action?->label(),
                'status' => $lock->status?->value,
                'status_label' => $lock->status?->label(),
                'reasons' => $lock->reasons ?? [],
                'created_at' => $lock->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
