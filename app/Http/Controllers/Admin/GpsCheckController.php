<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\GpsLevel;
use App\Enums\GpsLockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GpsDecisionRequest;
use App\Models\GpsLockRequest;
use App\Models\LocationCheck;
use App\Models\Staff;
use App\Services\Gps\GeofenceEvaluator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Validasi GPS: the hub's view of the courier app's location evidence.
 *
 * Two things land here: every check the server refused or flagged, and the
 * review requests couriers raise from the field. Approving a request is what
 * unlocks that action for that shipment while the incident stays on record.
 */
final class GpsCheckController extends Controller
{
    public function index(Request $request, GeofenceEvaluator $geofence): JsonResponse
    {
        $tab = $request->query('status') === 'resolved' ? 'resolved' : 'pending';
        $level = (string) $request->query('level', '');

        $checks = LocationCheck::query()
            ->with(['package.buyer', 'courier', 'override'])
            ->where(function (Builder $query): void {
                $query->where('level', '<>', GpsLevel::CLEAN->value)
                    ->orWhere('inside_geofence', false);
            })
            ->when(
                in_array($level, ['BLOCKED', 'SUSPECTED'], true),
                fn (Builder $query): Builder => $query->where('level', $level),
            )
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (LocationCheck $check): array => [
                'id' => $check->id,
                'tracking' => $check->package?->tracking_number ?? '-',
                'buyer' => $check->package?->buyer?->name ?? '-',
                'courier' => $check->courier?->name ?? '-',
                'action' => $check->action?->value,
                'action_label' => $check->action?->label(),
                'level' => $check->level?->value,
                'level_label' => $check->level?->label(),
                'tone' => $this->toneFor($check),
                'reasons' => $check->reasons ?? [],
                'distance_m' => $check->distance_m,
                'radius_m' => $check->radius_m,
                'inside' => $check->inside_geofence,
                'accuracy_m' => $check->accuracy_m,
                'applied' => $check->applied,
                'override_status' => $check->override?->status?->value,
                'checked_at' => $check->created_at?->toIso8601String(),
            ])
            ->values();

        $locks = GpsLockRequest::query()
            ->with(['package.buyer', 'courier', 'decider'])
            ->when(
                $tab === 'pending',
                fn (Builder $query): Builder => $query->where('status', GpsLockStatus::PENDING),
                fn (Builder $query): Builder => $query->whereIn('status', [GpsLockStatus::APPROVED, GpsLockStatus::REJECTED]),
            )
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (GpsLockRequest $lock): array => [
                'id' => $lock->id,
                'tracking' => $lock->package?->tracking_number ?? '-',
                'buyer' => $lock->package?->buyer?->name ?? '-',
                'courier' => $lock->courier?->name ?? '-',
                'action' => $lock->action?->value,
                'action_label' => $lock->action?->label(),
                'status' => $lock->status?->value,
                'status_label' => $lock->status?->label(),
                'tone' => $lock->status?->tone()->value,
                'reasons' => $lock->reasons ?? [],
                'decision_reason' => $lock->decision_reason,
                'decided_by' => $lock->decider?->name,
                'decided_at' => $lock->decided_at?->toIso8601String(),
                'created_at' => $lock->created_at?->toIso8601String(),
            ])
            ->values();

        $todayStart = Carbon::now('UTC')->startOfDay();

        return response()->json([
            'tab' => $tab,
            'checks' => $checks,
            'locks' => $locks,
            'summary' => [
                'pending_requests' => GpsLockRequest::query()->where('status', GpsLockStatus::PENDING)->count(),
                'blocked_today' => LocationCheck::query()->where('level', GpsLevel::BLOCKED->value)->where('created_at', '>=', $todayStart)->count(),
                'suspect_today' => LocationCheck::query()->where('level', GpsLevel::SUSPECTED->value)->where('created_at', '>=', $todayStart)->count(),
                'outside_radius_today' => LocationCheck::query()->where('inside_geofence', false)->where('created_at', '>=', $todayStart)->count(),
            ],
            'radius' => $geofence->radii(),
        ]);
    }

    public function decide(GpsDecisionRequest $request, int $id): JsonResponse
    {
        $lock = GpsLockRequest::with('package')->findOrFail($id);

        if ($lock->status !== GpsLockStatus::PENDING) {
            return response()->json([
                'message' => 'Permintaan tinjauan ini sudah diputuskan sebelumnya.',
                'code' => 'GPS_LOCK_ALREADY_DECIDED',
            ], 422);
        }

        /** @var Staff $admin */
        $admin = $request->user();
        $decision = GpsLockStatus::from((string) $request->validated('decision'));

        $lock->status = $decision;
        $lock->decided_by = $admin->id;
        $lock->decision_reason = (string) $request->validated('decision_reason');
        $lock->decided_at = Carbon::now('UTC');
        $lock->save();

        return response()->json([
            'message' => $decision === GpsLockStatus::APPROVED
                ? "Tinjauan GPS untuk {$lock->package?->tracking_number} disetujui."
                : "Tinjauan GPS untuk {$lock->package?->tracking_number} ditolak.",
            'lock' => [
                'id' => $lock->id,
                'status' => $lock->status->value,
                'status_label' => $lock->status->label(),
                'decision_reason' => $lock->decision_reason,
                'decided_at' => $lock->decided_at?->toIso8601String(),
            ],
        ]);
    }

    private function toneFor(LocationCheck $check): string
    {
        if ($check->inside_geofence === false && $check->level === GpsLevel::CLEAN) {
            return 'danger';
        }

        return $check->level?->tone()->value ?? 'neutral';
    }
}
