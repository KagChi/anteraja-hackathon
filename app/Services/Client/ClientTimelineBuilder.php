<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\DTOs\Client\TimelineEntry;
use App\Enums\DeliveryMilestoneEnum;
use App\Enums\PackageStatus;
use App\Models\Package;
use Carbon\CarbonInterface;

final class ClientTimelineBuilder
{
    /**
     * @return list<TimelineEntry>
     */
    public function forTracking(Package $package): array
    {
        return $this->build($package, isIssueOnly: false);
    }

    /**
     * @return list<TimelineEntry>
     */
    public function forIssue(Package $package): array
    {
        return $this->build($package, isIssueOnly: true);
    }

    /**
     * @return list<TimelineEntry>
     */
    private function build(Package $package, bool $isIssueOnly): array
    {
        $hub = (string) config('coldchain.hub.display_name', 'Hub Jakarta Selatan');
        $courierName = $package->courier?->name ?? 'Kurir';

        /** @var list<array{time: CarbonInterface, title: string, description: string, tag: string}> $events */
        $events = [];

        // 1. Status logs
        $logs = $package->statusLogs()->orderBy('changed_at', 'asc')->get();
        foreach ($logs as $log) {
            $to = $log->to_status;
            $from = $log->from_status;
            $at = $log->changed_at;

            if ($to === PackageStatus::CLOSED) {
                continue;
            }

            if ($to === PackageStatus::CREATED) {
                $events[] = [
                    'time' => $at,
                    'title' => 'Tiba di Hub Cold Storage',
                    'description' => "Paket tiba dan disimpan di freezer {$hub}.",
                    'tag' => 'created',
                ];
            } elseif (! $isIssueOnly && $to === PackageStatus::AWAITING_CONFIRMATION) {
                $events[] = [
                    'time' => $at,
                    'title' => 'Menunggu Konfirmasi Jadwal',
                    'description' => 'Mohon konfirmasi jadwal pengantaran.',
                    'tag' => 'awaiting_confirmation',
                ];
            } elseif (! $isIssueOnly && $to === PackageStatus::RESCHEDULE_REQUESTED) {
                $events[] = [
                    'time' => $at,
                    'title' => 'Permintaan Jadwal Ulang',
                    'description' => 'Permintaan Anda diterima, admin akan mengusulkan jadwal baru.',
                    'tag' => 'reschedule_requested',
                ];
            } elseif (! $isIssueOnly && $to === PackageStatus::CONFIRMED) {
                $events[] = [
                    'time' => $at,
                    'title' => 'Jadwal Dikonfirmasi',
                    'description' => 'Jadwal pengantaran telah disepakati.',
                    'tag' => 'confirmed',
                ];
            } elseif (! $isIssueOnly && $to === PackageStatus::COURIER_ASSIGNED) {
                $events[] = [
                    'time' => $at,
                    'title' => 'Kurir Ditugaskan',
                    'description' => 'Kurir khusus frozen telah ditugaskan.',
                    'tag' => 'courier_assigned',
                ];
            } elseif (! $isIssueOnly && $to === PackageStatus::CHECKED) {
                if ($from === PackageStatus::COURIER_ASSIGNED) {
                    $events[] = [
                        'time' => $at,
                        'title' => 'Paket Keluar Hub',
                        'description' => "Paket dipindahkan ke cooler box kurir di {$hub}.",
                        'tag' => 'checked_dispatch',
                    ];
                } elseif ($from === PackageStatus::ISSUE_REPORTED) {
                    $events[] = [
                        'time' => $at,
                        'title' => 'Pemeriksaan Ulang',
                        'description' => 'Paket sedang diperiksa ulang.',
                        'tag' => 'checked_recheck',
                    ];
                }
            } elseif (! $isIssueOnly && $to === PackageStatus::IN_DELIVERY) {
                if ($from === PackageStatus::ISSUE_REPORTED) {
                    $events[] = [
                        'time' => $at,
                        'title' => 'Pengiriman Dilanjutkan',
                        'description' => "Pengiriman dilanjutkan oleh {$courierName}.",
                        'tag' => 'in_delivery_resumed',
                    ];
                } else {
                    $events[] = [
                        'time' => $at,
                        'title' => 'Sedang Diantar',
                        'description' => "Kurir {$courierName} sedang menuju alamat penerima membawa cooler box.",
                        'tag' => 'in_delivery',
                    ];
                }
            } elseif ($to === PackageStatus::RETURNED) {
                $events[] = [
                    'time' => $at,
                    'title' => 'Paket Dikembalikan',
                    'description' => 'Paket dikembalikan ke penjual.',
                    'tag' => 'returned',
                ];
            } elseif (! $isIssueOnly && $to === PackageStatus::RECEIVED) {
                $receivedBy = $package->delivery?->received_by ?? $package->buyer?->name ?? 'Penerima';
                $events[] = [
                    'time' => $at,
                    'title' => 'Paket Diterima',
                    'description' => "Diterima oleh {$receivedBy}.",
                    'tag' => 'received',
                ];
            }
        }

        // 2. Delivery Milestones (only for tracking)
        if (! $isIssueOnly) {
            $milestones = $package->deliveryMilestones()
                ->where('milestone', DeliveryMilestoneEnum::ARRIVED)
                ->orderBy('occurred_at', 'asc')
                ->get();

            foreach ($milestones as $milestone) {
                $events[] = [
                    'time' => $milestone->occurred_at,
                    'title' => 'Kurir Tiba di Lokasi',
                    'description' => 'Kurir telah tiba di alamat tujuan.',
                    'tag' => 'arrived',
                ];
            }
        }

        // 3. Issue Reports
        $checks = $package->conditionChecks()->with('issueReport')->get();
        foreach ($checks as $check) {
            $issue = $check->issueReport;
            if ($issue === null) {
                continue;
            }

            $cat = $issue->issue_category instanceof \BackedEnum
                ? $issue->issue_category->value
                : ($issue->issue_category ? (string) $issue->issue_category : 'default');

            $summaryKey = "client.issue.summary.{$cat}";
            $summary = __($summaryKey, [], 'id');
            if ($summary === $summaryKey) {
                $summary = __('client.issue.summary.default', [], 'id');
            }

            $events[] = [
                'time' => $issue->reported_at,
                'title' => 'Kendala Ditemukan',
                'description' => (string) $summary,
                'tag' => 'issue_reported',
            ];

            if ($issue->decided_at !== null && $issue->decision !== null) {
                $decisionCode = $issue->decision instanceof \BackedEnum
                    ? $issue->decision->value
                    : (string) $issue->decision;
                $decKey = "client.issue.decision.{$decisionCode}";
                $decisionText = __($decKey, [], 'id');
                if ($decisionText === $decKey) {
                    $decisionText = 'Keputusan telah ditetapkan oleh admin operasional.';
                }

                $events[] = [
                    'time' => $issue->decided_at,
                    'title' => 'Keputusan Admin',
                    'description' => (string) $decisionText,
                    'tag' => 'issue_decided',
                ];
            }
        }

        // Sort latest first (DESC)
        usort($events, fn ($a, $b) => $b['time']->getTimestamp() <=> $a['time']->getTimestamp());

        $nowJakarta = now('Asia/Jakarta')->startOfDay();
        $timelineEntries = [];

        foreach ($events as $index => $event) {
            $eventDate = $event['time']->timezone('Asia/Jakarta');
            $timeLabel = $eventDate->startOfDay()->equalTo($nowJakarta)
                ? $eventDate->format('H.i')
                : $eventDate->translatedFormat('d M, ').$eventDate->format('H.i');

            $timelineEntries[] = new TimelineEntry(
                title: $event['title'],
                description: $event['description'],
                time: $timeLabel,
                isAccent: ($index === 0),
            );
        }

        return $timelineEntries;
    }
}
