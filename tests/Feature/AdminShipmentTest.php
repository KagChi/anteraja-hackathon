<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Package;
use App\Models\Product;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AdminShipmentTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Staff::where('role', StaffRole::ADMIN)->where('is_active', true)->firstOrFail();
    }

    public function test_admin_can_view_shipments_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/shipments');

        $response->assertOk();
        $response->assertJsonStructure([
            'shipments',
            'paginator' => ['total', 'per_page', 'current_page', 'last_page'],
            'filters',
            'couriers',
        ]);
    }

    public function test_admin_can_filter_shipments(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/shipments?schedule=all&status=ISSUE_REPORTED');

        $response->assertOk();
    }

    public function test_admin_can_search_shipments_safely(): void
    {
        // Testing search with wildcard characters to verify ILIKE escaping
        $response = $this->actingAs($this->admin)->getJson('/api/admin/shipments?q=%_test\\');

        $response->assertOk();
    }

    public function test_admin_can_export_shipments_csv(): void
    {
        $response = $this->actingAs($this->admin)->get('/api/admin/shipments/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_admin_can_view_shipment_details(): void
    {
        $pkg = Package::where('tracking_number', 'AJ-FRZ-0012345')->firstOrFail();

        $response = $this->actingAs($this->admin)->getJson("/api/admin/shipments/{$pkg->tracking_number}");

        $response->assertOk();
        $response->assertJsonStructure([
            'package',
            'timeline',
        ]);
    }

    public function test_admin_can_create_new_shipment(): void
    {
        $product = Product::firstOrFail();
        $todayStr = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        $payload = [
            'buyer' => [
                'name' => 'Budi Tes Pembeli',
                'phone' => '081233344455',
                'address' => 'Jl. Pengujian No. 99, Jakarta Selatan',
                'area' => 'Jakarta Selatan',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
            'scheduled_date' => $todayStr,
            'window_start' => '14:00',
            'window_end' => '16:00',
            'weight_kg' => 2.5,
            'cooler_box_code' => 'BOX-TEST',
            'package_category' => 'Frozen Food',
            'handling_instructions' => 'Jaga tetap beku.',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/shipments', $payload);

        $response->assertCreated();

        $created = Package::where('cooler_box_code', 'BOX-TEST')->latest('id')->firstOrFail();
        $this->assertSame(PackageStatus::AWAITING_CONFIRMATION, $created->status);
        $this->assertNotNull($created->tracking_number);
        $this->assertDatabaseHas('schedule_confirmations', [
            'package_id' => $created->id,
            'response' => 'PENDING',
        ]);
    }

    public function test_admin_can_assign_courier(): void
    {
        $courier = Staff::where('role', StaffRole::COURIER)->where('is_active', true)->firstOrFail();
        $unassignedPkg = Package::where('tracking_number', 'AJ-FRZ-0012347')->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/shipments/{$unassignedPkg->tracking_number}/assign", [
                'courier_id' => $courier->id,
            ]);

        $response->assertOk();

        $unassignedPkg->refresh();
        $this->assertSame(PackageStatus::COURIER_ASSIGNED, $unassignedPkg->status);
        $this->assertSame($courier->id, $unassignedPkg->courier_id);
    }
}
