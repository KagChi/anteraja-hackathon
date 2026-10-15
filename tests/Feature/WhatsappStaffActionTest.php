<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\ConditionCheck;
use App\Models\IssueReport;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use App\Models\Staff;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Balasan WhatsApp dari admin.
 *
 * Nomor pengirim yang menentukan siapa yang bertindak, dan setiap keputusan
 * dijalankan aksi aplikasi yang sama dengan yang dipakai dashboard admin — jadi
 * status paket dan notifikasi lanjutannya sama. Kurir tidak memakai WhatsApp:
 * tugas dan pembaruan pengantaran hanya lewat aplikasi Anteraja Kurir, jadi
 * nomor kurir yang membalas ditolak.
 */
final class WhatsappStaffActionTest extends TestCase {
    use DatabaseTransactions;

    private const TOKEN = 'staff-action-test-token';

    private const RESI = 'AJ-FRZ-0099001';

    private Staff $admin;

    private Staff $courier;

    private Buyer $buyer;

    private Product $product;

    protected function setUp(): void {
        parent::setUp();

        config(['coldchain.n8n.token' => self::TOKEN]);

        $this->admin = Staff::firstOrCreate(
            ['email' => 'admin.staff.action@anteraja.id'],
            [
                'name' => 'Dimas Admin',
                'role' => StaffRole::ADMIN,
                'phone' => '081100000099',
                'password' => 'secret123',
                'is_active' => true,
            ]
        );

        $this->courier = Staff::firstOrCreate(
            ['email' => 'courier.staff.action@anteraja.id'],
            [
                'name' => 'Budi Kurir',
                'role' => StaffRole::COURIER,
                'phone' => '081200000099',
                'password' => 'secret123',
                'is_active' => true,
            ]
        );

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => '+6281300000100'],
            [
                'name' => 'Rina Pengirim',
                'address' => 'Jl. Melati Indah No. 42, Jakarta Selatan',
                'area' => 'Tebet Barat',
            ]
        );

        $this->product = Product::firstOrCreate(
            ['name' => 'Nugget Ayam 2 kg'],
            ['handling_notes' => 'Simpan di cold box.']
        );
    }

    private function package(PackageStatus $status = PackageStatus::COURIER_ASSIGNED, ?Staff $courier = null, array $attributes = []): Package {
        $courier ??= $this->courier;

        /** @var Package $package */
        $package = Package::create(array_merge([
            'buyer_id' => $this->buyer->id,
            'created_by' => $this->admin->id,
            'courier_id' => $courier->id,
            // The parcel's destination and WhatsApp contact is the recipient.
            'recipient_name' => 'Penerima WhatsApp Staff',
            'recipient_phone' => '+6281300000199',
            'recipient_address' => 'Jl. Kenanga No. 3, Coblong',
            'recipient_area' => 'Bandung, Jawa Barat',
            'recipient_address_type' => 'RESIDENTIAL',
            'recipient_latitude' => -6.9175,
            'recipient_longitude' => 107.6191,
            'status' => $status,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'weight_kg' => 2.0,
            'handling_instructions' => 'Serahkan langsung ke penerima.',
        ], $attributes))->refresh();

        PackageItem::create([
            'package_id' => $package->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        return $package;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function act(string $phone, string $action, ?string $resi = null, array $extra = []): array {
        $response = $this->withHeaders(['X-Anteraja-Token' => self::TOKEN])
            ->postJson('/api/internal/whatsapp/action', array_merge([
                'phone' => $phone,
                'action' => $action,
                'resi' => $resi,
            ], $extra));

        $response->assertOk();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function lookup(string $phone, string $resi): array {
        $response = $this->withHeaders(['X-Anteraja-Token' => self::TOKEN])
            ->postJson('/api/internal/whatsapp/lookup', [
                'phone' => $phone,
                'resi' => $resi,
            ]);

        $response->assertOk();

        return $response->json();
    }

    public function test_the_lookup_recognises_the_recipient_without_a_customer_link(): void {
        $package = $this->package(PackageStatus::AWAITING_CONFIRMATION, courier: $this->courier, attributes: ['courier_id' => null]);

        $answer = $this->lookup('6281300000199', $package->tracking_number);

        $this->assertTrue($answer['found']);
        $this->assertSame('BUYER', $answer['role']);
        $this->assertSame($package->client_token, $answer['client_token']);
        $this->assertArrayNotHasKey('client_url', $answer);
    }

    public function test_the_lookup_recognises_an_admin_holding_an_open_issue(): void {
        $package = $this->package(PackageStatus::ISSUE_REPORTED);
        $this->openIssue($package);

        $answer = $this->lookup('6281100000099', $package->tracking_number);

        $this->assertTrue($answer['found']);
        $this->assertSame('ADMIN', $answer['role']);
        $this->assertSame('admin_decision', $answer['template']);
        $this->assertArrayNotHasKey('client_url', $answer);
    }

    public function test_the_lookup_refuses_a_number_that_owns_nothing(): void {
        $package = $this->package();

        $this->assertSame(['found' => false, 'role' => null], $this->lookup('628999999999', $package->tracking_number));
    }

    /** Kurir tidak memakai WhatsApp, so their number resolves to no role. */
    public function test_the_lookup_does_not_recognise_a_courier_number(): void {
        $package = $this->package();

        $this->assertSame(['found' => false, 'role' => null], $this->lookup('6281200000099', $package->tracking_number));
    }

    public function test_a_courier_reply_is_refused(): void {
        $package = $this->package();

        $answer = $this->act('6281200000099', 'accept', $package->tracking_number, ['message_id' => 'wa-courier-1']);

        $this->assertFalse($answer['success']);
        $this->assertStringContainsString('Kurir tidak bertindak lewat WhatsApp', $answer['message']);

        $package->refresh();
        $this->assertNull($package->courier_accepted_at);
        $this->assertSame(PackageStatus::COURIER_ASSIGNED, $package->status);
    }

    public function test_an_admin_decides_over_whatsapp(): void {
        $package = $this->package(PackageStatus::ISSUE_REPORTED);
        $this->openIssue($package);

        $answer = $this->act('6281100000099', 'return', $package->tracking_number, [
            'note' => 'Segel rusak, paket dikembalikan.',
            'message_id' => 'wa-decision-1',
        ]);

        $this->assertTrue($answer['success']);
        $this->assertSame('ADMIN', $answer['role']);
        $this->assertSame('admin_decision:RETURN', $answer['template']);

        $package->refresh();
        $this->assertSame(PackageStatus::RETURNED, $package->status);

        $this->assertDatabaseHas('issue_reports', [
            'decision' => IssueDecision::RETURN->value,
            'decision_reason' => 'Segel rusak, paket dikembalikan.',
            'decided_by' => $this->admin->id,
        ]);

        // Pembeli menerima kabar keputusan; kurir tidak lagi lewat WhatsApp.
        $this->assertDatabaseHas('notifications', ['package_id' => $package->id, 'recipient_type' => 'BUYER', 'template' => 'issue_decision_update']);
        $this->assertDatabaseMissing('notifications', ['package_id' => $package->id, 'recipient_type' => 'COURIER']);
    }

    public function test_an_unknown_number_gets_a_help_reply_and_changes_nothing(): void {
        $package = $this->package(PackageStatus::ISSUE_REPORTED);

        $answer = $this->act('628999999999', 'return', $package->tracking_number, ['message_id' => 'wa-unknown-1']);

        $this->assertFalse($answer['success']);
        $this->assertStringContainsString('belum terdaftar', $answer['message']);

        $package->refresh();
        $this->assertSame(PackageStatus::ISSUE_REPORTED, $package->status);
    }

    public function test_an_unknown_command_explains_the_commands_it_accepts(): void {
        $package = $this->package(PackageStatus::ISSUE_REPORTED);

        $answer = $this->act('6281100000099', 'terbang', $package->tracking_number, ['message_id' => 'wa-unknown-cmd-1']);

        $this->assertFalse($answer['success']);
        $this->assertStringContainsString('*KEMBALIKAN*', $answer['message']);
        $this->assertStringContainsString('*JADWALKAN ULANG*', $answer['message']);
    }

    public function test_an_action_the_status_does_not_allow_explains_itself(): void {
        // No open issue: there is nothing for the admin to decide.
        $package = $this->package(PackageStatus::IN_DELIVERY);

        $answer = $this->act('6281100000099', 'return', $package->tracking_number, ['message_id' => 'wa-twice-1']);

        $this->assertFalse($answer['success']);
        $this->assertStringContainsString('menunggu keputusan kendala', $answer['message']);
    }

    public function test_a_repeated_whatsapp_message_runs_the_action_once(): void {
        $package = $this->package(PackageStatus::ISSUE_REPORTED);
        $this->openIssue($package);

        $first = $this->act('6281100000099', 'return', $package->tracking_number, ['message_id' => 'wa-dup-1']);
        $second = $this->act('6281100000099', 'return', $package->tracking_number, ['message_id' => 'wa-dup-1']);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['message'], $second['message']);

        // Satu keputusan saja: satu notifikasi dan satu pesan tercatat.
        $this->assertSame(1, IssueReport::whereHas('check', fn ($query) => $query->where('package_id', $package->id))->whereNotNull('decision')->count());
        $this->assertSame(1, Notification::where('package_id', $package->id)->where('template', 'issue_decision_update')->count());
        $this->assertSame(1, WhatsappMessage::where('external_id', 'wa-dup-1')->count());
    }

    public function test_the_message_log_keeps_both_directions_and_drops_repeats(): void {
        $headers = ['X-Anteraja-Token' => self::TOKEN];

        $this->withHeaders($headers)->postJson('/api/internal/whatsapp/messages', [
            'phone' => '6281100000099',
            'direction' => 'IN',
            'body' => 'kembalikan resi AJ-FRZ-0099001',
            'external_id' => 'log-in-1',
            'role' => 'ADMIN',
        ])->assertOk()->assertJson(['recorded' => true]);

        $this->withHeaders($headers)->postJson('/api/internal/whatsapp/messages', [
            'phone' => '6281100000099',
            'direction' => 'OUT',
            'body' => 'Keputusan dijalankan',
            'external_id' => 'log-out-1',
            'status' => 'SENT',
        ])->assertOk()->assertJson(['recorded' => true]);

        // Id yang sama tidak dicatat dua kali.
        $this->withHeaders($headers)->postJson('/api/internal/whatsapp/messages', [
            'phone' => '6281100000099',
            'direction' => 'OUT',
            'body' => 'Keputusan dijalankan',
            'external_id' => 'log-out-1',
            'status' => 'SENT',
        ])->assertOk()->assertJson(['recorded' => false]);

        $this->assertSame(1, WhatsappMessage::where('external_id', 'log-in-1')->count());
        $this->assertSame(1, WhatsappMessage::where('external_id', 'log-out-1')->count());
    }

    public function test_the_admin_dashboard_can_read_the_message_history(): void {
        WhatsappMessage::create([
            'phone' => '6281100000099',
            'role' => 'ADMIN',
            'direction' => WhatsappMessage::IN,
            'body' => 'kembalikan',
            'status' => WhatsappMessage::RECEIVED,
            'tracking_number' => self::RESI,
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/messages?resi='.self::RESI)
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'kembalikan')
            ->assertJsonPath('messages.0.direction', 'IN');

        $this->actingAs($this->courier)
            ->getJson('/api/admin/messages')
            ->assertForbidden();
    }

    private function openIssue(Package $package): IssueReport {
        $check = ConditionCheck::create([
            'package_id' => $package->id,
            'courier_id' => $this->courier->id,
            'is_normal' => false,
            'notes' => 'Kemasan bocor',
            'checked_at' => now(),
        ]);

        return IssueReport::create([
            'check_id' => $check->id,
            'issue_category' => IssueCategory::KEMASAN_BOCOR->value,
            'description' => 'Kemasan bocor',
            'reported_at' => now(),
        ]);
    }
}
