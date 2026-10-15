<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Package;
use App\Models\Product;
use App\Models\Staff;
use App\Models\Delivery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end journeys over the real HTTP surface.
 *
 * These drive the same requests the console and the buy-and-deliver flow make,
 * across all three surfaces (admin API, client token API, courier API) plus the
 * Inertia pages, so a regression anywhere between them fails here rather than on
 * a phone.
 */
final class AdminConsoleE2ETest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    private Staff $courier;

    private Product $product;

    private string $deliveryDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');

        // Today: the daily report caps its window at today, so a journey that
        // runs on any other day could not be reconciled against it.
        $this->deliveryDate = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        // refresh() after firstOrCreate: strict mode is on outside production, so
        // a freshly created row has no remember_token to read back on logout.
        $this->admin = Staff::firstOrCreate(
            ['email' => 'admin.e2e@anteraja.id'],
            [
                'name' => 'Admin E2E',
                'role' => StaffRole::ADMIN,
                'phone' => '081100002001',
                'password' => 'password',
                'is_active' => true,
            ]
        )->refresh();

        $this->courier = Staff::firstOrCreate(
            ['email' => 'courier.e2e@anteraja.id'],
            [
                'name' => 'Kurir E2E',
                'role' => StaffRole::COURIER,
                'phone' => '081200002001',
                'password' => 'password',
                'is_active' => true,
            ]
        )->refresh();

        $this->product = Product::firstOrCreate(
            ['name' => 'Produk E2E 1kg'],
            ['handling_notes' => 'Makanan beku. Suhu standar -18°C.']
        );
    }

    /** Signs in the console the way the browser does and returns the admin. */
    private function signIn(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.role', 'ADMIN');
    }

    /** Creates a shipment through the console and confirms it as the buyer. */
    private function createConfirmedShipment(string $buyerName, string $phone): Package
    {
        $create = $this->postJson('/api/admin/shipments', [
            'buyer' => [
                'name' => $buyerName,
                'phone' => $phone,
                'address' => 'Jl. End To End No. 1, Tebet, Jakarta Selatan',
                'area' => 'Tebet, Jakarta Selatan',
                'latitude' => -6.229746,
                'longitude' => 106.848529,
            ],
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'scheduled_date' => $this->deliveryDate,
            'window_start' => '13:00',
            'window_end' => '15:00',
            'weight_kg' => 1.5,
        ]);

        $create->assertCreated();
        $tracking = (string) $create->json('package.tracking_number');
        $this->assertNotSame('', $tracking);

        /** @var Package $package */
        $package = Package::where('tracking_number', $tracking)->firstOrFail();
        $this->assertSame(PackageStatus::AWAITING_CONFIRMATION, $package->status);

        // The buyer opens their link and confirms the window.
        $this->postJson("/api/client/{$package->client_token}/schedule/confirm")->assertOk();
        $this->assertSame(PackageStatus::CONFIRMED, $package->fresh()->status);

        return $package->fresh();
    }

    private function assignCourier(Package $package): void
    {
        $this->postJson("/api/admin/shipments/{$package->tracking_number}/assign", [
            'courier_id' => $this->courier->id,
        ])->assertOk();
    }

    public function test_the_delivery_journey_completes_end_to_end(): void
    {
        // 1. The console signs in against the JSON API.
        $this->signIn();
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $this->admin->email);

        // 2. The same session reaches the Inertia page.
        $this->get('/admin/shipment')->assertOk();
        $this->get('/admin/report')->assertOk();
        $this->get('/admin/history')->assertOk();

        // 3. The queue loads with the lists the filters need.
        $index = $this->getJson('/api/admin/shipments');
        $index->assertOk();
        $this->assertNotEmpty($index->json('statusOptions'));
        $this->assertNotEmpty($index->json('couriers'));

        // 4. Create a shipment, then have the buyer confirm it.
        $package = $this->createConfirmedShipment('Pembeli E2E', '081299990001');
        $tracking = $package->tracking_number;

        // 5. The queue shows it on its delivery day, waiting for a courier.
        $list = $this->getJson("/api/admin/shipments?q={$tracking}&date={$this->deliveryDate}");
        $list->assertOk();
        $rows = collect($list->json('shipments'));
        $this->assertContains($tracking, $rows->pluck('tracking')->all());
        $this->assertSame('assign_courier', $rows->firstWhere('tracking', $tracking)['availableAction']);

        // 6. Assign the courier from the dialog.
        $this->assignCourier($package);
        $this->assertSame(PackageStatus::COURIER_ASSIGNED, $package->fresh()->status);

        // 7. The courier works the task: accept, depart, arrive, hand over.
        $this->actingAs($this->courier)
            ->postJson("/api/courier/tasks/{$tracking}/accept")
            ->assertOk();

        $this->actingAs($this->courier)
            ->postJson("/api/courier/deliveries/{$tracking}/depart", [
                'checklist' => [
                    'packaging_damaged' => false,
                    'ice_cream_melting' => false,
                    'seal_opened' => false,
                    'buyer_rejected' => false,
                ],
                'temperature_c' => -19.5,
            ])
            ->assertOk();

        $this->actingAs($this->courier)
            ->postJson("/api/courier/deliveries/{$tracking}/arrive")
            ->assertOk();

        $this->actingAs($this->courier)
            ->post(
                "/api/courier/deliveries/{$tracking}/proof",
                [
                    'received_by' => 'Pembeli E2E',
                    'recipient_relation' => 'SELF',
                    'photo' => UploadedFile::fake()->image('bukti.jpg', 24, 24),
                ],
                ['Accept' => 'application/json']
            )
            ->assertOk();

        $this->assertSame(PackageStatus::RECEIVED, $package->fresh()->status);

        // 8. Back in the console, the detail shows the handover and its photo.
        $this->actingAs($this->admin);

        $detail = $this->getJson("/api/admin/shipments/{$tracking}");
        $detail->assertOk();
        $this->assertSame('Diterima', $detail->json('statusLabel'));
        $this->assertSame('success', $detail->json('tone'));
        $this->assertNotNull($detail->json('proofPhotoUrl'));
        $this->assertSame('Kurir E2E', $detail->json('package.courier.name'));

        // The proof photo the console renders has to be a real image, not a
        // dead link.
        $proofPath = (string) Delivery::where('package_id', $package->id)->value('photo_url');
        $this->assertNotSame('', $proofPath);
        $this->assertIsString($detail->json('proofPhotoUrl'));

        $proof = $this->get('/api/media/preview?path='.urlencode($proofPath));
        $proof->assertOk();
        $this->assertStringContainsString('image/', (string) $proof->headers->get('Content-Type'));

        // 9. The day's report counts it, and the archive lists it.
        $report = $this->getJson('/api/admin/reports?date='.$this->deliveryDate);
        $report->assertOk();
        $this->assertGreaterThanOrEqual(1, $report->json('kpis.total_shipments'));
        $this->assertGreaterThanOrEqual(1, $report->json('kpis.delivered_count'));

        $history = $this->getJson("/api/admin/history?q={$tracking}");
        $history->assertOk();
        $this->assertContains($tracking, collect($history->json('history'))->pluck('tracking')->all());

        // 10. Every export streams the CSV it advertises.
        foreach ([
            "/api/admin/shipments/export?date={$this->deliveryDate}",
            '/api/admin/reports/export?date='.$this->deliveryDate,
            "/api/admin/history/export?q={$tracking}",
        ] as $endpoint) {
            $export = $this->get($endpoint);
            $export->assertOk();
            $this->assertStringContainsString('RESI', $export->streamedContent());
        }

        // 11. Signing out ends the session for both surfaces.
        $this->postJson('/api/auth/logout')->assertOk();
        $this->getJson('/api/auth/me')->assertStatus(401);
        $this->get('/admin/shipment')->assertRedirect('/login');
    }

    public function test_the_kendala_journey_completes_end_to_end(): void
    {
        $this->signIn();

        $package = $this->createConfirmedShipment('Pembeli Kendala E2E', '081299990002');
        $tracking = $package->tracking_number;

        $this->assignCourier($package);

        $this->actingAs($this->courier)
            ->postJson("/api/courier/tasks/{$tracking}/accept")
            ->assertOk();

        // The courier finds the packaging leaking on arrival and reports it.
        $this->actingAs($this->courier)
            ->post(
                "/api/courier/deliveries/{$tracking}/issue",
                [
                    'issue_category' => 'kemasan_bocor',
                    'description' => 'Kemasan bocor saat paket tiba di lokasi penerima.',
                    'photo' => UploadedFile::fake()->image('kendala.jpg', 24, 24),
                ],
                ['Accept' => 'application/json']
            )
            ->assertOk();

        $this->assertSame(PackageStatus::ISSUE_REPORTED, $package->fresh()->status);

        // The console opens the kendala, sees the allowed decisions, and decides.
        $this->actingAs($this->admin);

        $detail = $this->getJson("/api/admin/exceptions/{$tracking}");
        $detail->assertOk();
        $this->assertSame('kemasan_bocor', $detail->json('category.key'));
        $this->assertSame('Menunggu Keputusan', $detail->json('resolutionStatus'));
        $this->assertContains('PROCEED', array_column($detail->json('allowedDecisions'), 'value'));
        $this->assertNotNull($detail->json('photoTemporaryUrl'));

        $this->postJson("/api/admin/exceptions/{$tracking}/decision", [
            'decision' => 'PROCEED',
            'decision_reason' => 'Produk masih layak konsumsi, pengantaran diteruskan dengan kompensasi.',
        ])->assertOk();

        $decided = $this->getJson("/api/admin/exceptions/{$tracking}");
        $decided->assertOk();
        $this->assertSame('PROCEED', $decided->json('issueReport.decision'));
        $this->assertSame('Dilanjutkan', $decided->json('resolutionStatus'));
        $this->assertSame('info', $decided->json('resolutionTone'));

        // The decision reaches the buyer's own view of the shipment.
        $clientView = $this->getJson("/api/client/{$package->fresh()->client_token}/issue");
        $clientView->assertOk();

        // And the day's report picks the kendala up.
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        $report = $this->getJson("/api/admin/reports?date={$today}");
        $report->assertOk();
        $this->assertGreaterThanOrEqual(1, $report->json('kpis.issue_count'));
        $this->assertContains('kemasan_bocor', $report->json('categoriesOnDate'));
    }
}
