<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\DTOs\Client\TrackingView;
use App\Enums\PackageStatus;
use App\Models\Package;

final class TrackingViewService
{
    public function __construct(
        private readonly ColdChainSummaryService $coldChainSummaryService,
        private readonly ClientTimelineBuilder $timelineBuilder,
        private readonly ClientLinkService $linkService,
    ) {}

    public function build(Package $package): TrackingView
    {
        $status = $package->status;
        $statusLabel = (string) __("client.status.{$status->value}", [], 'id');
        $statusTone = $status->tone()->value;

        $scheduledAt = $package->scheduled_at?->timezone('Asia/Jakarta');
        $scheduledEndAt = $package->scheduled_end_at?->timezone('Asia/Jakarta');

        if ($scheduledAt !== null) {
            $datePart = $scheduledAt->locale('id')->translatedFormat('l, d F Y');
            $startTime = $scheduledAt->format('H.i');
            $endTime = $scheduledEndAt ? $scheduledEndAt->format('H.i') : $scheduledAt->addHours(2)->format('H.i');
            $estimatedDelivery = "{$datePart}, {$startTime} - {$endTime} WIB";
        } else {
            $estimatedDelivery = 'Belum ditentukan';
        }

        $courier = $package->courier;
        $canContactCourier = ($status === PackageStatus::IN_DELIVERY && $courier !== null);
        $courierPhone = $canContactCourier ? $courier->phone : null;

        // Cold chain card hidden prior to COURIER_ASSIGNED
        $preCourierStatuses = [
            PackageStatus::CREATED,
            PackageStatus::AWAITING_CONFIRMATION,
            PackageStatus::RESCHEDULE_REQUESTED,
            PackageStatus::CONFIRMED,
        ];

        $coldChain = in_array($status, $preCourierStatuses, true)
            ? null
            : $this->coldChainSummaryService->forPackage($package);

        $issueBanner = null;
        if (in_array($status, [PackageStatus::ISSUE_REPORTED, PackageStatus::RETURNED], true)) {
            $issueBanner = [
                'message' => 'Pengiriman terkendala',
                'url' => $this->linkService->issue($package),
            ];
        }

        $rescheduleBanner = null;
        if ($status === PackageStatus::RESCHEDULE_REQUESTED) {
            $rescheduleBanner = 'Permintaan jadwal ulang diterima, admin akan mengusulkan jadwal baru.';
        }

        return new TrackingView(
            trackingNumber: $package->tracking_number,
            status: $status->value,
            statusLabel: $statusLabel,
            statusTone: $statusTone,
            estimatedDelivery: $estimatedDelivery,
            buyerName: $package->buyer->name,
            buyerArea: $package->buyer->area ?? 'Jabodetabek',
            courierName: $courier?->name,
            coolerBoxCode: $package->cooler_box_code,
            coldChain: $coldChain,
            timeline: $this->timelineBuilder->forTracking($package),
            canContactCourier: $canContactCourier,
            courierPhone: $courierPhone,
            issueBanner: $issueBanner,
            rescheduleBanner: $rescheduleBanner,
        );
    }
}
