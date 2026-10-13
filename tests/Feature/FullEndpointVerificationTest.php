<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\Package;
use App\Models\Product;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class FullEndpointVerificationTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    private Staff $courier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Staff::where('role', StaffRole::ADMIN)->firstOrFail();
        $this->courier = Staff::where('role', StaffRole::COURIER)->where('email', 'budi@anteraja.id')->firstOrFail();
    }

    public function test_all_auth_endpoints(): void
    {
        // 1. POST /api/auth/login (Admin)
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'dimas@anteraja.id',
            'password' => 'password',
        ]);
        $loginRes->assertOk();

        // 2. GET /api/auth/me
        $meRes = $this->actingAs($this->admin)->getJson('/api/auth/me');
        $meRes->assertOk();

        // 3. POST /api/auth/logout
        $logoutRes = $this->actingAs($this->admin)->postJson('/api/auth/logout');
        $logoutRes->assertOk();
    }

    public function test_all_admin_endpoints(): void
    {
        $this->actingAs($this->admin);

        // 4. GET /api/admin/shipments
        $this->getJson('/api/admin/shipments')->assertOk();

        // 5. GET /api/admin/shipments/create
        $this->getJson('/api/admin/shipments/create')->assertOk();

        // 6. POST /api/admin/shipments
        $product = Product::firstOrFail();
        $todayStr = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        $storeShipmentRes = $this->postJson('/api/admin/shipments', [
            'buyer' => [
                'name' => 'Verifikasi Pembeli',
                'phone' => '081234567899',
                'address' => 'Jl. Verifikasi No. 10',
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'scheduled_date' => $todayStr,
            'window_start' => '13:00',
            'window_end' => '15:00',
            'weight_kg' => 1.5,
        ]);
        $storeShipmentRes->assertCreated();

        // 7. GET /api/admin/shipments/export
        $exportShipmentRes = $this->get('/api/admin/shipments/export');
        $exportShipmentRes->assertOk();

        // 8. GET /api/admin/shipments/{package}
        $this->getJson('/api/admin/shipments/AJ-FRZ-0012345')->assertOk();

        // 9. POST /api/admin/shipments/{package}/assign
        // Resi AJ-FRZ-0012347 is CONFIRMED in DemoDataSeeder
        $assignRes = $this->postJson('/api/admin/shipments/AJ-FRZ-0012347/assign', [
            'courier_id' => $this->courier->id,
        ]);
        $assignRes->assertOk();

        // 10. POST /api/admin/shipments/{package}/reschedule
        // Resi AJ-FRZ-0012364 is RESCHEDULE_REQUESTED in DemoDataSeeder
        $rescheduleRes = $this->postJson('/api/admin/shipments/AJ-FRZ-0012364/reschedule', [
            'scheduled_date' => Carbon::now('Asia/Jakarta')->addDays(2)->format('Y-m-d'),
            'scheduled_time_window' => '13:00 - 15:00',
        ]);
        $rescheduleRes->assertOk();

        // 11. GET /api/admin/exceptions
        $this->getJson('/api/admin/exceptions')->assertOk();

        // 12. GET /api/admin/exceptions/{package}
        $this->getJson('/api/admin/exceptions/AJ-FRZ-0012345')->assertOk();

        // 13. POST /api/admin/exceptions/{package}/decision
        $decisionRes = $this->postJson('/api/admin/exceptions/AJ-FRZ-0012345/decision', [
            'decision' => 'RETURN',
            'decision_reason' => 'Verifikasi keputusan retur',
            'quality_inspection_note' => 'Kemasan rusak saat transit.',
        ]);
        $decisionRes->assertOk();

        // 14. GET /api/admin/reports
        $this->getJson('/api/admin/reports')->assertOk();

        // 15. GET /api/admin/reports/export
        $this->get('/api/admin/reports/export')->assertOk();

        // 16. GET /api/admin/history
        $this->getJson('/api/admin/history')->assertOk();

        // 17. GET /api/admin/history/export
        $this->get('/api/admin/history/export')->assertOk();
    }

    public function test_all_courier_endpoints(): void
    {
        $this->actingAs($this->courier);

        // 18. GET /api/courier/tasks
        $this->getJson('/api/courier/tasks')->assertOk();

        // 19. GET /api/courier/tasks/{package}/distance
        $this->getJson('/api/courier/tasks/AJ-FRZ-0012351/distance?lat=-6.220000&lng=106.840000')->assertOk();

        // 20. POST /api/courier/tasks/{package}/accept
        $this->postJson('/api/courier/tasks/AJ-FRZ-0012351/accept')->assertOk();

        // 21. POST /api/courier/tasks/{package}/reject
        $pkgToReject = Package::where('tracking_number', 'AJ-FRZ-0012351')->firstOrFail();
        $pkgToReject->update(['courier_accepted_at' => null]);
        $this->postJson('/api/courier/tasks/AJ-FRZ-0012351/reject', ['reason' => 'Verifikasi tolak'])->assertOk();

        // 22. GET /api/courier/deliveries/{package}
        $this->getJson('/api/courier/deliveries/AJ-FRZ-0012354')->assertOk();

        // 23. POST /api/courier/deliveries/{package}/depart
        $this->postJson('/api/courier/deliveries/AJ-FRZ-0012354/depart', [
            'checklist' => ['packaging_damaged' => ['answer' => false]],
            'temperature_c' => -20.0,
        ]);

        // 24. POST /api/courier/deliveries/{package}/arrive
        $this->postJson('/api/courier/deliveries/AJ-FRZ-0012352/arrive', [
            'latitude' => -6.229746,
            'longitude' => 106.848529,
        ])->assertOk();

        // 25. GET /api/courier/deliveries/{package}/proof
        $this->getJson('/api/courier/deliveries/AJ-FRZ-0012353/proof')->assertOk();

        // 26. POST /api/courier/deliveries/{package}/proof
        Storage::fake('local');
        $this->postJson('/api/courier/deliveries/AJ-FRZ-0012353/proof', [
            'received_by' => 'Rina Putri',
            'recipient_relation' => 'SELF',
            'receipt_note' => 'Verifikasi serah terima',
            'photo' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertOk();

        // 27. GET /api/courier/deliveries/{package}/issue
        $this->getJson('/api/courier/deliveries/AJ-FRZ-0012352/issue')->assertOk();

        // 28. POST /api/courier/deliveries/{package}/issue
        $this->postJson('/api/courier/deliveries/AJ-FRZ-0012352/issue', [
            'issue_category' => 'kemasan_bocor',
            'description' => 'Verifikasi pelaporan kendala di lapangan',
            'photo' => UploadedFile::fake()->image('issue.jpg'),
        ])->assertOk();

        // 29. GET /api/courier/history
        $this->getJson('/api/courier/history')->assertOk();

        // 30. GET /api/courier/profile
        $this->getJson('/api/courier/profile')->assertOk();
    }

    public function test_all_client_endpoints(): void
    {
        // Sample packages from DemoDataSeeder
        $pkgSchedule = Package::where('tracking_number', 'AJ-FRZ-0012361')->firstOrFail();
        $pkgTracking = Package::where('tracking_number', 'AJ-FRZ-0012362')->firstOrFail();
        $pkgIssue = Package::where('tracking_number', 'AJ-FRZ-0012363')->firstOrFail();

        // 31. GET /api/client/{token} (Entry)
        $this->getJson("/api/client/{$pkgSchedule->client_token}")
            ->assertOk()
            ->assertJsonStructure(['screen', 'redirect_url']);

        // 32. GET /api/client/{token}/schedule (Schedule Show)
        $this->getJson("/api/client/{$pkgSchedule->client_token}/schedule")
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);

        // 33. POST /api/client/{token}/schedule/confirm (Schedule Confirm)
        $this->postJson("/api/client/{$pkgSchedule->client_token}/schedule/confirm")
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'redirect_url']);

        // 34. POST /api/client/{token}/schedule/reschedule (Schedule Reschedule)
        $buyer = Buyer::firstOrFail();
        $pkgReschedule = Package::create([
            'buyer_id' => $buyer->id,
            'created_by' => $this->admin->id,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'status' => PackageStatus::AWAITING_CONFIRMATION,
        ])->refresh();
        $pkgReschedule->scheduleConfirmations()->create(['response' => 'PENDING', 'requested_at' => now()]);

        $targetDate = Carbon::now('Asia/Jakarta')->addDays(3)->format('Y-m-d');
        $this->postJson("/api/client/{$pkgReschedule->client_token}/schedule/reschedule", [
            'date' => $targetDate,
            'slot' => '13:00-15:00',
        ])->assertOk()->assertJsonStructure(['success', 'message', 'redirect_url']);

        // 35. GET /api/client/{token}/tracking (Tracking Show)
        $this->getJson("/api/client/{$pkgTracking->client_token}/tracking")
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);

        // 36. GET /api/client/{token}/issue (Issue Show)
        $this->getJson("/api/client/{$pkgIssue->client_token}/issue")
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);

        // 37. POST /api/client/{token}/issue/resend (Replacement Request)
        $this->postJson("/api/client/{$pkgIssue->client_token}/issue/resend")
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'redirect_url', 'replacement_token']);
    }
}
