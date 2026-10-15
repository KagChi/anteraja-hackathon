<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Enums\DeliveryStage;
use App\Enums\GpsAction;
use App\Enums\GpsLevel;
use App\Enums\GpsLockStatus;
use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Models\GpsLockRequest;
use App\Models\LocationCheck;
use App\Models\Package;
use App\Services\Gps\GeofenceEvaluator;
use App\Services\Gps\GpsValidator;
use App\Support\PhoneFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DeliveryController extends Controller
{
    public function show(
        Request $request,
        string $trackingNumber,
        GpsValidator $gps,
        GeofenceEvaluator $geofence,
    ): JsonResponse {
        $package = Package::where('tracking_number', $trackingNumber)
            ->with(['statusRef', 'latestMilestone', 'conditionChecks.issueReport'])
            ->firstOrFail();

        Gate::authorize('view', $package);

        $allowedStatuses = [
            PackageStatus::COURIER_ASSIGNED,
            PackageStatus::CHECKED,
            PackageStatus::IN_DELIVERY,
            PackageStatus::ISSUE_REPORTED,
            PackageStatus::RECEIVED,
            PackageStatus::RETURNED,
            PackageStatus::CLOSED,
        ];

        if (! in_array($package->status, $allowedStatuses, true)) {
            abort(404, 'Paket tidak ditemukan.');
        }

        if ($package->status === PackageStatus::COURIER_ASSIGNED && $package->courier_accepted_at === null) {
            return response()->json([
                'message' => 'Tugas belum diterima. Silakan terima tugas terlebih dahulu.',
                'redirect' => '/courier/tasks',
            ], 422);
        }

        // The courier drives to the recipient the customer chose, not to the
        // customer's own address.
        $recipient = $package->recipient;
        $address = $recipient['address'] ?? '';
        $phone = $recipient['phone'] ?? '';
        $phoneE164 = $phone !== '' ? PhoneFormatter::toE164Id($phone) : '';

        if ($recipient['latitude'] !== null && $recipient['longitude'] !== null) {
            $mapHref = "https://www.google.com/maps/search/?api=1&query={$recipient['latitude']},{$recipient['longitude']}";
        } else {
            $mapHref = 'https://www.google.com/maps/search/?api=1&query='.urlencode($address);
        }

        $stage = $package->deliveryStage();
        $hasOpenIssue = $package->conditionChecks
            ->pluck('issueReport')
            ->filter()
            ->whereNull('decision')
            ->isNotEmpty();

        $canDepart = in_array($package->status, [PackageStatus::COURIER_ASSIGNED, PackageStatus::CHECKED], true);
        $canArrive = $package->status === PackageStatus::IN_DELIVERY && $stage !== DeliveryStage::ARRIVED;
        $canProof = $package->status === PackageStatus::IN_DELIVERY;
        $canReportIssue = in_array($package->status, [PackageStatus::COURIER_ASSIGNED, PackageStatus::CHECKED, PackageStatus::IN_DELIVERY], true) && ! $hasOpenIssue;

        return response()->json([
            'delivery' => [
                'tracking' => $package->tracking_number,
                'service' => 'Paket Beku',
                'serviceType' => $package->service_type?->label() ?? 'Reguler',
                'recipient' => $recipient['name'] ?? '-',
                'phone' => $phone,
                'phoneHref' => "tel:{$phoneE164}",
                'address' => $address,
                'instruction' => $package->handling_instructions,
                'mapLabel' => $address,
                'mapHref' => $mapHref,
                'status' => $package->status->value,
                'statusLabel' => $package->statusRef?->label ?? $package->status->value,
                'stage' => $stage->value,
                'stageLabel' => $stage->label(),
                'canDepart' => $canDepart,
                'canArrive' => $canArrive,
                'canProof' => $canProof,
                'canReportIssue' => $canReportIssue,
                'hasOpenIssue' => $hasOpenIssue,
                'checklistItems' => config('coldchain.checklist_items', []),
                'gps' => $this->gpsContext($package, $gps, $geofence),
            ],
        ]);
    }

    /**
     * What the courier screen shows about location validation: where the
     * destination is (the map link already carries these coordinates), the
     * radius for this service, the last verdict, and any open review request.
     *
     * @return array<string, mixed>
     */
    private function gpsContext(Package $package, GpsValidator $gps, GeofenceEvaluator $geofence): array
    {
        $last = $this->latestCheck($package, $gps);
        $pending = GpsLockRequest::query()
            ->where('package_id', $package->id)
            ->where('status', GpsLockStatus::PENDING)
            ->orderByDesc('id')
            ->first();
        $approved = GpsLockRequest::query()
            ->where('package_id', $package->id)
            ->where('status', GpsLockStatus::APPROVED)
            ->orderByDesc('id')
            ->first();

        $flagged = $last !== null
            && ($last->level !== GpsLevel::CLEAN || $last->inside_geofence === false);

        return [
            'destination' => [
                'latitude' => $package->recipient_latitude,
                'longitude' => $package->recipient_longitude,
            ],
            'radius_m' => $geofence->radiusFor($package->service_type),
            'distance_m' => $last?->distance_m,
            'inside' => $last?->inside_geofence,
            'level' => $last?->level?->value,
            'level_label' => $last?->level?->label(),
            'level_tone' => $last?->level?->tone()->value,
            'accuracy_m' => $last?->accuracy_m,
            'action' => $last?->action?->value,
            'action_label' => $last?->action?->label(),
            'reasons' => $last?->reasons ?? [],
            'checked_at' => $last?->created_at?->toIso8601String(),
            'can_request_review' => $flagged && $pending === null && $approved === null,
            'requested_action' => $pending?->action?->value,
            'lock' => $pending === null ? null : [
                'id' => $pending->id,
                'status' => $pending->status?->value,
                'status_label' => $pending->status?->label(),
                'created_at' => $pending->created_at?->toIso8601String(),
            ],
        ];
    }

    private function latestCheck(Package $package, GpsValidator $gps): ?LocationCheck
    {
        foreach ([GpsAction::POD, GpsAction::ARRIVE, GpsAction::DEPART] as $action) {
            $check = $gps->latestCheck($package, $action);
            if ($check !== null) {
                return $check;
            }
        }

        return null;
    }
}
