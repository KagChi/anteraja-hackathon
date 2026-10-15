<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\ConfirmationResponse;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use App\Support\DisplayTime;
use App\Support\NotificationQueue;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Ringkasan harian operasi untuk admin.
 *
 * Satu-satunya tempat yang menghitung angka laporan: perintah terjadwal dan
 * tombol "kirim sekarang" di dashboard memanggil metode yang sama, supaya
 * laporan yang dikirim lewat WhatsApp selalu cocok dengan yang tampil di
 * halaman Laporan.
 */
final class DailyReportSender
{
    public function __construct(private readonly NotificationQueue $notifications) {}

    /**
     * @return array<string, mixed>
     */
    public function send(?CarbonInterface $day = null): array
    {
        $localDay = $day === null
            ? Carbon::now(DisplayTime::DISPLAY_TIMEZONE)
            : Carbon::instance($day)->setTimezone(DisplayTime::DISPLAY_TIMEZONE);

        [$startUtc, $endUtc] = DisplayTime::dayRange($localDay);

        $payload = array_merge($this->summarise($startUtc, $endUtc), [
            'report_day' => $localDay->format('Y-m-d'),
            'report_date' => $localDay->copy()->locale('id')->translatedFormat('j F Y'),
            'report_label' => 'Laporan harian '.$localDay->copy()->locale('id')->translatedFormat('j F Y'),
        ]);

        $admins = Staff::query()
            ->where('role', StaffRole::ADMIN)
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            $this->notifications->queueStaffReport($admin, 'daily_report', $payload);
        }

        return $payload + ['queued' => $admins->count()];
    }

    /**
     * @return array{
     *     total_shipments: int,
     *     delivered_count: int,
     *     in_process_count: int,
     *     issue_count: int,
     *     decision_count: int,
     *     unanswered_count: int
     * }
     */
    private function summarise(CarbonInterface $startUtc, CarbonInterface $endUtc): array
    {
        $scheduledToday = fn () => Package::query()
            ->where('scheduled_at', '>=', $startUtc)
            ->where('scheduled_at', '<', $endUtc);

        $threshold = Carbon::now()->subMinutes(
            (int) config('coldchain.client.unanswered_after_minutes', 120)
        );

        return [
            'total_shipments' => $scheduledToday()->count(),
            'delivered_count' => $scheduledToday()->whereHas('delivery')->count(),
            'in_process_count' => $scheduledToday()
                ->whereNotIn('status', [
                    PackageStatus::RECEIVED->value,
                    PackageStatus::RETURNED->value,
                    PackageStatus::CLOSED->value,
                ])
                ->count(),
            'issue_count' => IssueReport::query()
                ->where('reported_at', '>=', $startUtc)
                ->where('reported_at', '<', $endUtc)
                ->count(),
            'decision_count' => IssueReport::query()
                ->where('decided_at', '>=', $startUtc)
                ->where('decided_at', '<', $endUtc)
                ->count(),
            'unanswered_count' => ScheduleConfirmation::query()
                ->where('response', ConfirmationResponse::PENDING)
                ->where('requested_at', '<=', $threshold)
                ->whereHas('package', fn ($query) => $query->where('status', PackageStatus::AWAITING_CONFIRMATION->value))
                ->count(),
        ];
    }
}
