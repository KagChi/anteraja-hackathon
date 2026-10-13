<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Client\ClientScreen;
use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Exceptions\ClientLinkExpired;
use App\Exceptions\ClientLinkInvalid;
use App\Models\Buyer;
use App\Models\ConditionCheck;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\Staff;
use App\Services\Client\ClientAccessService;
use App\Services\Client\ClientLinkService;
use App\Services\Client\ClientScreenResolver;
use App\Services\Client\ColdChainSummaryService;
use App\Services\Client\ReplacementEligibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ClientUnitTest extends TestCase
{
    use DatabaseTransactions;

    private ClientAccessService $accessService;

    private ClientLinkService $linkService;

    private ClientScreenResolver $screenResolver;

    private ColdChainSummaryService $coldChainService;

    private ReplacementEligibility $eligibility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessService = app(ClientAccessService::class);
        $this->linkService = app(ClientLinkService::class);
        $this->screenResolver = app(ClientScreenResolver::class);
        $this->coldChainService = app(ColdChainSummaryService::class);
        $this->eligibility = app(ReplacementEligibility::class);
    }

    private function createSamplePackage(array $attributes = []): Package
    {
        $buyer = Buyer::firstOrCreate(
            ['phone' => '081299990001'],
            ['name' => 'Buyer Test', 'address' => 'Jl. Test 1', 'area' => 'Jakarta']
        );

        $admin = Staff::firstOrCreate(
            ['email' => 'admin.test@anteraja.id'],
            ['name' => 'Admin Test', 'role' => StaffRole::ADMIN, 'phone' => '081199990001', 'password' => 'secret']
        );

        return Package::create(array_merge([
            'buyer_id' => $buyer->id,
            'created_by' => $admin->id,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'status' => PackageStatus::AWAITING_CONFIRMATION,
        ], $attributes))->refresh();
    }

    public function test_access_service_resolves_valid_token(): void
    {
        $package = $this->createSamplePackage();
        $resolved = $this->accessService->resolve($package->client_token);

        $this->assertSame($package->id, $resolved->id);
    }

    public function test_access_service_rejects_invalid_token_format(): void
    {
        $this->expectException(ClientLinkInvalid::class);
        $this->accessService->resolve('short-token-invalid');
    }

    public function test_access_service_rejects_tracking_number_as_token(): void
    {
        $package = $this->createSamplePackage();
        $this->expectException(ClientLinkInvalid::class);
        $this->accessService->resolve($package->tracking_number);
    }

    public function test_access_service_rejects_unknown_hex_token(): void
    {
        $this->expectException(ClientLinkInvalid::class);
        $unknownHex = str_repeat('a', 64);
        $this->accessService->resolve($unknownHex);
    }

    public function test_access_service_triggers_rate_limit_after_twenty_failures(): void
    {
        $testIp = '192.168.1.100';
        RateLimiter::clear('client-lookup-failed:'.$testIp);

        for ($i = 0; $i < 20; $i++) {
            try {
                $this->accessService->resolve(str_repeat('b', 64), $testIp);
            } catch (ClientLinkInvalid) {
                // Expected
            }
        }

        $this->expectException(HttpException::class);
        $this->accessService->resolve(str_repeat('b', 64), $testIp);
    }

    public function test_access_service_rejects_expired_terminal_package(): void
    {
        $package = $this->createSamplePackage([
            'status' => PackageStatus::RECEIVED,
        ]);

        // Artificially age the package beyond 30 days
        DB::statement('ALTER TABLE packages DISABLE TRIGGER trg_packages_updated_at');
        DB::statement("UPDATE packages SET updated_at = now() - interval '35 days' WHERE id = ?", [$package->id]);
        DB::statement('ALTER TABLE packages ENABLE TRIGGER trg_packages_updated_at');

        $this->expectException(ClientLinkExpired::class);
        $this->accessService->resolve($package->client_token);
    }

    public function test_screen_resolver_maps_statuses_correctly(): void
    {
        $pkgAwaiting = $this->createSamplePackage(['status' => PackageStatus::AWAITING_CONFIRMATION]);
        $this->assertSame(ClientScreen::SCHEDULE, $this->screenResolver->resolve($pkgAwaiting));

        $pkgInDelivery = $this->createSamplePackage(['status' => PackageStatus::IN_DELIVERY]);
        $this->assertSame(ClientScreen::TRACKING, $this->screenResolver->resolve($pkgInDelivery));

        $pkgIssue = $this->createSamplePackage(['status' => PackageStatus::ISSUE_REPORTED]);
        $courier = Staff::firstOrCreate(
            ['email' => 'courier.unit@anteraja.id'],
            ['name' => 'Courier Unit', 'role' => StaffRole::COURIER, 'phone' => '081299990002', 'password' => 'secret']
        );
        $chk = ConditionCheck::create([
            'package_id' => $pkgIssue->id,
            'courier_id' => $courier->id,
            'is_normal' => false,
            'temperature_c' => -18.0,
            'notes' => 'Issue found',
        ]);
        IssueReport::create([
            'check_id' => $chk->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR,
            'description' => 'Kemasan bocor',
            'reported_at' => now(),
        ]);

        $this->assertSame(ClientScreen::ISSUE, $this->screenResolver->resolve($pkgIssue));
    }

    public function test_cold_chain_summary_evaluates_measured_and_target_states(): void
    {
        $pkg = $this->createSamplePackage();
        $courier = Staff::firstOrCreate(
            ['email' => 'courier.cold@anteraja.id'],
            ['name' => 'Courier Cold', 'role' => StaffRole::COURIER, 'phone' => '081299990003', 'password' => 'secret']
        );

        // When no checks exist: target mode, chain monitored
        $summary = $this->coldChainService->forPackage($pkg);
        $this->assertFalse($summary->isMeasured);
        $this->assertSame('Target', $summary->temperatureState);
        $this->assertSame('Dipantau', $summary->chainState);

        // With optimal check
        ConditionCheck::create([
            'package_id' => $pkg->id,
            'courier_id' => $courier->id,
            'is_normal' => true,
            'temperature_c' => -19.5,
            'checked_at' => now(),
        ]);

        $summaryOptimal = $this->coldChainService->forPackage($pkg);
        $this->assertTrue($summaryOptimal->isMeasured);
        $this->assertSame('Optimal', $summaryOptimal->temperatureState);
        $this->assertSame('Terjaga', $summaryOptimal->chainState);

        // With breach check
        ConditionCheck::create([
            'package_id' => $pkg->id,
            'courier_id' => $courier->id,
            'is_normal' => false,
            'temperature_c' => -10.0,
            'checked_at' => now()->addMinute(),
        ]);

        $summaryBreach = $this->coldChainService->forPackage($pkg);
        $this->assertTrue($summaryBreach->isMeasured);
        $this->assertSame('DiLuarBatas', $summaryBreach->temperatureState);
        $this->assertSame('PerluPerhatian', $summaryBreach->chainState);
    }

    public function test_replacement_eligibility_enforces_business_rules(): void
    {
        $pkg = $this->createSamplePackage(['status' => PackageStatus::RETURNED]);
        $courier = Staff::firstOrCreate(
            ['email' => 'courier.elig@anteraja.id'],
            ['name' => 'Courier Elig', 'role' => StaffRole::COURIER, 'phone' => '081299990004', 'password' => 'secret']
        );
        $chk = ConditionCheck::create([
            'package_id' => $pkg->id,
            'courier_id' => $courier->id,
            'is_normal' => false,
            'temperature_c' => -18.0,
        ]);
        $admin = Staff::firstOrCreate(
            ['email' => 'admin.elig@anteraja.id'],
            ['name' => 'Admin Elig', 'role' => StaffRole::ADMIN, 'phone' => '081299990005', 'password' => 'secret']
        );

        IssueReport::create([
            'check_id' => $chk->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR,
            'description' => 'Kerusakan kemasan bocor',
            'decision' => IssueDecision::RETURN,
            'decision_reason' => 'Retur karena rusak',
            'decided_by' => $admin->id,
            'decided_at' => now(),
            'reported_at' => now()->subHour(),
        ]);

        $res = $this->eligibility->check($pkg);
        $this->assertTrue($res->isEligible);
        $this->assertNull($res->childPackage);

        // Once child exists, it becomes ineligible
        $child = $this->createSamplePackage(['resend_of_package_id' => $pkg->id]);
        $resAfter = $this->eligibility->check($pkg);
        $this->assertFalse($resAfter->isEligible);
        $this->assertSame($child->id, $resAfter->childPackage?->id);
    }

    public function test_client_link_service_builds_expected_urls(): void
    {
        $pkg = $this->createSamplePackage();

        $this->assertStringContainsString($pkg->client_token, $this->linkService->entry($pkg));
        $this->assertStringContainsString('/tracking', $this->linkService->tracking($pkg));
        $this->assertStringContainsString('/schedule', $this->linkService->schedule($pkg));
        $this->assertStringContainsString('/issue', $this->linkService->issue($pkg));
    }
}
