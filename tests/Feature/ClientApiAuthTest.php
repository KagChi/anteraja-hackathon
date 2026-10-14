<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Packages\CreatePackage;
use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\ConditionCheck;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use App\Models\Staff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class ClientApiAuthTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    private Staff $courier;

    private Buyer $buyer;

    private Buyer $otherBuyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('client-auth');

        $this->admin = Staff::firstOrCreate(
            ['email' => 'admin.authclient@anteraja.id'],
            [
                'name' => 'Admin Client Auth',
                'role' => StaffRole::ADMIN,
                'phone' => '+6281100000088',
                'password' => 'secret123',
                'is_active' => true,
            ]
        );

        $this->courier = Staff::firstOrCreate(
            ['email' => 'courier.authclient@anteraja.id'],
            [
                'name' => 'Courier Client Auth',
                'role' => StaffRole::COURIER,
                'phone' => '+6281200000088',
                'password' => 'secret123',
                'is_active' => true,
            ]
        );

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => '+6281277770001'],
            [
                'name' => 'Rina Pembeli',
                'password' => Hash::make('password123'),
                'phone_verified_at' => now(),
                'is_active' => true,
                'address' => 'Jl. Tebet No. 1, Jakarta Selatan',
                'area' => 'Tebet',
            ]
        );

        $this->otherBuyer = Buyer::firstOrCreate(
            ['phone' => '+6281277770002'],
            [
                'name' => 'Budi Pembeli Lain',
                'password' => Hash::make('password123'),
                'phone_verified_at' => now(),
                'is_active' => true,
                'address' => 'Jl. Sudirman No. 2, Jakarta Pusat',
                'area' => 'Sudirman',
            ]
        );

        $this->product = Product::firstOrCreate(
            ['name' => 'Wagyu Beef Slice 500g'],
            [
                'handling_notes' => 'Suhu -18°C beku terjaga.',
            ]
        );
    }

    private function createPackageFor(Buyer $buyer, array $attributes = []): Package
    {
        /** @var Package $pkg */
        $pkg = Package::create(array_merge([
            'buyer_id' => $buyer->id,
            'created_by' => $this->admin->id,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'status' => PackageStatus::AWAITING_CONFIRMATION,
            'weight_kg' => 1.50,
        ], $attributes))->refresh();

        PackageItem::create([
            'package_id' => $pkg->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        return $pkg;
    }

    public function test_buyer_can_login_with_phone_and_password(): void
    {
        $response = $this->postJson('/api/client/auth/login', [
            'phone' => '081277770001', // Local format
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_at',
                    'buyer' => ['id', 'name', 'phone', 'address', 'area'],
                ],
            ]);

        $this->assertSame('+6281277770001', $response->json('data.buyer.phone'));
    }

    public function test_login_returns_generic_error_for_invalid_credentials(): void
    {
        // 1. Wrong password
        $res1 = $this->postJson('/api/client/auth/login', [
            'phone' => '081277770001',
            'password' => 'wrong_password',
        ]);
        $res1->assertStatus(401)
            ->assertJson([
                'message' => 'Nomor atau password salah.',
                'code' => 'INVALID_CREDENTIALS',
            ]);

        // 2. Unregistered phone
        $res2 = $this->postJson('/api/client/auth/login', [
            'phone' => '089999999999',
            'password' => 'any_password',
        ]);
        $res2->assertStatus(401)
            ->assertJson([
                'message' => 'Nomor atau password salah.',
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_inactive_buyer_cannot_login(): void
    {
        $this->buyer->update(['is_active' => false]);

        $response = $this->postJson('/api/client/auth/login', [
            'phone' => '081277770001',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'ACCOUNT_INACTIVE',
            ]);
    }

    public function test_client_auth_rate_limiting_triggers_429(): void
    {
        $phone = '081299990099';
        $ip = '192.168.1.100';

        for ($i = 0; $i < 5; $i++) {
            $res = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/client/auth/login', [
                    'phone' => $phone,
                    'password' => 'wrong_password',
                ]);
            $res->assertStatus(401);
        }

        // 6th attempt should be blocked with 429
        $resBlocked = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/client/auth/login', [
                'phone' => $phone,
                'password' => 'wrong_password',
            ]);

        $resBlocked->assertStatus(429)
            ->assertJson([
                'code' => 'TOO_MANY_REQUESTS',
            ]);
    }

    public function test_buyer_can_logout_and_revokes_token(): void
    {
        $token = $this->buyer->createToken('client-mobile', ['client'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/client/auth/logout');
        $response->assertOk()
            ->assertJson([
                'code' => 'LOGGED_OUT',
            ]);

        // Attempting to use the revoked token should fail with 401
        $this->app['auth']->forgetGuards();
        $resAfter = $this->withToken($token)->getJson('/api/client/me');
        $resAfter->assertStatus(401);
    }

    public function test_sanctum_ability_isolation(): void
    {
        // 1. Staff token cannot access client authenticated routes
        $staffToken = $this->admin->createToken('staff-token', ['staff'])->plainTextToken;
        $resStaff = $this->withToken($staffToken)->getJson('/api/client/me');
        $resStaff->assertStatus(403);

        // 2. Buyer token cannot access admin or courier routes
        $this->app['auth']->forgetGuards();
        $buyerToken = $this->buyer->createToken('client-mobile', ['client'])->plainTextToken;
        $resBuyerOnAdmin = $this->withToken($buyerToken)->getJson('/api/admin/shipments');
        $this->assertTrue(in_array($resBuyerOnAdmin->status(), [401, 403], true));
    }

    public function test_buyer_profile_endpoint_returns_stats_and_support(): void
    {
        $this->createPackageFor($this->buyer, ['status' => PackageStatus::CONFIRMED]);
        $this->createPackageFor($this->buyer, ['status' => PackageStatus::RECEIVED]);

        $token = $this->buyer->createToken('client-mobile', ['client'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/client/me');
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Rina Pembeli',
                    'phone' => '+6281277770001',
                    'stats' => [
                        'active_count' => 1,
                        'completed_count' => 1,
                        'on_time_rate' => '96%',
                    ],
                ],
            ]);
    }

    public function test_buyer_orders_list_with_tabs_and_counts(): void
    {
        // Active package
        $pkg1 = $this->createPackageFor($this->buyer, ['status' => PackageStatus::IN_DELIVERY]);
        // Completed package
        $pkg2 = $this->createPackageFor($this->buyer, ['status' => PackageStatus::RECEIVED]);
        // Issue package
        $pkg3 = $this->createPackageFor($this->buyer, ['status' => PackageStatus::ISSUE_REPORTED]);

        $token = $this->buyer->createToken('client-mobile', ['client'])->plainTextToken;

        // 1. Default tab (active)
        $resActive = $this->withToken($token)->getJson('/api/client/orders?tab=active');
        $resActive->assertOk();
        $dataActive = $resActive->json('data');
        $this->assertNotEmpty($dataActive);
        $this->assertSame(2, $resActive->json('meta.tab_counts.active'));
        $this->assertSame(1, $resActive->json('meta.tab_counts.completed'));
        $this->assertSame(1, $resActive->json('meta.tab_counts.issue'));
        $this->assertSame(3, $resActive->json('meta.tab_counts.total'));

        // 2. Completed tab
        $resCompleted = $this->withToken($token)->getJson('/api/client/orders?tab=completed');
        $resCompleted->assertOk();
        $this->assertCount(1, $resCompleted->json('data'));
        $this->assertSame($pkg2->tracking_number, $resCompleted->json('data.0.resi'));

        // 3. Issue tab
        $resIssue = $this->withToken($token)->getJson('/api/client/orders?tab=issue');
        $resIssue->assertOk();
        $this->assertCount(1, $resIssue->json('data'));
        $this->assertSame($pkg3->tracking_number, $resIssue->json('data.0.resi'));
    }

    public function test_order_ownership_isolation_returns_404_for_other_buyer(): void
    {
        $pkgOther = $this->createPackageFor($this->otherBuyer);
        $token = $this->buyer->createToken('client-mobile', ['client'])->plainTextToken;

        // Buyer cannot access other buyer's package
        $resShow = $this->withToken($token)->getJson("/api/client/orders/{$pkgOther->tracking_number}");
        $resShow->assertStatus(404)
            ->assertJson([
                'code' => 'PACKAGE_NOT_FOUND',
            ]);

        $resTracking = $this->withToken($token)->getJson("/api/client/orders/{$pkgOther->tracking_number}/tracking");
        $resTracking->assertStatus(404);
    }

    public function test_guest_link_resolution_returns_json_descriptor(): void
    {
        $pkg = $this->createPackageFor($this->buyer, ['status' => PackageStatus::AWAITING_CONFIRMATION]);

        $response = $this->getJson("/api/guest/{$pkg->client_token}");
        $response->assertOk()
            ->assertJson([
                'next_screen' => 'schedule',
                'order' => [
                    'resi' => $pkg->tracking_number,
                ],
            ]);
    }

    public function test_guest_account_claim_sets_password_and_second_claim_returns_409(): void
    {
        // Create an unverified buyer with null password
        $unclaimedBuyer = Buyer::create([
            'name' => 'Klaim User',
            'phone' => '+6281255554444',
            'password' => null,
            'phone_verified_at' => null,
            'is_active' => true,
            'address' => 'Jl. Klaim No. 5',
            'area' => 'Klaim Area',
        ]);

        $pkg = $this->createPackageFor($unclaimedBuyer);

        // First claim -> 200 OK
        $resClaim = $this->postJson("/api/guest/{$pkg->client_token}/claim", [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $resClaim->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'buyer' => ['id', 'phone'],
                ],
            ]);

        $unclaimedBuyer->refresh();
        $this->assertNotNull($unclaimedBuyer->password);
        $this->assertNotNull($unclaimedBuyer->phone_verified_at);

        // Second claim -> 409 Conflict
        $resSecondClaim = $this->postJson("/api/guest/{$pkg->client_token}/claim", [
            'password' => 'anotherpass123',
            'password_confirmation' => 'anotherpass123',
        ]);

        $resSecondClaim->assertStatus(409)
            ->assertJson([
                'code' => 'ACCOUNT_ALREADY_CLAIMED',
            ]);
    }

    public function test_create_package_reuses_existing_buyer_by_phone(): void
    {
        /** @var CreatePackage $action */
        $action = app(CreatePackage::class);

        $initialBuyerCount = Buyer::count();

        // Pass buyer data with the same phone as existing $this->buyer
        $pkg = $action->execute([
            'buyer' => [
                'name' => 'Rina Duplicate Test',
                'phone' => '081277770001',
                'address' => 'Alamat Berbeda',
            ],
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'scheduled_date' => now()->addDays(2)->format('Y-m-d'),
            'window_start' => '13:00',
            'window_end' => '15:00',
            'weight_kg' => 1.0,
        ], $this->admin);

        $this->assertSame($this->buyer->id, $pkg->buyer_id);
        $this->assertSame($initialBuyerCount, Buyer::count(), 'Buyer count should not increase because existing buyer was reused');
    }

    public function test_privacy_sentinel_never_leaked_in_json(): void
    {
        $pkg = $this->createPackageFor($this->buyer, ['status' => PackageStatus::RETURNED]);

        $chk = ConditionCheck::create([
            'package_id' => $pkg->id,
            'courier_id' => $this->courier->id,
            'is_normal' => false,
            'temperature_c' => -18.0,
            'notes' => 'SENTINEL_RAW_NOTE_TOP_SECRET',
        ]);

        IssueReport::create([
            'check_id' => $chk->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR,
            'description' => 'SENTINEL_COURIER_DESC_TOP_SECRET',
            'photo_url' => 'https://anteraja.id/SENTINEL_PHOTO_SECRET.jpg',
            'decision' => IssueDecision::RETURN,
            'decision_reason' => 'SENTINEL_DECISION_REASON_TOP_SECRET',
            'quality_inspection_note' => 'Pemeriksaan kualitas cold chain aman.',
            'decided_by' => $this->admin->id,
            'decided_at' => now(),
            'reported_at' => now()->subHour(),
        ]);

        $token = $this->buyer->createToken('client-mobile', ['client'])->plainTextToken;

        // 1. Order detail
        $resOrder = $this->withToken($token)->getJson("/api/client/orders/{$pkg->tracking_number}");
        $resOrder->assertOk();
        $this->assertStringNotContainsString('SENTINEL_DECISION_REASON_TOP_SECRET', $resOrder->getContent());
        $this->assertStringNotContainsString('SENTINEL_COURIER_DESC_TOP_SECRET', $resOrder->getContent());

        // 2. Tracking view
        $resTracking = $this->withToken($token)->getJson("/api/client/orders/{$pkg->tracking_number}/tracking");
        $resTracking->assertOk();
        $this->assertStringNotContainsString('SENTINEL_DECISION_REASON_TOP_SECRET', $resTracking->getContent());
        $this->assertStringNotContainsString('SENTINEL_COURIER_DESC_TOP_SECRET', $resTracking->getContent());
        $this->assertStringNotContainsString($this->courier->phone, $resTracking->getContent()); // Not in delivery -> phone hidden

        // 3. Issue view
        $resIssue = $this->withToken($token)->getJson("/api/client/orders/{$pkg->tracking_number}/issue");
        $resIssue->assertOk();
        $this->assertStringNotContainsString('SENTINEL_DECISION_REASON_TOP_SECRET', $resIssue->getContent());
        $this->assertStringNotContainsString('SENTINEL_PHOTO_SECRET.jpg', $resIssue->getContent());
    }
}
