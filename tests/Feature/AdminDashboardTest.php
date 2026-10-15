<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Package;
use App\Models\Product;
use App\Models\Staff;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Beranda admin: ringkasan otomatis, riwayat aktivitas, dan ekspor PDF.
 *
 * The numbers come from the same query the Laporan screen uses, so this covers
 * the seam between the two screens plus the PDF download.
 */
final class AdminDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    private Staff $courier;

    private Product $product;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['coldchain.n8n.enabled' => false]);

        $this->today = Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->format('Y-m-d');

        $this->admin = Staff::firstOrCreate(
            ['email' => 'admin.dashboard@anteraja.id'],
            [
                'name' => 'Admin Dashboard',
                'role' => StaffRole::ADMIN,
                'phone' => '081199000021',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        $this->courier = Staff::firstOrCreate(
            ['email' => 'courier.dashboard@anteraja.id'],
            [
                'name' => 'Kurir Dashboard',
                'role' => StaffRole::COURIER,
                'phone' => '081299000021',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        $this->product = Product::firstOrCreate(
            ['name' => 'Produk Dashboard 1 kg'],
            ['handling_notes' => 'Makanan beku. Suhu standar -18°C.']
        );
    }

    private function signIn(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.role', 'ADMIN');
    }

    /** Creates a shipment through the console and confirms it as the buyer. */
    private function createConfirmedShipment(string $phone): Package
    {
        $create = $this->postJson('/api/admin/shipments', [
            'buyer' => [
                'name' => 'Pembeli Dashboard',
                'phone' => $phone,
                'address' => 'Jl. Ringkasan No. 1, Tebet, Jakarta Selatan',
                'area' => 'Tebet, Jakarta Selatan',
                'latitude' => -6.229746,
                'longitude' => 106.848529,
            ],
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'scheduled_date' => $this->today,
            'window_start' => '13:00',
            'window_end' => '15:00',
            'weight_kg' => 1.0,
        ]);

        $create->assertCreated();
        $tracking = (string) $create->json('package.tracking_number');

        /** @var Package $package */
        $package = Package::where('tracking_number', $tracking)->firstOrFail();
        $this->assertSame(PackageStatus::AWAITING_CONFIRMATION, $package->status);

        $this->postJson("/api/client/{$package->client_token}/schedule/confirm")->assertOk();
        $this->assertSame(PackageStatus::CONFIRMED, $package->fresh()->status);

        return $package->fresh();
    }

    public function test_the_dashboard_returns_the_summary_and_the_audit_trail(): void
    {
        $this->signIn();
        $package = $this->createConfirmedShipment('081399990001');

        $response = $this->getJson("/api/admin/dashboard?date={$this->today}");

        $response->assertOk()
            ->assertJsonPath('date', $this->today)
            ->assertJsonPath('isToday', true);

        $summary = $response->json('summary');
        $this->assertGreaterThanOrEqual(1, $summary['total_shipments']);
        $this->assertArrayHasKey('in_process_count', $summary);
        $this->assertArrayHasKey('returned_count', $summary);
        $this->assertArrayHasKey('pending_decision_count', $summary);
        $this->assertArrayHasKey('decided_count', $summary);

        $this->assertIsString($response->json('narrative'));
        $this->assertNotSame('', trim((string) $response->json('narrative')));

        $activity = collect($response->json('activity'));
        $this->assertTrue(
            $activity->contains(fn (array $row): bool => $row['tracking'] === $package->tracking_number),
            'riwayat aktivitas memuat paket yang baru dibuat'
        );

        // The Inertia page renders for the signed-in admin.
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_the_dashboard_exports_a_pdf_recap(): void
    {
        $this->signIn();
        $this->createConfirmedShipment('081399990002');

        $response = $this->get("/api/admin/dashboard/export?date={$this->today}");

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_an_empty_day_reports_zeroes_and_no_activity(): void
    {
        $this->signIn();

        $past = Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->subDays(45)->format('Y-m-d');

        $response = $this->getJson("/api/admin/dashboard?date={$past}");

        $response->assertOk();
        $this->assertSame(0, $response->json('summary.total_shipments'));
        $this->assertSame(0, $response->json('paginator.total'));
        $this->assertSame([], $response->json('activity'));
    }

    public function test_only_an_admin_can_open_the_dashboard(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();

        $this->actingAs($this->courier)->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
