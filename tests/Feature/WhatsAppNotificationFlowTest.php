<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Courier\ReportIssue;
use App\DTOs\Courier\ReportIssueData;
use App\Enums\IssueCategory;
use App\Enums\NotificationStatus;
use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Jobs\DispatchNotificationToN8n;
use App\Models\Buyer;
use App\Models\ConditionCheck;
use App\Models\IssueReport;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use App\Models\Staff;
use App\Support\DisplayTime;
use App\Support\NotificationQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WhatsAppNotificationFlowTest extends TestCase
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
            ['email' => 'admin.whatsapp@anteraja.id'],
            [
                'name' => 'Admin WhatsApp',
                'role' => StaffRole::ADMIN,
                'phone' => '081100000088',
                'password' => 'secret',
                'is_active' => true,
            ]
        );

        $this->courier = Staff::firstOrCreate(
            ['email' => 'courier.whatsapp@anteraja.id'],
            [
                'name' => 'Budi Kurir',
                'role' => StaffRole::COURIER,
                'phone' => '081200000088',
                'password' => 'secret',
                'is_active' => true,
            ]
        );

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => '+6281300000188'],
            [
                'name' => 'Rina Pembeli',
                'address' => 'Jl. Melati No. 14, Jakarta Selatan',
                'area' => 'Kebayoran Baru',
            ]
        );

        $this->product = Product::firstOrCreate(
            ['name' => 'Nugget Ayam 1 kg'],
            ['handling_notes' => 'Suhu -18C.'],
        );

        config([
            'coldchain.n8n.enabled' => false,
            'coldchain.n8n.webhook_url' => 'http://n8n.test/webhook/anteraja-frozen/notify',
            'coldchain.n8n.token' => 'secret-token',
        ]);
    }

    private function createPackage(array $attributes = []): Package
    {
        /** @var Package $package */
        $package = Package::create(array_merge([
            'buyer_id' => $this->buyer->id,
            'created_by' => $this->admin->id,
            // The delivery-leg messages go to the recipient's number, not to
            // the customer who sent the parcel.
            'recipient_name' => 'Ibu Sari Penerima',
            'recipient_phone' => '+6281300000199',
            'recipient_address' => 'Jl. Kenanga No. 3, Coblong',
            'recipient_area' => 'Bandung, Jawa Barat',
            'recipient_address_type' => 'RESIDENTIAL',
            'recipient_latitude' => -6.9175,
            'recipient_longitude' => 107.6191,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'status' => PackageStatus::AWAITING_CONFIRMATION,
            'weight_kg' => 1.00,
        ], $attributes))->refresh();

        PackageItem::create([
            'package_id' => $package->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        return $package;
    }

    private function createIssueReport(Package $package, array $attributes = []): IssueReport
    {
        $check = ConditionCheck::create([
            'package_id' => $package->id,
            'courier_id' => $this->courier->id,
            'is_normal' => false,
            'notes' => 'Kemasan bocor',
            'checked_at' => now(),
        ]);

        return IssueReport::create(array_merge([
            'check_id' => $check->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR,
            'description' => 'Kemasan bocor saat transit',
            'reported_at' => now(),
        ], $attributes));
    }

    private function queuedNotification(string $template = 'schedule_request'): Notification
    {
        $package = $this->createPackage();

        return app(NotificationQueue::class)->queue(
            $package,
            RecipientType::BUYER,
            $package->buyer_id,
            $template,
            ['window' => 'Besok 13:00-15:00']
        );
    }

    public function test_notification_payload_carries_whatsapp_delivery_context(): void
    {
        Queue::fake();

        $package = $this->createPackage();

        $notification = app(NotificationQueue::class)->queue(
            $package,
            RecipientType::BUYER,
            $package->buyer_id,
            'schedule_request',
            ['window' => 'Besok 13:00-15:00']
        );

        Queue::assertPushed(DispatchNotificationToN8n::class);

        $payload = $notification->refresh()->payload;

        // Addressed to the recipient, with the token the answer is applied
        // through; the customer's app link is not part of it.
        $this->assertSame('+6281300000199', $payload['recipient_phone']);
        $this->assertSame('Ibu Sari Penerima', $payload['recipient_name']);
        $this->assertSame('Jl. Kenanga No. 3, Coblong', $payload['address']);
        $this->assertSame($package->tracking_number, $payload['tracking_number']);
        $this->assertSame($package->client_token, $payload['client_token']);
        $this->assertArrayNotHasKey('client_url', $payload);
        // A caller that names the window keeps it.
        $this->assertSame('Besok 13:00-15:00', $payload['window']);
    }

    /** Without a caller-supplied window the payload gets the readable label. */
    public function test_a_queued_confirmation_carries_the_formatted_window(): void
    {
        Queue::fake();

        $package = $this->createPackage();

        $notification = app(NotificationQueue::class)->queue(
            $package,
            RecipientType::BUYER,
            $package->buyer_id,
            'schedule_request'
        );

        $this->assertSame(
            DisplayTime::windowLabel($package->scheduled_at, $package->scheduled_end_at),
            $notification->refresh()->payload['window']
        );
    }

    /** A recheck decision is an outcome too: the buyer has to hear it. */
    public function test_a_recheck_decision_also_updates_the_buyer(): void
    {
        Queue::fake();

        $package = $this->createPackage(['status' => PackageStatus::ISSUE_REPORTED]);
        $this->createIssueReport($package);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/exceptions/{$package->tracking_number}/decision", [
                'decision' => 'RECHECK',
                'decision_reason' => 'Perlu pemeriksaan ulang kondisi fisik paket.',
            ])
            ->assertOk();

        $buyerUpdate = Notification::query()
            ->where('package_id', $package->id)
            ->where('recipient_type', RecipientType::BUYER->value)
            ->where('template', 'issue_decision_update')
            ->first();

        $this->assertNotNull($buyerUpdate, 'the buyer gets an update for a recheck decision');
        $this->assertSame('RECHECK', $buyerUpdate->payload['decision']);
        $this->assertNotEmpty($buyerUpdate->payload['message']);
    }

    public function test_job_marks_notification_sent_when_n8n_delivers(): void
    {
        Http::fake(['*' => Http::response(['delivered' => true, 'message_id' => 'wamid-1'], 200)]);
        config(['coldchain.n8n.enabled' => true]);

        $notification = $this->queuedNotification();

        (new DispatchNotificationToN8n($notification->id))->handle();

        $notification->refresh();

        $this->assertSame(NotificationStatus::SENT, $notification->status);
        $this->assertNotNull($notification->sent_at);
        $this->assertSame('wamid-1', $notification->payload['wa_message_id']);

        Http::assertSent(fn ($request) => $request->url() === 'http://n8n.test/webhook/anteraja-frozen/notify'
            && $request->hasHeader('X-Anteraja-Token', 'secret-token')
            && $request['template'] === 'schedule_request');
    }

    public function test_job_marks_notification_failed_when_n8n_rejects(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);
        config(['coldchain.n8n.enabled' => true]);

        $notification = $this->queuedNotification();

        (new DispatchNotificationToN8n($notification->id))->handle();

        $notification->refresh();

        $this->assertSame(NotificationStatus::FAILED, $notification->status);
        $this->assertSame('HTTP 500', $notification->payload['delivery_error']);
    }

    public function test_job_leaves_unmapped_template_queued(): void
    {
        Http::fake(['*' => Http::response(['delivered' => false, 'reason' => 'unmapped'], 200)]);
        config(['coldchain.n8n.enabled' => true]);

        $notification = $this->queuedNotification('task_assigned');

        (new DispatchNotificationToN8n($notification->id))->handle();

        $notification->refresh();

        $this->assertSame(NotificationStatus::QUEUED, $notification->status);
        $this->assertArrayNotHasKey('delivery_error', $notification->payload);
    }

    public function test_job_skips_when_webhook_is_disabled(): void
    {
        Http::fake();

        $notification = $this->queuedNotification();

        (new DispatchNotificationToN8n($notification->id))->handle();

        Http::assertNothingSent();
        $this->assertSame(NotificationStatus::QUEUED, $notification->refresh()->status);
    }

    public function test_courier_issue_report_notifies_ops_but_not_the_buyer(): void
    {
        Queue::fake();

        $package = $this->createPackage([
            'status' => PackageStatus::COURIER_ASSIGNED,
            'courier_id' => $this->courier->id,
        ]);

        app(ReportIssue::class)->execute($package, $this->courier, new ReportIssueData(
            issueCategory: IssueCategory::KEMASAN_BOCOR,
            description: 'Kemasan bocor di jalan',
            photoPath: 'proofs/issue-test.jpg',
        ));

        // Ops is told about the problem; the recipient no longer receives a menu.
        $this->assertTrue(Notification::where('package_id', $package->id)
            ->where('template', 'issue_reported')
            ->where('recipient_type', RecipientType::ADMIN)
            ->exists());

        $this->assertSame(0, Notification::where('package_id', $package->id)
            ->where('recipient_type', RecipientType::BUYER)
            ->count());
    }
}
