<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IssueCategory;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\ConditionCheck;
use App\Models\Delivery;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use App\Models\Staff;
use App\Support\MediaStorage;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The admin console reads its data from the JSON API, so this covers the seam:
 * the web pages that must send a guest to the login screen, and the extra
 * fields the pages need on top of what the endpoints already returned.
 */
final class AdminConsoleWiringTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    private Staff $courier;

    private Buyer $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');

        $this->admin = Staff::firstOrCreate(
            ['email' => 'admin.console@anteraja.id'],
            [
                'name' => 'Admin Console',
                'role' => StaffRole::ADMIN,
                'phone' => '081100000001',
                'password' => 'secret',
                'is_active' => true,
            ]
        );

        $this->courier = Staff::firstOrCreate(
            ['email' => 'courier.console@anteraja.id'],
            [
                'name' => 'Kurir Console',
                'role' => StaffRole::COURIER,
                'phone' => '081200000001',
                'password' => 'secret',
                'is_active' => true,
            ]
        );

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => '081300000001'],
            [
                'name' => 'Pembeli Console',
                'address' => 'Jl. Console No. 1, Jakarta Selatan',
                'area' => 'Tebet, Jakarta Selatan',
            ]
        );

        $this->product = Product::firstOrCreate(
            ['name' => 'Nugget Console 500g'],
            ['handling_notes' => 'Suhu -18°C beku terjaga.']
        );
    }

    private function createPackage(array $attributes = []): Package
    {
        /** @var Package $package */
        $package = Package::create(array_merge([
            'buyer_id' => $this->buyer->id,
            'created_by' => $this->admin->id,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'status' => PackageStatus::AWAITING_CONFIRMATION,
            'weight_kg' => 1.25,
        ], $attributes))->refresh();

        PackageItem::create([
            'package_id' => $package->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        return $package;
    }

    public function test_the_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            // The console authenticates over XHR with a session cookie, so the
            // token axios echoes back has to be issued with the page.
            ->assertCookie('XSRF-TOKEN')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    public function test_the_api_group_validates_the_csrf_token(): void
    {
        // Middleware groups are registered onto the router when the HTTP kernel
        // boots, which only happens once a request has been made.
        $this->get('/login');

        $router = app('router');
        $middleware = $router->gatherRouteMiddleware($router->getRoutes()->getByName('api.auth.login'));

        $this->assertContains(ValidateCsrfToken::class, $middleware);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        foreach (['/admin/shipment', '/admin/report'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_an_admin_reaches_the_console_pages(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/shipment')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/Shipment'));

        $this->actingAs($this->admin)
            ->get('/admin/report')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/Report'));
    }

    public function test_a_courier_is_refused_the_console(): void
    {
        $this->actingAs($this->courier)->get('/admin/shipment')->assertForbidden();
    }

    public function test_the_shipment_index_carries_the_filter_lists(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/shipments')
            ->assertOk()
            ->assertJsonStructure([
                'shipments',
                'paginator' => ['total', 'per_page', 'current_page', 'last_page'],
                'couriers',
                'statusOptions',
                'filters' => ['q', 'status', 'courier', 'schedule', 'date', 'per_page'],
            ]);
    }

    public function test_the_date_filter_names_one_delivery_day(): void
    {
        $today = Carbon::now('Asia/Jakarta');
        $tomorrow = $today->copy()->addDay();

        $package = $this->createPackage([
            'scheduled_at' => $tomorrow->copy()->setTime(10, 0)->setTimezone('UTC'),
            'scheduled_end_at' => $tomorrow->copy()->setTime(12, 0)->setTimezone('UTC'),
        ]);

        $forTomorrow = $this->actingAs($this->admin)->getJson(
            "/api/admin/shipments?q={$package->tracking_number}&date=".$tomorrow->format('Y-m-d')
        );
        $forTomorrow->assertOk();
        $this->assertContains($package->tracking_number, array_column($forTomorrow->json('shipments'), 'tracking'));

        // The day request must replace the relative window, not stack on top of
        // it, or every day other than today would come back empty.
        $forToday = $this->actingAs($this->admin)->getJson(
            "/api/admin/shipments?q={$package->tracking_number}&date=".$today->format('Y-m-d')
        );
        $forToday->assertOk();
        $this->assertNotContains($package->tracking_number, array_column($forToday->json('shipments'), 'tracking'));
    }

    public function test_the_shipment_detail_carries_the_proof_photos_and_labels(): void
    {
        $package = $this->createPackage(['status' => PackageStatus::ISSUE_REPORTED]);

        Delivery::create([
            'package_id' => $package->id,
            'received_by' => 'Pembeli Console',
            'recipient_relation' => 'Penerima Sendiri',
            'photo_url' => 'proofs/2026/10/handover.jpg',
        ]);

        $check = ConditionCheck::create([
            'package_id' => $package->id,
            'courier_id' => $this->courier->id,
            'is_normal' => false,
            'temperature_c' => -8.0,
            'notes' => 'Kemasan luar basah.',
            'checked_at' => now(),
        ]);

        IssueReport::create([
            'check_id' => $check->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR,
            'description' => 'Kemasan bocor saat transit.',
            'photo_url' => 'proofs/2026/10/issue.jpg',
            'reported_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/shipments/{$package->tracking_number}");

        $response->assertOk()->assertJsonStructure([
            'package',
            'timeline',
            'client_url',
            'scheduleLabel',
            'scheduledDate',
            'statusLabel',
            'tone',
            'proofPhotoUrl',
            'issuePhotoUrl',
        ]);

        $this->assertSame('Kendala (Kemasan bocor)', $response->json('statusLabel'));
        $this->assertNotNull($response->json('proofPhotoUrl'));
        $this->assertNotNull($response->json('issuePhotoUrl'));
    }

    public function test_the_exception_detail_carries_the_decisions_and_resolution(): void
    {
        $package = $this->createPackage(['status' => PackageStatus::ISSUE_REPORTED]);

        $check = ConditionCheck::create([
            'package_id' => $package->id,
            'courier_id' => $this->courier->id,
            'is_normal' => false,
            'temperature_c' => -18.0,
            'checklist' => ['packaging_damaged' => ['answer' => true, 'detail' => 'Segel bocor']],
            'checked_at' => now(),
        ]);

        IssueReport::create([
            'check_id' => $check->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR,
            'description' => 'Kemasan bocor saat transit.',
            'photo_url' => 'proofs/2026/10/issue.jpg',
            'reported_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/exceptions/{$package->tracking_number}");

        $response->assertOk()->assertJsonStructure([
            'package',
            'issueReport',
            'conditionCheck',
            'category' => ['key', 'label'],
            'allowedDecisions' => [['value', 'label', 'helpText']],
            'checklist',
            'timeline',
            'reportedAtFormatted',
            'photoTemporaryUrl',
            'resolutionStatus',
            'resolutionTone',
            'statusLabel',
            'tone',
        ]);

        $this->assertSame(IssueCategory::KEMASAN_BOCOR->label(), $response->json('category.label'));
        $this->assertSame('Menunggu Keputusan', $response->json('resolutionStatus'));
        $this->assertContains('RETURN', array_column($response->json('allowedDecisions'), 'value'));
    }

    public function test_the_create_master_data_includes_the_courier_roster(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/shipments/create')
            ->assertOk()
            ->assertJsonStructure(['products', 'buyers', 'couriers' => [['id', 'name']]]);
    }

    public function test_the_media_preview_serves_only_proof_paths(): void
    {
        // Guests cannot read stored photos.
        $this->get('/api/media/preview?path='.urlencode('proofs/2026/10/x.jpg'))->assertUnauthorized();

        // Nor can an admin walk out of the media folders or read unrelated shapes.
        foreach (['../../.env', 'proofs/2026/10/../../../.env', '/etc/passwd', 'proofs/x.jpg', ''] as $path) {
            $this->actingAs($this->admin)
                ->get('/api/media/preview?path='.urlencode($path))
                ->assertNotFound();
        }

        // A well-shaped path that has no file behind it is still a 404.
        $this->actingAs($this->admin)
            ->get('/api/media/preview?path='.urlencode('proofs/2026/10/missing.jpg'))
            ->assertNotFound();

        // And a real stored photo is served as an image.
        Storage::disk('local')->put('proofs/2026/10/handover.jpg', 'jpeg-bytes');

        $served = $this->actingAs($this->admin)->get('/api/media/preview?path='.urlencode('proofs/2026/10/handover.jpg'));
        $served->assertOk();
        $this->assertStringContainsString('image/', (string) $served->headers->get('Content-Type'));
    }

    public function test_a_stored_photo_resolves_to_the_preview_route(): void
    {
        // The faked disk the other tests use does support temporary URLs, but the
        // real local disk does not: its config has no url and no signing, so
        // MediaStorage falls back to the preview route. Pin that, because the
        // console renders whatever this hands out.
        config(['filesystems.disks.local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ]]);
        Storage::forgetDisk('local');

        $url = MediaStorage::temporaryUrl('proofs/2026/10/handover.jpg');

        $this->assertStringContainsString('/api/media/preview', $url);

        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        $this->assertSame('/api/media/preview', $path);
        $this->assertStringContainsString('path=proofs%2F2026%2F10%2Fhandover.jpg', $query);
    }

    public function test_the_report_endpoint_answers(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonStructure([
                'date',
                'dateLabel',
                'kpis' => ['total_shipments', 'delivered_count', 'issue_count', 'success_rate_pct'],
                'issues',
                'categoriesOnDate',
                'paginator' => ['total', 'current_page', 'last_page'],
            ]);
    }
}
