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
 * Balasan WhatsApp dari kurir dan admin.
 *
 * Nomor pengirim yang menentukan siapa yang bertindak, dan setiap aksi
 * dijalankan aksi aplikasi yang sama dengan yang dipakai aplikasi kurir atau
 * dashboard admin — jadi status paket dan notifikasi lanjutannya sama.
 */
final class WhatsappStaffActionTest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'staff-action-test-token';

    private const RESI = 'AJ-FRZ-0099001';

    private Staff $admin;

    private Staff $courier;

    private Staff $otherCourier;

    private Buyer $buyer;

    private Product $product;

    protected function setUp(): void
    {
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

        $this->otherCourier = Staff::firstOrCreate(
            ['email' => 'courier.staff.action.2@anteraja.id'],
            [
                'name' => 'Ahmad Kurir',
                'role' => StaffRole::COURIER,
                'phone' => '081200000199',
                'password' => 'secret123',
                'is_active' => true,
            ]
        );

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => '+6281300000199'],
            [
                'name' => 'Rina Pembeli',
                'address' => 'Jl. Melati Indah No. 42, Jakarta Selatan',
                'area' => 'Tebet Barat',
            ]
        );

        $this->product = Product::firstOrCreate(
            ['name' => 'Nugget Ayam 2 kg'],
            ['handling_notes' => 'Simpan di cold box.']
        );
    }

    private function package(PackageStatus $status = PackageStatus::COURIER_ASSIGNED, ?Staff $courier = null, array $attributes = []): Package
    {
        $courier ??= $this->courier;

        /** @var Package $package */
        $package = Package::create(array_merge([
            'buyer_id' => $this->buyer->id,
            'created_by' => $this->admin->id,
            'courier_id' => $courier->id,
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
    private function act(string $phone, string $action, ?string $resi = null, array $extra = []): array
    {
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
    private function lookup(string $phone, string $resi): array
    {
        $response = $this->withHeaders(['X-Anteraja-Token' => self::TOKEN])
            ->postJson('/api/internal/whatsapp/lookup', [
                'phone' => $phone,
                'resi' => $resi,
            ]);

        $response->assertOk();

        return $response->json();
    }

    public function test_the_lookup_recognises_the_buyer_and_keeps_the_guest_link(): void
    {
        $package = $this->package(PackageStatus::AWAITING_CONFIRMATION, courier: $this->courier, attributes: ['courier_id' => null]);

        $answer = $this->lookup('6281300000199', $package->tracking_number);

        $this->assertTrue($answer['found']);
        $this->assertSame('BUYER', $answer['role']);
        $this->assertSame($package->client_token, $answer['client_token']);
        $this->assertArrayHasKey('client_url', $answer);
    }

    public function test_the_lookup_recognises_the_courier_without_leaking_the_buyer_token(): void
    {
        $package = $this->package();

        $answer = $this->lookup('6281200000099', $package->tracking_number);

        $this->assertTrue($answer['found']);
        $this->assertSame('COURIER', $answer['role']);
        $this->assertSame('task_assigned', $answer['template']);
        $this->assertArrayNotHasKey('client_url', $answer);
        $this->assertArrayNotHasKey('client_token', $answer);
        $this->assertSame(
            ['found', 'role', 'courier_name', 'tracking_number', 'template', 'status_label', 'window'],
            array_keys($answer)
        );
    }

    public function test_the_lookup_recognises_an_admin_holding_an_open_issue(): void
    {
        $package = $this->package(PackageStatus::ISSUE_REPORTED);
        $this->openIssue($package);

        $answer = $this->lookup('6281100000099', $package->tracking_number);

        $this->assertTrue($answer['found']);
        $this->assertSame('ADMIN', $answer['role']);
        $this->assertSame('admin_decision', $answer['template']);
        $this->assertArrayNotHasKey('client_url', $answer);
    }

    public function test_the_lookup_refuses_a_number_that_owns_nothing(): void
    {
        $package = $this->package();

        $this->assertSame(['found' => false, 'role' => null], $this->lookup('628999999999', $package->tracking_number));
    }

    public function test_a_courier_accepts_the_task_over_whatsapp(): void
    {
        $package = $this->package();

        $answer = $this->act('6281200000099', 'accept', $package->tracking_number, ['message_id' => 'wa-accept-1']);

        $this->assertTrue($answer['success']);
        $this->assertSame('COURIER', $answer['role']);
        $this->assertSame('courier_accepted', $answer['template']);
        $this->assertStringContainsString('Instruksi penanganan: Serahkan langsung ke penerima', $answer['message']);

        $package->refresh();
        $this->assertNotNull($package->courier_accepted_at);
        $this->assertSame(PackageStatus::COURIER_ASSIGNED, $package->status);

        // Kurir juga menerima pengingat memeriksa kemasan sebelum berangkat.
        $this->assertDatabaseHas('notifications', [
            'package_id' => $package->id,
            'recipient_type' => 'COURIER',
            'template' => 'condition_check_reminder',
        ]);
    }

    public function test_a_courier_rejecting_the_task_tells_the_admin(): void
    {
        $package = $this->package();

        $answer = $this->act('6281200000099', 'reject', $package->tracking_number, [
            'note' => 'Motor masuk bengkel',
            'message_id' => 'wa-reject-1',
        ]);

        $this->assertTrue($answer['success']);
        $this->assertStringContainsString('Motor masuk bengkel', $answer['message']);

        $package->refresh();
        $this->assertSame(PackageStatus::CONFIRMED, $package->status);
        $this->assertNull($package->courier_id);

        $this->assertDatabaseHas('notifications', [
            'package_id' => $package->id,
            'recipient_type' => 'ADMIN',
            'template' => 'task_rejected',
        ]);
    }

    public function test_a_courier_departs_and_the_buyer_is_told(): void
    {
        $package = $this->package();

        $answer = $this->act('6281200000099', 'depart', $package->tracking_number, ['message_id' => 'wa-depart-1']);

        $this->assertTrue($answer['success']);
        $this->assertSame('courier_departed', $answer['template']);

        $package->refresh();
        $this->assertSame(PackageStatus::IN_DELIVERY, $package->status);

        $this->assertDatabaseHas('notifications', [
            'package_id' => $package->id,
            'recipient_type' => 'BUYER',
            'template' => 'on_the_way',
        ]);
    }

    public function test_a_courier_arrives_and_the_buyer_is_told(): void
    {
        $package = $this->package(PackageStatus::IN_DELIVERY);
        \App\Models\DeliveryMilestone::create([
            'package_id' => $package->id,
            'milestone' => \App\Enums\DeliveryMilestoneEnum::DEPARTED,
            'courier_id' => $this->courier->id,
            'occurred_at' => now(),
        ]);

        $answer = $this->act('6281200000099', 'arrived', $package->tracking_number, ['message_id' => 'wa-arrive-1']);

        $this->assertTrue($answer['success']);

        $this->assertDatabaseHas('delivery_milestones', [
            'package_id' => $package->id,
            'milestone' => 'ARRIVED',
        ]);

        $this->assertDatabaseHas('notifications', [
            'package_id' => $package->id,
            'recipient_type' => 'BUYER',
            'template' => 'courier_arrived',
        ]);
    }

    public function test_a_courier_reports_a_leaked_packaging_from_whatsapp(): void
    {
        $package = $this->package(PackageStatus::IN_DELIVERY);

        $answer = $this->act('6281200000099', 'issue', $package->tracking_number, [
            'category' => IssueCategory::KEMASAN_BOCOR->value,
            'note' => 'Kemasan bocor di sisi kiri',
            'message_id' => 'wa-issue-1',
        ]);

        $this->assertTrue($answer['success']);
        $this->assertSame('courier_issue_reported', $answer['template']);
        $this->assertStringContainsString('Kemasan bocor', $answer['message']);

        $package->refresh();
        $this->assertSame(PackageStatus::ISSUE_REPORTED, $package->status);

        $this->assertDatabaseHas('issue_reports', ['description' => 'Kemasan bocor di sisi kiri']);

        // Admin memutuskan, pembeli memilih penyelesaian: keduanya diantre.
        $this->assertDatabaseHas('notifications', ['package_id' => $package->id, 'template' => 'issue_reported', 'recipient_type' => 'ADMIN']);
        $this->assertDatabaseHas('notifications', ['package_id' => $package->id, 'template' => 'issue_options', 'recipient_type' => 'BUYER']);
    }

    public function test_a_courier_completes_the_handover_from_whatsapp(): void
    {
        $package = $this->package(PackageStatus::IN_DELIVERY);

        $answer = $this->act('6281200000099', 'deliver', $package->tracking_number, [
            'note' => 'Rina Putri',
            'message_id' => 'wa-deliver-1',
        ]);

        $this->assertTrue($answer['success']);

        $package->refresh();
        $this->assertSame(PackageStatus::RECEIVED, $package->status);

        $this->assertDatabaseHas('deliveries', [
            'package_id' => $package->id,
            'received_by' => 'Rina Putri',
        ]);

        $this->assertDatabaseHas('notifications', [
            'package_id' => $package->id,
            'recipient_type' => 'BUYER',
            'template' => 'delivered',
        ]);
    }

    public function test_an_admin_decides_over_whatsapp(): void
    {
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

        // Instruksi ke kurir dan kabar ke pembeli.
        $this->assertDatabaseHas('notifications', ['package_id' => $package->id, 'recipient_type' => 'COURIER', 'template' => 'issue_decision']);
        $this->assertDatabaseHas('notifications', ['package_id' => $package->id, 'recipient_type' => 'BUYER', 'template' => 'issue_decision_update']);
    }

    public function test_a_courier_cannot_touch_another_couriers_parcel(): void
    {
        $package = $this->package();

        $answer = $this->act('6281200000199', 'accept', $package->tracking_number, ['message_id' => 'wa-foreign-1']);

        $this->assertFalse($answer['success']);
        $this->assertStringContainsString('Tidak ada paket aktif', $answer['message']);

        $package->refresh();
        $this->assertNull($package->courier_accepted_at);
    }

    public function test_an_unknown_number_gets_a_help_reply_and_changes_nothing(): void
    {
        $package = $this->package();

        $answer = $this->act('628999999999', 'accept', $package->tracking_number, ['message_id' => 'wa-unknown-1']);

        $this->assertFalse($answer['success']);
        $this->assertStringContainsString('belum terdaftar', $answer['message']);

        $package->refresh();
        $this->assertNull($package->courier_accepted_at);
    }

    public function test_an_unknown_command_explains_the_commands_it_accepts(): void
    {
        $package = $this->package();

        $answer = $this->act('6281200000099', 'terbang', $package->tracking_number, ['message_id' => 'wa-unknown-cmd-1']);

        $this->assertFalse($answer['success']);
        $this->assertStringContainsString('*TERIMA*', $answer['message']);
        $this->assertStringContainsString('*BOCOR*', $answer['message']);
    }

    public function test_an_action_the_status_does_not_allow_explains_itself(): void
    {
        $package = $this->package(PackageStatus::COURIER_ASSIGNED);

        // Sudah diterima sekali; "terima" kedua kali tetap jawaban yang sama.
        $first = $this->act('6281200000099', 'accept', $package->tracking_number, ['message_id' => 'wa-twice-1']);
        $this->assertTrue($first['success']);

        // Berangkat dua kali tidak diizinkan status; jawabannya dari domain.
        $this->act('6281200000099', 'depart', $package->tracking_number, ['message_id' => 'wa-twice-2']);
        $second = $this->act('6281200000099', 'depart', $package->tracking_number, ['message_id' => 'wa-twice-3']);

        $this->assertFalse($second['success']);
        $this->assertStringContainsString('siap berangkat', $second['message']);
    }

    public function test_a_repeated_whatsapp_message_runs_the_action_once(): void
    {
        $package = $this->package();

        $first = $this->act('6281200000099', 'depart', $package->tracking_number, ['message_id' => 'wa-dup-1']);
        $second = $this->act('6281200000099', 'depart', $package->tracking_number, ['message_id' => 'wa-dup-1']);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['message'], $second['message']);

        // Satu aksi saja: satu baris condition check dan satu notifikasi.
        $this->assertSame(1, ConditionCheck::where('package_id', $package->id)->count());
        $this->assertSame(1, Notification::where('package_id', $package->id)->where('template', 'on_the_way')->count());
        $this->assertSame(1, WhatsappMessage::where('external_id', 'wa-dup-1')->count());
    }

    public function test_the_message_log_keeps_both_directions_and_drops_repeats(): void
    {
        $headers = ['X-Anteraja-Token' => self::TOKEN];

        $this->withHeaders($headers)->postJson('/api/internal/whatsapp/messages', [
            'phone' => '6281200000099',
            'direction' => 'IN',
            'body' => 'terima',
            'external_id' => 'log-in-1',
            'role' => 'COURIER',
        ])->assertOk()->assertJson(['recorded' => true]);

        $this->withHeaders($headers)->postJson('/api/internal/whatsapp/messages', [
            'phone' => '6281200000099',
            'direction' => 'OUT',
            'body' => 'Tugas diterima',
            'external_id' => 'log-out-1',
            'status' => 'SENT',
        ])->assertOk()->assertJson(['recorded' => true]);

        // Id yang sama tidak dicatat dua kali.
        $this->withHeaders($headers)->postJson('/api/internal/whatsapp/messages', [
            'phone' => '6281200000099',
            'direction' => 'OUT',
            'body' => 'Tugas diterima',
            'external_id' => 'log-out-1',
            'status' => 'SENT',
        ])->assertOk()->assertJson(['recorded' => false]);

        $this->assertSame(1, WhatsappMessage::where('external_id', 'log-in-1')->count());
        $this->assertSame(1, WhatsappMessage::where('external_id', 'log-out-1')->count());
    }

    public function test_the_admin_dashboard_can_read_the_message_history(): void
    {
        WhatsappMessage::create([
            'phone' => '6281200000099',
            'role' => 'COURIER',
            'direction' => WhatsappMessage::IN,
            'body' => 'terima',
            'status' => WhatsappMessage::RECEIVED,
            'tracking_number' => self::RESI,
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/messages?resi='.self::RESI)
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'terima')
            ->assertJsonPath('messages.0.direction', 'IN');

        $this->actingAs($this->courier)
            ->getJson('/api/admin/messages')
            ->assertForbidden();
    }

    private function openIssue(Package $package): IssueReport
    {
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
