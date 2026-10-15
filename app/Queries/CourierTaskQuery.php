<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\PackageStatus;
use App\Enums\ScheduleTiming;
use App\Models\Package;
use App\Models\Staff;
use App\Support\DisplayTime;
use App\Support\RelativeTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class CourierTaskQuery {
    /**
     * @return array{
     *     newTasks: Collection<int, array<string, mixed>>,
     *     activeTasks: Collection<int, array<string, mixed>>
     * }
     */
    public function getTasksForCourier(Staff $courier): array {
        $allowedStatuses = [
            PackageStatus::COURIER_ASSIGNED->value,
            PackageStatus::AWAITING_CONFIRMATION->value,
            PackageStatus::CONFIRMED->value,
            PackageStatus::CHECKED->value,
            PackageStatus::IN_DELIVERY->value,
            PackageStatus::ISSUE_REPORTED->value,
        ];

        $packages = Package::query()
            ->where('courier_id', $courier->id)
            ->whereIn('status', $allowedStatuses)
            ->select('packages.*')
            ->selectRaw('
                (SELECT id FROM status_logs 
                 WHERE package_id = packages.id AND to_status = \'COURIER_ASSIGNED\' 
                 ORDER BY changed_at DESC, id DESC LIMIT 1) AS assigned_log_id
            ')
            ->selectRaw('
                (SELECT changed_at FROM status_logs 
                 WHERE package_id = packages.id AND to_status = \'COURIER_ASSIGNED\' 
                 ORDER BY changed_at DESC, id DESC LIMIT 1) AS assigned_at
            ')
            ->with([
                'buyer',
                'statusRef',
                'items.product',
                'latestMilestone',
            ])
            ->orderByRaw('scheduled_at ASC NULLS LAST')
            ->orderBy('id', 'asc')
            ->get();

        $transformed = $packages->map(function (Package $pkg) {
            $assignedLogId = $pkg->getAttribute('assigned_log_id');
            $assignedAt = $pkg->getAttribute('assigned_at') ? Carbon::parse($pkg->getAttribute('assigned_at')) : null;

            $timing = ScheduleTiming::forWindow($pkg->scheduled_end_at);
            $stage = $pkg->deliveryStage();

            $isNew = $pkg->status === PackageStatus::COURIER_ASSIGNED && $pkg->courier_accepted_at === null;

            return [
                'id' => 'KRR-'.($assignedLogId ?? $pkg->id),
                'package_id' => $pkg->id,
                'age' => RelativeTime::diffForHumansId($assignedAt ?? $pkg->created_at),
                'tracking' => $pkg->tracking_number,
                'service' => $pkg->service_type?->label() ?? 'Reguler',
                'serviceKey' => $pkg->service_type?->value ?? 'REGULER',
                'window' => DisplayTime::windowLabel($pkg->scheduled_at, $pkg->scheduled_end_at),
                'windowStatus' => $timing->label(),
                'windowStatusTone' => $timing->tone(),
                'recipient' => $pkg->recipient_name ?? '-',
                'phone' => $pkg->recipient_phone ?? '-',
                'placeType' => $pkg->recipient_address_type?->label() ?? 'Rumah / Residensial',
                'address' => $pkg->recipient_address ?? '-',
                'instruction' => $pkg->handling_instructions,
                'metrics' => [
                    ['label' => 'Jumlah', 'value' => "{$pkg->koli_count} Koli"],
                    ['label' => 'Kategori', 'value' => $pkg->package_category ?? 'Frozen Food'],
                    ['label' => 'Berat', 'value' => "{$pkg->weight_kg} kg"],
                ],
                'recipientCoordinates' => [
                    'latitude' => $pkg->recipient_latitude,
                    'longitude' => $pkg->recipient_longitude,
                ],
                'status' => $pkg->status->value,
                'statusLabel' => $pkg->statusRef?->label ?? $pkg->status->value,
                'stage' => $stage->value,
                'stageLabel' => $stage->label(),
                'hasIssue' => $pkg->status === PackageStatus::ISSUE_REPORTED,
                'isNew' => $isNew,
            ];
        });

        $newTasks = $transformed->filter(fn (array $t) => $t['isNew'])->values();
        $activeTasks = $transformed->filter(fn (array $t) => ! $t['isNew'])->values();

        return [
            'newTasks' => $newTasks,
            'activeTasks' => $activeTasks,
        ];
    }
}
