<?php

declare(strict_types=1);

namespace App\Actions\Courier;

use App\DTOs\Courier\ConfirmDeliveryData;
use App\Enums\DeliveryMilestoneEnum;
use App\Enums\PackageStatus;
use App\Exceptions\DeliveryNotActionable;
use App\Models\Delivery;
use App\Models\DeliveryMilestone;
use App\Models\Package;
use App\Models\Staff;
use App\Support\MediaStorage;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ConfirmDelivery {
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notifications,
    ) {
    }

    public function execute(Package $package, Staff $courier, ConfirmDeliveryData $data): Package {
        try {
            return DB::transaction(function () use ($package, $courier, $data) {
                /** @var Package $locked */
                $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

                if ($locked->courier_id !== $courier->id) {
                    throw DeliveryNotActionable::invalidState('Paket tidak ditugaskan ke kurir ini.');
                }

                if ($locked->status !== PackageStatus::IN_DELIVERY) {
                    if ($locked->status === PackageStatus::RECEIVED) {
                        MediaStorage::delete($data->photoPath);

                        return $locked;
                    }
                    throw DeliveryNotActionable::invalidState('Paket tidak dalam status pengantaran aktif.');
                }

                $existingDelivery = Delivery::where('package_id', $locked->id)->first();
                if ($existingDelivery !== null) {
                    MediaStorage::delete($data->photoPath);

                    return $locked;
                }

                // The hand-over code the recipient read out. A missing hash
                // means no PIN was ever sent; a mismatch is a wrong code — both
                // refuse the hand-over. The photo is cleaned up by the catch.
                if ($locked->delivery_pin_hash === null) {
                    throw DeliveryNotActionable::invalidState('PIN pengiriman belum tersedia untuk paket ini.');
                }

                if (! Hash::check($data->pin, $locked->delivery_pin_hash)) {
                    $locked->increment('delivery_pin_attempts');

                    throw DeliveryNotActionable::invalidState('PIN pengiriman salah. Minta kode terbaru dari penerima.');
                }

                $now = Carbon::now();

                Delivery::create([
                    'package_id' => $locked->id,
                    'received_by' => $data->receivedBy,
                    'recipient_relation' => $data->recipientRelation->value,
                    'photo_url' => $data->photoPath,
                    'receipt_note' => $data->receiptNote,
                    'delivered_at' => $now,
                ]);

                $latestMilestone = DeliveryMilestone::where('package_id', $locked->id)->orderBy('id', 'desc')->first();
                if ($latestMilestone?->milestone !== DeliveryMilestoneEnum::ARRIVED) {
                    DeliveryMilestone::create([
                        'package_id' => $locked->id,
                        'milestone' => DeliveryMilestoneEnum::ARRIVED,
                        'courier_id' => $courier->id,
                        'occurred_at' => $now,
                    ]);
                }

                $this->transitioner->transition($locked, PackageStatus::RECEIVED, $courier, "Diserahkan ke {$data->receivedBy} ({$data->recipientRelation->label()})");

                // The code is single-use: once the parcel is handed over the
                // hash is gone and cannot be replayed.
                $locked->update([
                    'delivery_pin_hash' => null,
                    'delivery_pin_attempts' => 0,
                ]);

                $this->notifications->queueDelivered($locked, $data->receivedBy, $now);

                return $locked;
            });
        } catch (\Throwable $e) {
            MediaStorage::delete($data->photoPath);
            throw $e;
        }
    }
}
