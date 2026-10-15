<?php

declare(strict_types=1);

namespace App\Actions\Courier;

use App\DTOs\Courier\StartDeliveryData;
use App\Enums\ConfirmationResponse;
use App\Enums\DeliveryMilestoneEnum;
use App\Enums\PackageStatus;
use App\Exceptions\DeliveryNotActionable;
use App\Models\ConditionCheck;
use App\Models\DeliveryMilestone;
use App\Models\Package;
use App\Models\Staff;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class StartDelivery {
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notifications,
    ) {
    }

    public function execute(Package $package, Staff $courier, StartDeliveryData $data): Package {
        return DB::transaction(function () use ($package, $courier, $data) {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            if ($locked->courier_id !== $courier->id) {
                throw DeliveryNotActionable::invalidState('Paket tidak ditugaskan ke kurir ini.');
            }

            if (! in_array($locked->status, [PackageStatus::COURIER_ASSIGNED, PackageStatus::CONFIRMED, PackageStatus::CHECKED], true)) {
                throw DeliveryNotActionable::invalidState('Paket tidak dalam status siap berangkat.');
            }

            // A parcel still in the courier's hands waits for the recipient's
            // confirmation; CONFIRMED — or a re-assigned parcel whose
            // confirmation is already READY — may leave immediately.
            if ($locked->status === PackageStatus::COURIER_ASSIGNED) {
                $confirmed = $locked->scheduleConfirmations()
                    ->where('response', ConfirmationResponse::READY->value)
                    ->exists();

                if (! $confirmed) {
                    throw DeliveryNotActionable::invalidState('Jadwal belum dikonfirmasi penerima. Kirim permintaan konfirmasi terlebih dahulu.');
                }
            }

            foreach ($data->checklist as $item) {
                if ($item['answer'] === true) {
                    throw DeliveryNotActionable::invalidState('Pemeriksaan fisik menemukan kendala. Silakan buat laporan kendala.');
                }
            }

            ConditionCheck::create([
                'package_id' => $locked->id,
                'courier_id' => $courier->id,
                'is_normal' => true,
                'checklist' => $data->checklist,
                'notes' => 'Pemeriksaan sebelum berangkat normal',
                'temperature_c' => $data->temperatureC,
                'checked_at' => Carbon::now(),
            ]);

            if (in_array($locked->status, [PackageStatus::COURIER_ASSIGNED, PackageStatus::CONFIRMED], true)) {
                $this->transitioner->transition($locked, PackageStatus::CHECKED, $courier, 'Pemeriksaan pra-pengantaran selesai');
            }

            $this->transitioner->transition($locked, PackageStatus::IN_DELIVERY, $courier, 'Kurir berangkat mengantar paket');

            // The recipient gets a hand-over code now, a little before the
            // courier reaches the door. The plain digits travel to the recipient
            // and nowhere else; the parcel keeps only the hash.
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $locked->update([
                'delivery_pin_hash' => Hash::make($pin),
                'delivery_pin_sent_at' => Carbon::now('UTC'),
                'delivery_pin_attempts' => 0,
            ]);

            DeliveryMilestone::create([
                'package_id' => $locked->id,
                'milestone' => DeliveryMilestoneEnum::DEPARTED,
                'courier_id' => $courier->id,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'occurred_at' => Carbon::now(),
            ]);

            $this->notifications->queueOnTheWay($locked, $courier);
            $this->notifications->queueDeliveryPin($locked, $pin);

            return $locked;
        });
    }
}
