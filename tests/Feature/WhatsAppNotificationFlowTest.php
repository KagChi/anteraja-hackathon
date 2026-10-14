<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Courier\ReportIssue;
use App\DTOs\Courier\ReportIssueData;
use App\Enums\BuyerIssueChoice;
use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
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

        $this->assertSame('+6281300000188', $payload['recipient_phone']);
        $this->assertSame('Rina Pembeli', $payload['recipient_name']);
        $this->assertSame('Jl. Melati No. 14, Jakarta Selatan', $payload['address']);
        $this->assertSame($package->tracking_number, $payload['tracking_number']);
        $this->assertSame($package->client_token, $payload['client_token']);
        $this->assertNotEmpty($payload['client_url']);
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

    public function test_courier_issue_report_queues_buyer_resolution_menu(): void
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

        $notification = Notification::where('package_id', $package->id)
            ->where('template', 'issue_options')
            ->where('recipient_type', RecipientType::BUYER)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('kemasan_bocor', $notification->payload['issue_category']);
        $this->assertSame('+6281300000188', $notification->payload['recipient_phone']);
    }

    public function test_buyer_can_pick_return_option_from_whatsapp(): void
    {
        $package = $this->createPackage(['status' => PackageStatus::ISSUE_REPORTED]);
        $issue = $this->createIssueReport($package);

        $response = $this->postJson("/api/guest/{$package->client_token}/issue/choice", ['choice' => 1]);

        $response->assertOk();
        $response->assertJsonPath('data.choice', 'RETURN');
        $response->assertJsonPath('data.already_recorded', false);

        $issue->refresh();
        $this->assertSame(BuyerIssueChoice::RETURN, $issue->buyer_choice);
        $this->assertNotNull($issue->buyer_responded_at);

        $this->assertDatabaseHas('notifications', [
            'package_id' => $package->id,
            'recipient_type' => 'ADMIN',
            'template' => 'buyer_issue_choice',
        ]);
    }

    public function test_resend_option_creates_replacement_only_when_eligible(): void
    {
        $package = $this->createPackage(['status' => PackageStatus::ISSUE_REPORTED]);
        $this->createIssueReport($package);

        $this->postJson("/api/guest/{$package->client_token}/issue/choice", ['choice' => 2])
            ->assertOk()
            ->assertJsonPath('data.choice', 'RESEND')
            ->assertJsonPath('data.replacement_tracking_number', null);

        $this->assertNull(Package::where('resend_of_package_id', $package->id)->first());
    }

    public function test_resend_option_creates_replacement_when_package_is_returned(): void
    {
        $package = $this->createPackage(['status' => PackageStatus::RETURNED]);
        $this->createIssueReport($package, [
            'decision' => IssueDecision::RETURN,
            'decision_reason' => 'Kemasan rusak, paket ditarik ke DC.',
            'decided_by' => $this->admin->id,
            'decided_at' => now(),
        ]);

        $response = $this->postJson("/api/guest/{$package->client_token}/issue/choice", ['choice' => 2]);

        $response->assertOk();

        $replacement = Package::where('resend_of_package_id', $package->id)->first();

        $this->assertNotNull($replacement);
        $this->assertSame($replacement->tracking_number, $response->json('data.replacement_tracking_number'));
    }

    public function test_first_buyer_choice_wins_when_reply_is_repeated(): void
    {
        $package = $this->createPackage(['status' => PackageStatus::ISSUE_REPORTED]);
        $issue = $this->createIssueReport($package);

        $this->postJson("/api/guest/{$package->client_token}/issue/choice", ['choice' => 1])->assertOk();

        $this->postJson("/api/guest/{$package->client_token}/issue/choice", ['choice' => 3])
            ->assertOk()
            ->assertJsonPath('data.already_recorded', true)
            ->assertJsonPath('data.choice', 'RETURN');

        $this->assertSame(BuyerIssueChoice::RETURN, $issue->refresh()->buyer_choice);
    }

    public function test_issue_choice_rejects_unknown_option(): void
    {
        $package = $this->createPackage(['status' => PackageStatus::ISSUE_REPORTED]);
        $this->createIssueReport($package);

        $this->postJson("/api/guest/{$package->client_token}/issue/choice", ['choice' => 9])
            ->assertStatus(422);
    }

    public function test_issue_choice_requires_an_issue_report(): void
    {
        $package = $this->createPackage();

        $this->postJson("/api/guest/{$package->client_token}/issue/choice", ['choice' => 1])
            ->assertStatus(422);
    }
}
