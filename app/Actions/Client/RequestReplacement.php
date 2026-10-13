<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Exceptions\ReplacementNotAllowed;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Staff;
use App\Services\Client\ReplacementEligibility;
use App\Support\NotificationQueue;
use App\Support\ReplacementPackageFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class RequestReplacement
{
    public function __construct(
        private readonly ReplacementEligibility $eligibility,
        private readonly ReplacementPackageFactory $packageFactory,
        private readonly NotificationQueue $notificationQueue,
    ) {}

    public function execute(Package $package): Package
    {
        return DB::transaction(function () use ($package) {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            $check = $this->eligibility->check($locked);

            // If already created, silent success
            if ($check->childPackage !== null) {
                return $check->childPackage;
            }

            if (! $check->isEligible) {
                throw new ReplacementNotAllowed($check->reason ?? 'Pengajuan pengiriman ulang tidak memenuhi syarat.');
            }

            $autoApprove = (bool) config('coldchain.client.resend_auto_approve', true);

            if ($autoApprove) {
                try {
                    return $this->packageFactory->from($locked, null);
                } catch (QueryException $e) {
                    // Catch unique partial index race condition uq_packages_one_replacement
                    if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uq_packages_one_replacement')) {
                        return Package::where('resend_of_package_id', $locked->id)->firstOrFail();
                    }

                    throw $e;
                }
            }

            // If manual approval: queue notification to admin once (deduped)
            $admins = Staff::where('role', StaffRole::ADMIN)->where('is_active', true)->get();
            foreach ($admins as $admin) {
                $alreadyQueued = Notification::where('package_id', $locked->id)
                    ->where('recipient_type', RecipientType::ADMIN)
                    ->where('template', 'resend_requested')
                    ->exists();

                if (! $alreadyQueued) {
                    $this->notificationQueue->queue(
                        $locked,
                        RecipientType::ADMIN,
                        $admin->id,
                        'resend_requested',
                        [
                            'original_tracking_number' => $locked->tracking_number,
                            'status' => 'PENDING_APPROVAL',
                        ]
                    );
                }
            }

            return $locked;
        });
    }
}
