<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\ConditionCheck;
use App\Models\IssueReport;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use App\Models\StatusLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class ClientFlowTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    private Staff $courier;

    private Buyer $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Staff::firstOrCreate(
            ['email' => 'admin.clientflow@anteraja.id'],
            [
                'name' => 'Admin Test',
                'role' => StaffRole::ADMIN,
                'phone' => '081100000099',
                'password' => 'secret',
                'is_active' => true,
            ]
        );

        $this->courier = Staff::firstOrCreate(
            ['email' => 'courier.clientflow@anteraja.id'],
            [
                'name' => 'Budi Courier',
                'role' => StaffRole::COURIER,
                'phone' => '081200000099',
                'password' => 'secret',
                'is_active' => true,
            ]
        );

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => '081300000099'],
            [
                'name' => 'Siti Pembeli',
                'address' => 'Jl. Tebet No. 10, Jakarta Selatan',
                'area' => 'Tebet, Jakarta Selatan',
            ]
        );

        $this->product = Product::firstOrCreate(
            ['name' => 'Frozen Wagyu 500g'],
            [
                'handling_notes' => 'Suhu -18°C beku terjaga.',
            ]
        );
    }

    private function createPackage(array $attributes = []): Package
    {
        /** @var Package $pkg */
        $pkg = Package::create(array_merge([
            'buyer_id' => $this->buyer->id,
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

    public function test_client_security_headers_are_present(): void
    {
        $pkg = $this->createPackage();

        $response = $this->getJson("/api/client/{$pkg->client_token}");
        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);

        // Security headers also on 404
        $res404 = $this->getJson('/api/client/'.str_repeat('f', 64));
        $res404->assertStatus(404);
        $res404->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $res404->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_data_privacy_allow_list_never_leaks_forbidden_fields(): void
    {
        $pkg = $this->createPackage(['status' => PackageStatus::RETURNED]);

        $chk = ConditionCheck::create([
            'package_id' => $pkg->id,
            'courier_id' => $this->courier->id,
            'is_normal' => false,
            'temperature_c' => -18.0,
            'notes' => 'SENTINEL_RAW_CHECK_NOTE_SECRET',
        ]);

        IssueReport::create([
            'check_id' => $chk->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR,
            'description' => 'SENTINEL_RAW_COURIER_DESCRIPTION_SECRET',
            'photo_url' => 'https://anteraja.id/SENTINEL_SECRET_PHOTO.jpg',
            'decision' => IssueDecision::RETURN,
            'decision_reason' => 'SENTINEL_INTERNAL_DECISION_REASON_SECRET',
            'quality_inspection_note' => 'Pemeriksaan kualitas cold chain aman.',
            'decided_by' => $this->admin->id,
            'decided_at' => now(),
            'reported_at' => now()->subHour(),
        ]);

        $otherPkg = $this->createPackage();

        // 1. Test issue view JSON
        $resIssue = $this->getJson("/api/client/{$pkg->client_token}/issue");
        $resIssue->assertOk();
        $content = $resIssue->getContent();

        $this->assertStringNotContainsString('SENTINEL_RAW_COURIER_DESCRIPTION_SECRET', $content);
        $this->assertStringNotContainsString('SENTINEL_SECRET_PHOTO.jpg', $content);
        $this->assertStringNotContainsString('SENTINEL_INTERNAL_DECISION_REASON_SECRET', $content);
        $this->assertStringNotContainsString('SENTINEL_RAW_CHECK_NOTE_SECRET', $content);
        $this->assertStringNotContainsString($otherPkg->client_token, $content);

        // 2. Test tracking view JSON
        $resTracking = $this->getJson("/api/client/{$pkg->client_token}/tracking");
        $resTracking->assertOk();
        $trackingContent = $resTracking->getContent();

        $this->assertStringNotContainsString('SENTINEL_INTERNAL_DECISION_REASON_SECRET', $trackingContent);
        $this->assertStringNotContainsString($this->courier->phone, $trackingContent); // Not in delivery -> phone hidden
    }

    public function test_entry_resolves_appropriate_screen_and_redirect_url(): void
    {
        // 1. Awaiting confirmation -> schedule
        $pkg1 = $this->createPackage(['status' => PackageStatus::AWAITING_CONFIRMATION]);
        $res1 = $this->getJson("/api/client/{$pkg1->client_token}");
        $res1->assertOk()
            ->assertJson([
                'screen' => 'schedule',
            ]);
        $this->assertStringContainsString('/schedule', $res1->json('redirect_url'));

        // 2. In delivery -> tracking
        $pkg2 = $this->createPackage(['status' => PackageStatus::IN_DELIVERY]);
        $res2 = $this->getJson("/api/client/{$pkg2->client_token}");
        $res2->assertOk()
            ->assertJson([
                'screen' => 'tracking',
            ]);
        $this->assertStringContainsString('/tracking', $res2->json('redirect_url'));

        // 3. Issue reported with report -> issue
        $pkg3 = $this->createPackage(['status' => PackageStatus::ISSUE_REPORTED]);
        $chk = ConditionCheck::create([
            'package_id' => $pkg3->id,
            'courier_id' => $this->courier->id,
            'is_normal' => false,
            'temperature_c' => -18.0,
        ]);
        IssueReport::create([
            'check_id' => $chk->id,
            'issue_category' => IssueCategory::SUHU_NAIK,
            'description' => 'Suhu meningkat di atas ambang batas',
            'reported_at' => now(),
        ]);

        $res3 = $this->getJson("/api/client/{$pkg3->client_token}");
        $res3->assertOk()
            ->assertJson([
                'screen' => 'issue',
            ]);
        $this->assertStringContainsString('/issue', $res3->json('redirect_url'));
    }

    public function test_tracking_returns_formatted_data_and_client_status_labels(): void
    {
        $pkg = $this->createPackage([
            'status' => PackageStatus::IN_DELIVERY,
            'courier_id' => $this->courier->id,
            'cooler_box_code' => 'Box #04',
        ]);

        ConditionCheck::create([
            'package_id' => $pkg->id,
            'courier_id' => $this->courier->id,
            'is_normal' => true,
            'temperature_c' => -18.2,
            'checked_at' => now()->subMinutes(10),
        ]);

        $res = $this->getJson("/api/client/{$pkg->client_token}/tracking");
        $res->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'tracking_number' => $pkg->tracking_number,
                    'status' => 'IN_DELIVERY',
                    'status_label' => 'Sedang Diantar',
                    'buyer_name' => 'Siti Pembeli',
                    'courier_name' => 'Budi Courier',
                    'can_contact_courier' => true,
                    'courier_phone' => $this->courier->phone,
                ],
            ]);

        $data = $res->json('data');
        $this->assertNotNull($data['cold_chain']);
        $this->assertSame('Optimal', $data['cold_chain']['temperature_state']);
        $this->assertSame('Terjaga', $data['cold_chain']['chain_state']);
    }

    public function test_schedule_show_returns_cargo_items_and_weight(): void
    {
        $pkg = $this->createPackage(['status' => PackageStatus::AWAITING_CONFIRMATION]);
        ScheduleConfirmation::create([
            'package_id' => $pkg->id,
            'response' => 'PENDING',
            'requested_at' => now(),
        ]);

        $res = $this->getJson("/api/client/{$pkg->client_token}/schedule");
        $res->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'tracking_number' => $pkg->tracking_number,
                    'status_label' => 'Menunggu Konfirmasi',
                    'can_action' => true,
                    'total_weight_kg' => 1.5,
                ],
            ]);

        $items = $res->json('data.cargo_items');
        $this->assertCount(1, $items);
        $this->assertSame('Frozen Wagyu 500g', $items[0]['name']);
        $this->assertSame('2 Paket', $items[0]['quantity_label']);
    }

    public function test_schedule_confirm_transitions_status_with_null_actor(): void
    {
        $pkg = $this->createPackage(['status' => PackageStatus::AWAITING_CONFIRMATION]);
        ScheduleConfirmation::create([
            'package_id' => $pkg->id,
            'response' => 'PENDING',
            'requested_at' => now(),
        ]);

        $res = $this->postJson("/api/client/{$pkg->client_token}/schedule/confirm");
        $res->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Jadwal pengantaran berhasil dikonfirmasi.',
            ]);

        $this->assertSame(PackageStatus::CONFIRMED, $pkg->fresh()->status);

        // Verify status_logs.changed_by is NULL
        $latestLog = StatusLog::where('package_id', $pkg->id)
            ->where('to_status', PackageStatus::CONFIRMED)
            ->first();

        $this->assertNotNull($latestLog);
        $this->assertNull($latestLog->changed_by);

        // Idempotent double-tap
        $res2 = $this->postJson("/api/client/{$pkg->client_token}/schedule/confirm");
        $res2->assertOk();
    }

    public function test_schedule_reschedule_validates_and_transitions_status(): void
    {
        $pkg = $this->createPackage(['status' => PackageStatus::AWAITING_CONFIRMATION]);
        ScheduleConfirmation::create([
            'package_id' => $pkg->id,
            'response' => 'PENDING',
            'requested_at' => now(),
        ]);

        $targetDate = now('Asia/Jakarta')->addDays(3)->format('Y-m-d');

        $res = $this->postJson("/api/client/{$pkg->client_token}/schedule/reschedule", [
            'date' => $targetDate,
            'slot' => '13:00-15:00',
        ]);

        $res->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Permintaan perubahan jadwal berhasil dikirim.',
            ]);

        $this->assertSame(PackageStatus::RESCHEDULE_REQUESTED, $pkg->fresh()->status);

        // Notification queued for active admins
        $notif = Notification::where('package_id', $pkg->id)
            ->where('recipient_type', RecipientType::ADMIN)
            ->where('recipient_id', $this->admin->id)
            ->where('template', 'reschedule_requested')
            ->first();

        $this->assertNotNull($notif);
    }

    public function test_issue_replacement_creates_child_package_and_handles_double_tap(): void
    {
        $pkg = $this->createPackage(['status' => PackageStatus::RETURNED]);

        $chk = ConditionCheck::create([
            'package_id' => $pkg->id,
            'courier_id' => $this->courier->id,
            'is_normal' => false,
            'temperature_c' => -18.0,
        ]);

        IssueReport::create([
            'check_id' => $chk->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR,
            'description' => 'Kemasan rusak saat transit',
            'decision' => IssueDecision::RETURN,
            'decision_reason' => 'Retur karena kemasan rusak',
            'decided_by' => $this->admin->id,
            'decided_at' => now(),
            'reported_at' => now()->subHours(2),
        ]);

        $res = $this->postJson("/api/client/{$pkg->client_token}/issue/resend");
        $res->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $replacementToken = $res->json('replacement_token');
        $this->assertNotNull($replacementToken);

        $child = Package::where('resend_of_package_id', $pkg->id)->first();
        $this->assertNotNull($child);
        $this->assertSame(PackageStatus::AWAITING_CONFIRMATION, $child->status);
        $this->assertSame($this->buyer->id, $child->buyer_id);
        $this->assertCount(1, $child->items);

        // Double-tap returns the existing child package without error
        $res2 = $this->postJson("/api/client/{$pkg->client_token}/issue/resend");
        $res2->assertOk();
        $this->assertSame($replacementToken, $res2->json('replacement_token'));
    }

    public function test_admin_reschedule_shipment_action(): void
    {
        $pkg = $this->createPackage(['status' => PackageStatus::RESCHEDULE_REQUESTED]);

        $newDate = now('Asia/Jakarta')->addDays(2)->format('Y-m-d');

        $this->actingAs($this->admin);

        $res = $this->postJson("/api/admin/shipments/{$pkg->tracking_number}/reschedule", [
            'scheduled_date' => $newDate,
            'scheduled_time_window' => '11:00 - 13:00',
        ]);

        $res->assertOk();
        $this->assertSame(PackageStatus::AWAITING_CONFIRMATION, $pkg->fresh()->status);

        // Buyer notification queued
        $buyerNotif = Notification::where('package_id', $pkg->id)
            ->where('recipient_type', RecipientType::BUYER)
            ->where('template', 'schedule_rescheduled')
            ->first();

        $this->assertNotNull($buyerNotif);
        $this->assertArrayHasKey('client_url', $buyerNotif->payload);
    }

    public function test_remind_unanswered_confirmations_command(): void
    {
        $pkg = $this->createPackage(['status' => PackageStatus::AWAITING_CONFIRMATION]);

        $confirmation = ScheduleConfirmation::create([
            'package_id' => $pkg->id,
            'response' => 'PENDING',
            'requested_at' => now()->subHours(3), // Older than 120 minutes threshold
        ]);

        $this->artisan('confirmations:remind-unanswered')->assertSuccessful();

        $notif = Notification::where('package_id', $pkg->id)
            ->where('recipient_type', RecipientType::ADMIN)
            ->where('template', 'confirmation_overdue')
            ->first();

        $this->assertNotNull($notif);
        $this->assertSame((string) $confirmation->id, $notif->payload['confirmation_id']);

        // Run again: deduplication prevents re-queueing
        $countBefore = Notification::where('template', 'confirmation_overdue')->count();
        $this->artisan('confirmations:remind-unanswered')->assertSuccessful();
        $this->assertSame($countBefore, Notification::where('template', 'confirmation_overdue')->count());
    }
}
