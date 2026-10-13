<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DeliveryMilestoneEnum;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PackageTimelineBuilder
{
    /**
     * @return Collection<int, array{
     *     time: Carbon,
     *     time_label: string,
     *     actor: string,
     *     description: string,
     *     actor_type: string
     * }>
     */
    public function build(Package $package): Collection
    {
        $events = collect();

        // 1. Status logs
        $package->loadMissing(['statusLogs.changedByStaff', 'statusLogs.fromStatusRef', 'statusLogs.toStatusRef']);
        foreach ($package->statusLogs as $log) {
            $fromLabel = $log->fromStatusRef?->label ?? $log->from_status?->value ?? 'Awal';
            $toLabel = $log->toStatusRef?->label ?? $log->to_status?->value ?? '-';

            $actorName = 'Sistem';
            $actorType = 'system';
            if ($log->changedByStaff !== null) {
                $roleLabel = $log->changedByStaff->isAdmin() ? 'Admin' : 'Kurir';
                $actorName = "{$log->changedByStaff->name} ({$roleLabel})";
                $actorType = $log->changedByStaff->isAdmin() ? 'admin' : 'courier';
            }

            $time = Carbon::instance($log->changed_at);
            $localTime = DisplayTime::toDisplay($time) ?? $time;

            $events->push([
                'time' => $time,
                'time_label' => $localTime->format('H:i').' WIB',
                'actor' => $actorName,
                'description' => "Status berubah: {$fromLabel} → {$toLabel}.",
                'actor_type' => $actorType,
            ]);
        }

        // 2. Issue reports (reported & decided)
        $package->loadMissing(['conditionChecks.issueReport.decidedByStaff', 'conditionChecks.courier']);
        foreach ($package->conditionChecks as $check) {
            $issue = $check->issueReport;
            if ($issue === null) {
                continue;
            }

            // Reported event
            $courierName = $check->courier?->name ?? 'Kurir';
            $catLabel = $issue->issue_category?->label() ?? 'Kendala';
            $repTime = Carbon::instance($issue->reported_at);
            $repLocalTime = DisplayTime::toDisplay($repTime) ?? $repTime;

            $events->push([
                'time' => $repTime,
                'time_label' => $repLocalTime->format('H:i').' WIB',
                'actor' => "{$courierName} (Kurir)",
                'description' => "Melaporkan kendala {$catLabel} & unggah foto lapangan.",
                'actor_type' => 'courier',
            ]);

            // Decided event
            if ($issue->decision !== null && $issue->decided_at !== null) {
                $decAdmin = $issue->decidedByStaff?->name ?? 'Admin';
                $decLabel = $issue->decision->label();
                $decTime = Carbon::instance($issue->decided_at);
                $decLocalTime = DisplayTime::toDisplay($decTime) ?? $decTime;

                $events->push([
                    'time' => $decTime,
                    'time_label' => $decLocalTime->format('H:i').' WIB',
                    'actor' => "{$decAdmin} (Admin)",
                    'description' => "{$decLabel}: {$issue->decision_reason}",
                    'actor_type' => 'admin',
                ]);
            }
        }

        // 3. Notifications
        $package->loadMissing('notifications');
        foreach ($package->notifications as $notif) {
            $recType = $notif->recipient_type->value;
            $time = Carbon::instance($notif->created_at);
            $localTime = DisplayTime::toDisplay($time) ?? $time;

            $events->push([
                'time' => $time,
                'time_label' => $localTime->format('H:i').' WIB',
                'actor' => 'Sistem Dispatcher',
                'description' => "Notifikasi otomatis ({$notif->template}) ke {$recType}.",
                'actor_type' => 'system',
            ]);
        }

        // 4. Delivery milestones
        $package->loadMissing(['deliveryMilestones.courier']);
        foreach ($package->deliveryMilestones as $milestone) {
            $courierName = $milestone->courier?->name ?? 'Kurir';
            $desc = $milestone->milestone === DeliveryMilestoneEnum::DEPARTED
                ? 'Kurir berangkat mengantar paket ke alamat tujuan.'
                : 'Kurir tiba di lokasi tujuan pengantaran.';
            $time = Carbon::instance($milestone->occurred_at);
            $localTime = DisplayTime::toDisplay($time) ?? $time;

            $events->push([
                'time' => $time,
                'time_label' => $localTime->format('H:i').' WIB',
                'actor' => "{$courierName} (Kurir)",
                'description' => $desc,
                'actor_type' => 'courier',
            ]);
        }

        return $events->sortBy(fn (array $item) => $item['time']->timestamp)->values();
    }
}
