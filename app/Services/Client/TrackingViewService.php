<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\DTOs\Client\TrackingView;
use App\Enums\PackageStatus;
use App\Models\Package;

final class TrackingViewService {
    public function __construct(
        private readonly ColdChainSummaryService $coldChainSummaryService,
        private readonly ClientTimelineBuilder $timelineBuilder,
        private readonly ClientLinkService $linkService,
    ) {
    }

    public function build(Package $package): TrackingView {
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

        $firstItem = $package->items->first();
        $productName = $firstItem?->product?->name ?? 'Paket Frozen';
        $itemCount = $package->items->count() ?: 1;
        $weightGrams = (int) round(((float) ($package->weight_kg ?? 1.0)) * 1000);
        $weightFormatted = number_format($weightGrams, 0, ',', '.');
        $productMeta = "{$itemCount} Paket • Berat {$weightFormatted}g";

        return new TrackingView(
            trackingNumber: $package->tracking_number,
            status: $status->value,
            statusLabel: $statusLabel,
            statusTone: $statusTone,
            estimatedDelivery: $estimatedDelivery,
            recipientName: $package->recipient_name ?? 'Penerima',
            recipientArea: $package->recipient_area ?? '',
            courierName: $courier?->name,
            coolerBoxCode: $package->cooler_box_code,
            coldChain: $coldChain,
            timeline: $this->timelineBuilder->forTracking($package),
            canContactCourier: $canContactCourier,
            courierPhone: $courierPhone,
            issueBanner: $issueBanner,
            productName: $productName,
            productMeta: $productMeta,
        );
    }
}
