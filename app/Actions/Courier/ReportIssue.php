<?php

declare(strict_types=1);

namespace App\Actions\Courier;

use App\DTOs\Courier\ReportIssueData;
use App\Enums\PackageStatus;
use App\Exceptions\DeliveryNotActionable;
use App\Models\ConditionCheck;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\Staff;
use App\Support\MediaStorage;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReportIssue
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notifications,
    ) {}

    public function execute(Package $package, Staff $courier, ReportIssueData $data): Package
    {
        try {
            return DB::transaction(function () use ($package, $courier, $data) {
                /** @var Package $locked */
                $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

                if ($locked->courier_id !== $courier->id) {
                    throw DeliveryNotActionable::invalidState('Paket tidak ditugaskan ke kurir ini.');
                }

                $allowedStatuses = [
                    PackageStatus::COURIER_ASSIGNED,
                    PackageStatus::CHECKED,
                    PackageStatus::IN_DELIVERY,
                ];

                if (! in_array($locked->status, $allowedStatuses, true)) {
                    throw DeliveryNotActionable::invalidState('Paket tidak dalam status yang dapat dilaporkan kendala.');
                }

                $openIssue = IssueReport::join('condition_checks', 'condition_checks.id', '=', 'issue_reports.check_id')
                    ->where('condition_checks.package_id', $locked->id)
                    ->whereNull('issue_reports.decision')
                    ->exists();

                if ($openIssue) {
                    throw DeliveryNotActionable::issueAlreadyOpen();
                }

                $now = Carbon::now();

                if ($locked->status === PackageStatus::COURIER_ASSIGNED) {
                    $this->transitioner->transition($locked, PackageStatus::CHECKED, $courier, 'Pemeriksaan menemukan kendala');
                }

                $check = ConditionCheck::create([
                    'package_id' => $locked->id,
                    'courier_id' => $courier->id,
                    'is_normal' => false,
                    'checklist' => $data->checklist,
                    'notes' => $data->issueCategory->label(),
                    'temperature_c' => $data->temperatureC,
                    'checked_at' => $now,
                ]);

                IssueReport::create([
                    'check_id' => $check->id,
                    'issue_category' => $data->issueCategory->value,
                    'description' => $data->description,
                    'photo_url' => $data->photoPath,
                    'reported_at' => $now,
                ]);

                $this->transitioner->transition(
                    $locked,
                    PackageStatus::ISSUE_REPORTED,
                    $courier,
                    "Laporan kendala: {$data->issueCategory->label()}"
                );

                $this->notifications->queueIssueReported($locked, $courier, $data->issueCategory->value);

                // Menu opsi penyelesaian kendala dikirim ke pembeli lewat WhatsApp.
                $this->notifications->queueIssueOptions($locked, $data->issueCategory);

                return $locked;
            });
        } catch (\Throwable $e) {
            MediaStorage::delete($data->photoPath);
            throw $e;
        }
    }
}
