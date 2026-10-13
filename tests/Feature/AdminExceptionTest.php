<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AdminExceptionTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Staff::where('role', StaffRole::ADMIN)->where('is_active', true)->firstOrFail();
    }

    public function test_admin_can_view_exceptions_list(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/exceptions');

        $response->assertOk();
        $response->assertJsonStructure([
            'tab',
            'issues',
            'paginator' => ['total', 'per_page', 'current_page', 'last_page'],
        ]);
    }

    public function test_admin_can_view_exception_detail(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/exceptions/AJ-FRZ-0012345');

        $response->assertOk();
        $response->assertJsonStructure([
            'package',
            'issueReport',
            'allowedDecisions',
            'checklist',
            'timeline',
        ]);
    }

    public function test_admin_can_resolve_issue_with_return_decision(): void
    {
        $pkg = Package::where('tracking_number', 'AJ-FRZ-0012345')->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson("/api/admin/exceptions/{$pkg->tracking_number}/decision", [
            'decision' => 'RETURN',
            'decision_reason' => 'Kerusakan kemasan tidak dapat ditoleransi. Paket ditarik ke DC.',
        ]);

        $response->assertOk();

        $pkg->refresh();
        $this->assertSame(PackageStatus::RETURNED, $pkg->status);
    }

    public function test_admin_can_view_daily_report_and_export_csv(): void
    {
        $indexResponse = $this->actingAs($this->admin)->getJson('/api/admin/reports');
        $indexResponse->assertOk();
        $indexResponse->assertJsonStructure([
            'date',
            'kpis',
            'issues',
            'paginator',
        ]);

        $exportResponse = $this->actingAs($this->admin)->get('/api/admin/reports/export');
        $exportResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $exportResponse->headers->get('content-type'));
    }

    public function test_admin_can_view_history_and_export_csv(): void
    {
        $indexResponse = $this->actingAs($this->admin)->getJson('/api/admin/history');
        $indexResponse->assertOk();
        $indexResponse->assertJsonStructure([
            'history',
            'filters',
            'paginator',
        ]);

        $exportResponse = $this->actingAs($this->admin)->get('/api/admin/history/export');
        $exportResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $exportResponse->headers->get('content-type'));
    }
}
