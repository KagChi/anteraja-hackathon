<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Enums\DeliveryStage;
use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Support\PhoneFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DeliveryController extends Controller
{
    public function show(Request $request, string $trackingNumber): JsonResponse
    {
        $package = Package::where('tracking_number', $trackingNumber)
            ->with(['buyer', 'statusRef', 'latestMilestone', 'conditionChecks.issueReport'])
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

        $buyer = $package->buyer;
        $address = $buyer?->address ?? '';
        $phone = $buyer?->phone ?? '';
        $phoneE164 = $phone !== '' ? PhoneFormatter::toE164Id($phone) : '';

        if ($buyer && $buyer->latitude !== null && $buyer->longitude !== null) {
            $mapHref = "https://www.google.com/maps/search/?api=1&query={$buyer->latitude},{$buyer->longitude}";
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
                'recipient' => $buyer?->name ?? '-',
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
            ],
        ]);
    }
}
