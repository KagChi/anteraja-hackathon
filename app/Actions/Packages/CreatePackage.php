<?php

declare(strict_types=1);

namespace App\Actions\Packages;

use App\Enums\AddressType;
use App\Enums\ConfirmationResponse;
use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Enums\ServiceType;
use App\Models\Buyer;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use App\Support\DisplayTime;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use App\Support\PhoneFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CreatePackage
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notificationQueue,
    ) {}

    /**
     * @param array{
     *     buyer_id?: ?int,
     *     buyer?: ?array{name: string, phone: string, address: string, area?: ?string},
     *     recipient?: ?array{name: string, phone: string, address: string, area?: ?string, address_type?: ?string, latitude?: ?float, longitude?: ?float},
     *     items: list<array{product_id: int, quantity: int}>,
     *     scheduled_date: string,
     *     window_start: string,
     *     window_end: string,
     *     weight_kg: float,
     *     cooler_box_code?: ?string,
     *     package_category?: ?string,
     *     handling_instructions?: ?string
     * } $data
     */
    public function execute(array $data, Staff $admin): Package
    {
        return DB::transaction(function () use ($data, $admin): Package {
            // 1. Resolve Buyer
            $buyer = null;
            if (! empty($data['buyer_id'])) {
                $buyerId = (int) $data['buyer_id'];
                $buyer = Buyer::find($buyerId);
            } else {
                $buyerData = $data['buyer'] ?? [];
                $phone = $buyerData['phone'] ?? '';
                $normalizedPhone = PhoneFormatter::toE164Id($phone);

                $buyer = Buyer::where('phone', $normalizedPhone)
                    ->orWhere('phone', $phone)
                    ->first();

                if (! $buyer) {
                    $buyer = Buyer::create([
                        'name' => $buyerData['name'],
                        'phone' => $normalizedPhone,
                        'address' => $buyerData['address'],
                        'area' => $buyerData['area'] ?? null,
                        'address_type' => $buyerData['address_type'] ?? 'RESIDENTIAL',
                        'latitude' => isset($buyerData['latitude']) ? (float) $buyerData['latitude'] : null,
                        'longitude' => isset($buyerData['longitude']) ? (float) $buyerData['longitude'] : null,
                    ]);
                }
                $buyerId = $buyer->id;
            }

            // 2. Resolve the destination. A hub shipment that names no
            //    recipient is a shipment to the buyer themselves, which is how
            //    every counter parcel worked before the recipient block.
            $recipient = is_array($data['recipient'] ?? null) ? $data['recipient'] : [];
            $namesRecipient = $recipient !== [];

            $recipientBlock = [
                'recipient_name' => $recipient['name'] ?? $buyer?->name,
                'recipient_phone' => $recipient['phone'] ?? $buyer?->phone,
                'recipient_address' => $recipient['address'] ?? $buyer?->address,
                'recipient_area' => $recipient['area'] ?? $buyer?->area,
                'recipient_address_type' => isset($recipient['address_type'])
                    ? (AddressType::fromInput((string) $recipient['address_type']) ?? AddressType::RESIDENTIAL)
                    : ($buyer?->address_type ?? AddressType::RESIDENTIAL),
                // Coordinates are only inherited when the parcel really is a
                // shipment to the buyer. A named recipient without coordinates
                // keeps none, so the geofence stays silent instead of measuring
                // the courier against the sender's own house.
                'recipient_latitude' => isset($recipient['latitude'])
                    ? (float) $recipient['latitude']
                    : ($namesRecipient ? null : $buyer?->latitude),
                'recipient_longitude' => isset($recipient['longitude'])
                    ? (float) $recipient['longitude']
                    : ($namesRecipient ? null : $buyer?->longitude),
            ];

            // 3. Parse schedule window in Jakarta timezone, convert to UTC
            $dateStr = $data['scheduled_date'];
            $startJkt = Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$data['window_start']}", DisplayTime::DISPLAY_TIMEZONE);
            $endJkt = Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$data['window_end']}", DisplayTime::DISPLAY_TIMEZONE);

            $scheduledAtUtc = $startJkt !== false ? $startJkt->setTimezone('UTC') : Carbon::now('UTC');
            $scheduledEndAtUtc = $endJkt !== false ? $endJkt->setTimezone('UTC') : Carbon::now('UTC')->addHours(2);

            // 4. Create Package (Status: CREATED)
            $serviceType = isset($data['service_type']) ? ServiceType::tryFrom($data['service_type']) ?? ServiceType::REGULER : ServiceType::REGULER;
            $koliCount = isset($data['koli_count']) ? max(1, (int) $data['koli_count']) : 1;

            $package = Package::create([
                ...$recipientBlock,
                'buyer_id' => $buyerId,
                'created_by' => $admin->id,
                'courier_id' => null,
                'status' => PackageStatus::CREATED,
                'cooler_box_code' => $data['cooler_box_code'] ?? null,
                'package_category' => $data['package_category'] ?? 'Frozen Food',
                'service_type' => $serviceType,
                'koli_count' => $koliCount,
                'weight_kg' => (float) $data['weight_kg'],
                'scheduled_at' => $scheduledAtUtc,
                'scheduled_end_at' => $scheduledEndAtUtc,
                'handling_instructions' => $data['handling_instructions'] ?? null,
            ]);

            // Refresh to obtain tracking_number generated by PostgreSQL sequence default
            $package->refresh();

            // 4. Create Package Items
            foreach ($data['items'] as $item) {
                PackageItem::create([
                    'package_id' => $package->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ]);
            }

            // 5. Create Schedule Confirmation (PENDING)
            ScheduleConfirmation::create([
                'package_id' => $package->id,
                'response' => ConfirmationResponse::PENDING,
                'proposed_scheduled_at' => $scheduledAtUtc,
                'requested_at' => Carbon::now('UTC'),
            ]);

            // 6. Transition to AWAITING_CONFIRMATION
            $package = $this->transitioner->transition(
                package: $package,
                to: PackageStatus::AWAITING_CONFIRMATION,
                actor: $admin
            );

            // 7. Queue notification to buyer
            $this->notificationQueue->queue(
                package: $package,
                type: RecipientType::BUYER,
                recipientId: $package->buyer_id,
                template: 'schedule_request',
                payload: [
                    'tracking_number' => $package->tracking_number,
                    'scheduled_at' => $package->scheduled_at?->toIso8601String(),
                    'scheduled_end_at' => $package->scheduled_end_at?->toIso8601String(),
                ]
            );

            return $package;
        });
    }
}
