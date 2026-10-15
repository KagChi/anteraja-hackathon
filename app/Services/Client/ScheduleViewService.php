<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\DTOs\Client\CargoLine;
use App\DTOs\Client\ScheduleView;
use App\Enums\PackageStatus;
use App\Enums\ServiceType;
use App\Models\Package;

final class ScheduleViewService
{
    public function build(Package $package): ScheduleView
    {
        $status = $package->status;
        $statusLabel = (string) __("client.status.{$status->value}", [], 'id');

        $scheduledAt = $package->scheduled_at?->timezone('Asia/Jakarta');
        $scheduledEndAt = $package->scheduled_end_at?->timezone('Asia/Jakarta');

        if ($scheduledAt !== null) {
            $datePart = $scheduledAt->locale('id')->translatedFormat('l, d F Y');
            $startTime = $scheduledAt->format('H.i');
            $endTime = $scheduledEndAt ? $scheduledEndAt->format('H.i') : $scheduledAt->addHours(2)->format('H.i');
            $scheduledDelivery = "{$datePart}, {$startTime} - {$endTime} WIB";
        } else {
            $scheduledDelivery = 'Belum ditentukan';
        }

        $cargoItems = [];
        $handlingNotes = [];

        foreach ($package->items as $item) {
            $cargoItems[] = new CargoLine(
                category: (string) __('client.cargo.category_frozen', [], 'id'),
                name: $item->product->name,
                quantityLabel: "{$item->quantity} Paket",
            );

            if (! empty($item->product->handling_notes)) {
                $handlingNotes[] = $item->product->handling_notes;
            }
        }

        $handlingNotes = array_unique(array_filter($handlingNotes));
        $handlingNoteText = ! empty($handlingNotes)
            ? implode('; ', $handlingNotes)
            : (string) __('client.cargo.default_note', [], 'id');

        $courier = $package->courier;
        $courierBadge = $courier !== null ? (string) __('client.cargo.courier_badge', [], 'id') : null;
        $hasColdPriority = ($package->service_type === ServiceType::INSTANT_FROZEN);

        $hasPendingConfirmation = $package->scheduleConfirmations()
            ->where('response', 'PENDING')
            ->exists();

        $canAction = ($status === PackageStatus::AWAITING_CONFIRMATION && $hasPendingConfirmation);

        /** @var list<string> $slots */
        $slots = config('coldchain.client.slots', [
            '09:00-11:00',
            '11:00-13:00',
            '13:00-15:00',
            '15:00-17:00',
            '17:00-19:00',
        ]);

        return new ScheduleView(
            trackingNumber: $package->tracking_number,
            status: $status->value,
            statusLabel: $statusLabel,
            recipientName: $package->recipient_name ?? '',
            recipientAddress: $package->recipient_address ?? '',
            cargoItems: $cargoItems,
            totalWeightKg: (float) $package->weight_kg,
            coldGuaranteedBadge: (string) __('client.cargo.guaranteed_cold', [], 'id'),
            handlingNote: $handlingNoteText,
            scheduledDelivery: $scheduledDelivery,
            hasColdPriority: $hasColdPriority,
            courierName: $courier?->name,
            courierBadge: $courierBadge,
            canAction: $canAction,
            availableSlots: $slots,
        );
    }
}
