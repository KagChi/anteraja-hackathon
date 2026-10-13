<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Enums\ResolutionStatus;
use App\Enums\Tone;
use App\Support\Csv\StreamedCsv;
use App\Support\DisplayTime;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class ColdChainUnitTest extends TestCase
{
    public function test_display_time_day_range_utc_conversion(): void
    {
        // 2026-10-14 in Jakarta (WIB = UTC+7) is 2026-10-13 17:00:00 UTC to 2026-10-14 17:00:00 UTC
        [$startUtc, $endUtc] = DisplayTime::dayRange('2026-10-14');

        $this->assertSame('2026-10-13 17:00:00', $startUtc->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-14 17:00:00', $endUtc->format('Y-m-d H:i:s'));
    }

    public function test_display_time_label(): void
    {
        $todayJkt = Carbon::now(DisplayTime::DISPLAY_TIMEZONE);
        $tomorrowJkt = $todayJkt->copy()->addDay();
        $yesterdayJkt = $todayJkt->copy()->subDay();

        $this->assertSame('Hari ini', DisplayTime::label($todayJkt));
        $this->assertSame('Besok', DisplayTime::label($tomorrowJkt));
        $this->assertSame('Kemarin', DisplayTime::label($yesterdayJkt));
    }

    public function test_display_time_window_label(): void
    {
        $start = Carbon::createFromFormat('Y-m-d H:i', '2026-10-14 14:00', DisplayTime::DISPLAY_TIMEZONE);
        $end = Carbon::createFromFormat('Y-m-d H:i', '2026-10-14 16:00', DisplayTime::DISPLAY_TIMEZONE);

        $label = DisplayTime::windowLabel($start, $end);
        $this->assertStringContainsString('14:00 - 16:00', $label);
    }

    public function test_issue_category_allowed_decisions(): void
    {
        $damageDecisions = IssueCategory::KEMASAN_BOCOR->allowedDecisions();
        $this->assertContains(IssueDecision::RETURN, $damageDecisions);
        $this->assertContains(IssueDecision::RESEND, $damageDecisions);
        $this->assertContains(IssueDecision::PROCEED, $damageDecisions);
        $this->assertNotContains(IssueDecision::RESCHEDULE, $damageDecisions);

        $accessDecisions = IssueCategory::PEMBELI_TIDAK_ADA->allowedDecisions();
        $this->assertContains(IssueDecision::RESCHEDULE, $accessDecisions);
        $this->assertContains(IssueDecision::RETURN, $accessDecisions);
        $this->assertNotContains(IssueDecision::PROCEED, $accessDecisions);
    }

    public function test_package_status_tones(): void
    {
        $this->assertSame(Tone::NEUTRAL, PackageStatus::CREATED->tone());
        $this->assertSame(Tone::MUTED, PackageStatus::AWAITING_CONFIRMATION->tone());
        $this->assertSame(Tone::WARNING, PackageStatus::RESCHEDULE_REQUESTED->tone());
        $this->assertSame(Tone::INFO, PackageStatus::CONFIRMED->tone());
        $this->assertSame(Tone::DANGER, PackageStatus::ISSUE_REPORTED->tone());
        $this->assertSame(Tone::DARK, PackageStatus::IN_DELIVERY->tone());
        $this->assertSame(Tone::SUCCESS, PackageStatus::RECEIVED->tone());
    }

    public function test_resolution_status_tones(): void
    {
        $this->assertSame(Tone::WARNING, ResolutionStatus::MENUNGGU_KEPUTUSAN->tone());
        $this->assertSame(Tone::SUCCESS, ResolutionStatus::SELESAI->tone());
        $this->assertSame(Tone::INFO, ResolutionStatus::DILANJUTKAN->tone());
    }

    public function test_csv_injection_prevention(): void
    {
        $this->assertSame("'=SUM(A1:A10)", StreamedCsv::sanitizeCell('=SUM(A1:A10)'));
        $this->assertSame("'+123456", StreamedCsv::sanitizeCell('+123456'));
        $this->assertSame("'-calc", StreamedCsv::sanitizeCell('-calc'));
        $this->assertSame("'@evil", StreamedCsv::sanitizeCell('@evil'));
        $this->assertSame('Normal Text', StreamedCsv::sanitizeCell('Normal Text'));
    }
}
