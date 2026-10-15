<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use App\Models\Staff;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Laporan harian untuk admin: dikirim oleh penjadwal, dan bisa dipicu kapan
 * saja dari dashboard supaya bisa didemokan tanpa menunggu jamnya.
 */
final class DailyReportWhatsAppTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    private Staff $courier;

    private Buyer $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config(['coldchain.n8n.enabled' => false]);

        $this->admin = Staff::firstOrCreate(
            ['email' => 'admin.daily.report@anteraja.id'],
            [
                'name' => 'Dimas Admin',
                'role' => StaffRole::ADMIN,
                'phone' => '081199000011',
                'password' => 'secret123',
                'is_active' => true,
            ]
        );

        $this->courier = Staff::firstOrCreate(
            ['email' => 'courier.daily.report@anteraja.id'],
            [
                'name' => 'Budi Kurir',
                'role' => StaffRole::COURIER,
                'phone' => '081299000011',
                'password' => 'secret123',
                'is_active' => true,
            ]
        );

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => '+6281399000011'],
            [
                'name' => 'Rina Pembeli',
                'address' => 'Jl. Melati Indah No. 42, Jakarta Selatan',
                'area' => 'Tebet Barat',
            ]
        );

        $this->product = Product::firstOrCreate(
            ['name' => 'Nugget Ayam 1 kg'],
            ['handling_notes' => 'Simpan di cold box.']
        );
    }

    private function packageToday(): Package
    {
        $day = Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->startOfDay();
        [$startUtc] = DisplayTime::dayRange($day);

        /** @var Package $package */
        $package = Package::create([
            'buyer_id' => $this->buyer->id,
            'created_by' => $this->admin->id,
            'courier_id' => $this->courier->id,
            'status' => PackageStatus::IN_DELIVERY,
            'scheduled_at' => $startUtc->copy()->addHours(3),
            'scheduled_end_at' => $startUtc->copy()->addHours(5),
            'weight_kg' => 1.0,
        ])->refresh();

        PackageItem::create([
            'package_id' => $package->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        return $package;
    }

    public function test_the_dashboard_button_queues_the_daily_report(): void
    {
        $this->packageToday();

        $response = $this->actingAs($this->admin)->postJson('/api/admin/reports/daily-whatsapp');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('queued'));

        $summary = $response->json('summary');
        $this->assertSame(Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->format('Y-m-d'), $summary['report_day']);
        $this->assertGreaterThanOrEqual(1, $summary['total_shipments']);
        $this->assertGreaterThanOrEqual(1, $summary['in_process_count']);
        // Angka kendala mengikuti data demo yang ada, jadi yang diperiksa di
        // sini hanya bentuknya: semua kunci terisi angka.
        $this->assertIsInt($summary['issue_count']);
        $this->assertIsInt($summary['decision_count']);
        $this->assertArrayHasKey('unanswered_count', $summary);

        $notification = Notification::where('recipient_id', $this->admin->id)
            ->where('template', 'daily_report')
            ->first();

        $this->assertNotNull($notification, 'laporan diantre untuk admin');
        $this->assertSame('ADMIN', $notification->recipient_type->value);
        // Nomor dinormalkan ke E.164 sebelum dikirim ke workflow n8n.
        $this->assertSame('+6281199000011', $notification->payload['recipient_phone']);
        $this->assertNull($notification->package_id);
    }

    public function test_the_report_never_goes_to_a_courier(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/reports/daily-whatsapp')->assertOk();

        $this->assertSame(0, Notification::where('recipient_id', $this->courier->id)->where('template', 'daily_report')->count());
    }

    public function test_only_an_admin_can_trigger_the_report(): void
    {
        $this->postJson('/api/admin/reports/daily-whatsapp')->assertUnauthorized();

        // actingAs menempel pada sisa test, jadi pemeriksaan tamu dijalankan
        // lebih dulu.
        $this->actingAs($this->courier)->postJson('/api/admin/reports/daily-whatsapp')->assertForbidden();
    }

    public function test_the_scheduled_command_queues_yesterdays_report_on_demand(): void
    {
        $yesterday = Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->subDay()->format('Y-m-d');

        $this->artisan('report:send-daily', ['--date' => $yesterday])
            ->expectsOutputToContain($yesterday)
            ->assertSuccessful();

        $notification = Notification::where('recipient_id', $this->admin->id)
            ->where('template', 'daily_report')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame($yesterday, $notification->payload['report_day']);
    }
}
