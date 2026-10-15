<?php

declare(strict_types=1);

namespace App\Queries;

use App\DTOs\ReportFilters;
use App\Enums\PackageStatus;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\StatusLog;
use App\Support\DisplayTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read model for the admin beranda: one day's operational summary plus the
 * audit trail of that day.
 *
 * The six core numbers come from DailyReportQuery, so the beranda and the
 * Laporan screen can never disagree. The extra counts and the narrative are
 * derived here for the auto summary card and the PDF export.
 */
final class DashboardQuery
{
    public function __construct(private readonly DailyReportQuery $reports) {}

    /**
     * @return array{
     *     total_shipments: int,
     *     fleet_capacity_pct: float,
     *     delivered_count: int,
     *     success_rate_pct: float,
     *     issue_count: int,
     *     incident_rate_pct: float,
     *     in_process_count: int,
     *     returned_count: int,
     *     pending_decision_count: int,
     *     decided_count: int
     * }
     */
    public function summary(string $date): array
    {
        [$startUtc, $endUtc] = DisplayTime::dayRange($date);

        $kpis = $this->reports->getKpis(new ReportFilters($date));

        $scheduledToday = fn () => Package::query()
            ->where('scheduled_at', '>=', $startUtc)
            ->where('scheduled_at', '<', $endUtc);

        $inProcess = $scheduledToday()
            ->whereNotIn('status', [
                PackageStatus::RECEIVED->value,
                PackageStatus::RETURNED->value,
                PackageStatus::CLOSED->value,
            ])
            ->count();

        $returned = $scheduledToday()
            ->where('status', PackageStatus::RETURNED->value)
            ->count();

        $pending = IssueReport::query()
            ->whereNull('decision')
            ->where('reported_at', '>=', $startUtc)
            ->where('reported_at', '<', $endUtc)
            ->count();

        $decided = IssueReport::query()
            ->whereNotNull('decision')
            ->where('decided_at', '>=', $startUtc)
            ->where('decided_at', '<', $endUtc)
            ->count();

        return $kpis + [
            'in_process_count' => $inProcess,
            'returned_count' => $returned,
            'pending_decision_count' => $pending,
            'decided_count' => $decided,
        ];
    }

    /**
     * A deterministic, plain-Indonesian sentence for the auto summary card.
     * No LLM: the wording is a function of the numbers, so it is testable.
     *
     * @param  array<string, mixed>  $summary
     */
    public function narrative(array $summary, string $dateLabel): string
    {
        $total = (int) ($summary['total_shipments'] ?? 0);
        $delivered = (int) ($summary['delivered_count'] ?? 0);
        $inProcess = (int) ($summary['in_process_count'] ?? 0);
        $issues = (int) ($summary['issue_count'] ?? 0);
        $pending = (int) ($summary['pending_decision_count'] ?? 0);
        $decided = (int) ($summary['decided_count'] ?? 0);
        $returned = (int) ($summary['returned_count'] ?? 0);
        $success = (float) ($summary['success_rate_pct'] ?? 0);
        $incident = (float) ($summary['incident_rate_pct'] ?? 0);
        $fleet = (float) ($summary['fleet_capacity_pct'] ?? 0);

        if ($total === 0) {
            return sprintf(
                'Belum ada paket cold-chain yang dijadwalkan pada %s. Utilisasi armada %s%% dari kapasitas harian.',
                $dateLabel,
                $this->formatPct($fleet)
            );
        }

        $text = sprintf(
            'Pada %s, %d paket cold-chain dijadwalkan; %d sudah diterima (%s%% keberhasilan) dan %d masih berjalan.',
            $dateLabel,
            $total,
            $delivered,
            $this->formatPct($success),
            $inProcess
        );

        $text .= $issues === 0
            ? ' Tidak ada kendala dilaporkan pada hari ini.'
            : sprintf(
                ' Terdapat %d kendala dilaporkan (%s%% insiden): %d menunggu keputusan dan %d sudah diputuskan.',
                $issues,
                $this->formatPct($incident),
                $pending,
                $decided
            );

        if ($returned > 0) {
            $text .= sprintf(' %d paket dikembalikan ke hub.', $returned);
        }

        $text .= sprintf(' Utilisasi armada %s%% dari kapasitas harian.', $this->formatPct($fleet));

        return $text;
    }

    /**
     * The audit trail for one Jakarta day: every status change with its actor.
     *
     * @return LengthAwarePaginator<int, StatusLog>
     */
    public function paginateActivity(
        string $date,
        int $page,
        int $perPage = 15,
        ?string $tracking = null
    ): LengthAwarePaginator {
        [$startUtc, $endUtc] = DisplayTime::dayRange($date);

        $query = StatusLog::query()
            ->with(['package.buyer', 'changedByStaff', 'fromStatusRef', 'toStatusRef'])
            ->where('changed_at', '>=', $startUtc)
            ->where('changed_at', '<', $endUtc);

        $needle = $tracking !== null ? trim($tracking) : '';
        if ($needle !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtoupper($needle));
            $query->whereHas(
                'package',
                fn ($package) => $package->whereRaw('UPPER(tracking_number) LIKE ?', ["%{$escaped}%"])
            );
        }

        return $query
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{
     *     id: int,
     *     tracking: string,
     *     buyer: string,
     *     from: ?string,
     *     fromLabel: ?string,
     *     to: ?string,
     *     toLabel: string,
     *     tone: string,
     *     actor: string,
     *     at: string,
     *     at_iso: ?string
     * }
     */
    public function presentActivity(StatusLog $log): array
    {
        $from = $log->from_status;
        $to = $log->to_status;

        return [
            'id' => (int) $log->id,
            'tracking' => $log->package?->tracking_number ?? '-',
            'buyer' => $log->package?->buyer?->name ?? '-',
            'from' => $from?->value,
            'fromLabel' => $log->fromStatusRef?->label ?? $from?->value,
            'to' => $to?->value,
            'toLabel' => $log->toStatusRef?->label ?? $to?->value ?? '-',
            'tone' => $to?->tone()->value ?? 'neutral',
            'actor' => $log->changedByStaff?->name ?? 'Pembeli / Sistem',
            'at' => DisplayTime::toDisplay($log->changed_at)?->format('H:i').' WIB',
            'at_iso' => $log->changed_at?->toIso8601String(),
        ];
    }

    private function formatPct(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
