<?php

declare(strict_types=1);

namespace App\Actions\Packages;

use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Exceptions\PackageNotAssignable;
use App\Models\Package;
use App\Models\Staff;
use App\Support\DisplayTime;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Illuminate\Support\Facades\DB;

final class AssignCourier
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notificationQueue,
    ) {}

    public function execute(Package $package, Staff $courier, Staff $admin): Package
    {
        return DB::transaction(function () use ($package, $courier, $admin): Package {
            // Pessimistic locking to prevent race conditions
            /** @var Package $lockedPackage */
            $lockedPackage = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            if ($lockedPackage->status !== PackageStatus::CONFIRMED || $lockedPackage->courier_id !== null) {
                throw new PackageNotAssignable;
            }

            if (! $courier->isCourier()) {
                throw new PackageNotAssignable('Staf yang dipilih bukan kurir aktif.');
            }

            $lockedPackage = $this->transitioner->transition(
                package: $lockedPackage,
                to: PackageStatus::COURIER_ASSIGNED,
                actor: $admin,
                attributes: [
                    'courier_id' => $courier->id,
                    'courier_accepted_at' => null,
                ]
            );

            // Queue task_assigned notification for courier
            $lockedPackage->loadMissing('items.product');
            $itemSummaries = $lockedPackage->items->map(fn ($item) => [
                'name' => $item->product?->name ?? 'Produk',
                'quantity' => $item->quantity,
            ])->toArray();

            $windowLabel = DisplayTime::windowLabel($lockedPackage->scheduled_at, $lockedPackage->scheduled_end_at);

            $this->notificationQueue->queue(
                package: $lockedPackage,
                type: RecipientType::COURIER,
                recipientId: $courier->id,
                template: 'task_assigned',
                payload: [
                    'tracking_number' => $lockedPackage->tracking_number,
                    'scheduled_window' => $windowLabel,
                    'handling_instructions' => $lockedPackage->handling_instructions,
                    'items' => $itemSummaries,
                ]
            );

            return $lockedPackage;
        });
    }
}
