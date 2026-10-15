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

    /** A counter parcel can be sent to someone other than the buyer. */
    public function test_admin_can_send_a_shipment_to_a_named_recipient(): void
    {
        $product = Product::firstOrFail();
        $todayStr = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        $response = $this->actingAs($this->admin)->postJson('/api/admin/shipments', [
            'buyer' => [
                'name' => 'Budi Tes Pengirim',
                'phone' => '081233344466',
                'address' => 'Jl. Pengujian No. 99, Jakarta Selatan',
                'area' => 'Jakarta Selatan',
                'latitude' => -6.240000,
                'longitude' => 106.830000,
            ],
            'recipient' => [
                'name' => 'Ibu Budi Penerima',
                'phone' => '081233344477',
                'address' => 'Jl. Cihampelas No. 88, Coblong',
                'area' => 'Bandung, Jawa Barat',
                'address_type' => 'RESIDENTIAL',
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'scheduled_date' => $todayStr,
            'window_start' => '14:00',
            'window_end' => '16:00',
            'weight_kg' => 1.0,
            'cooler_box_code' => 'BOX-RECIPIENT',
        ]);

        $response->assertCreated();

        $created = Package::where('cooler_box_code', 'BOX-RECIPIENT')->latest('id')->firstOrFail();

        // The recipient is the destination; the buyer keeps their own details.
        $this->assertSame('Ibu Budi Penerima', $created->recipient_name);
        $this->assertSame('+6281233344477', $created->recipient_phone);
        $this->assertSame('Jl. Cihampelas No. 88, Coblong', $created->recipient_address);
        $this->assertSame('Bandung, Jawa Barat', $created->recipient_area);
        $this->assertNull($created->recipient_latitude);
        $this->assertSame('Budi Tes Pengirim', $created->buyer?->name);

        // The queue and the export name both sides of the parcel.
        $this->actingAs($this->admin)
            ->getJson('/api/admin/shipments?q=Ibu Budi&schedule=all&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['recipient' => 'Ibu Budi Penerima', 'location' => 'Bandung, Jawa Barat']);

        $csv = $this->actingAs($this->admin)->get('/api/admin/shipments/export');
        $csv->assertOk();
        $this->assertStringContainsString('PENERIMA', $csv->streamedContent());
        $this->assertStringContainsString('Ibu Budi Penerima', $csv->streamedContent());
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

    public function test_admin_can_move_a_courier_before_the_parcel_is_on_the_road(): void
    {
        $budi = Staff::where('email', 'budi@anteraja.id')->firstOrFail();
        $ahmad = Staff::where('email', 'ahmad@anteraja.id')->firstOrFail();
        $parcel = Package::where('tracking_number', 'AJ-FRZ-0012345')->firstOrFail();
        $parcel->forceFill(['courier_accepted_at' => Carbon::now()])->save();

        $this->assertSame($budi->id, $parcel->courier_id);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/shipments/{$parcel->tracking_number}/assign", [
                'courier_id' => $ahmad->id,
            ]);

        $response->assertOk();

        $parcel->refresh();
        $this->assertSame($ahmad->id, $parcel->courier_id);
        // The new courier has to take the task again.
        $this->assertNull($parcel->courier_accepted_at);
        // It was already checked and flagged, so only the hands change.
        $this->assertSame(PackageStatus::ISSUE_REPORTED, $parcel->status);
    }

    public function test_assigning_the_courier_who_already_holds_the_task_keeps_their_acceptance(): void
    {
        $budi = Staff::where('email', 'budi@anteraja.id')->firstOrFail();
        $parcel = Package::where('tracking_number', 'AJ-FRZ-0012345')->firstOrFail();
        $parcel->forceFill(['courier_accepted_at' => Carbon::now()])->save();
        $acceptedAt = $parcel->fresh()->courier_accepted_at;

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/shipments/{$parcel->tracking_number}/assign", [
                'courier_id' => $budi->id,
            ]);

        $response->assertOk();
        $this->assertEquals($acceptedAt, $parcel->fresh()->courier_accepted_at);
    }

    public function test_assignment_is_refused_with_a_readable_message_once_the_parcel_is_on_the_road(): void
    {
        $budi = Staff::where('email', 'budi@anteraja.id')->firstOrFail();
        $parcel = Package::where('tracking_number', 'AJ-FRZ-0012346')->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/shipments/{$parcel->tracking_number}/assign", [
                'courier_id' => $budi->id,
            ]);

        $response->assertStatus(422);
        $this->assertSame(
            'Paket tidak dapat ditugaskan ke kurir pada status saat ini.',
            $response->json('message')
        );

        $parcel->refresh();
        $this->assertSame(PackageStatus::IN_DELIVERY, $parcel->status);
    }
}
