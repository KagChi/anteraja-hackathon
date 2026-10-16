<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The courier-to-admin half of the demo: the courier reports a leak and the hub
 * decides the return, with the recipient told in between.
 *
 * It asserts the notification rows the workflow picks up, so the WhatsApp menu
 * and the decision message cannot silently disappear.
 */
final class IssueDecisionFlowTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $courier;

    private Staff $admin;

    private Buyer $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->courier = Staff::where('role', StaffRole::COURIER)
            ->where('email', 'budi@anteraja.id')
            ->firstOrFail();

        $this->admin = Staff::where('role', StaffRole::ADMIN)->firstOrFail();

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => '+6281200000098'],
            [
                'name' => 'Penerima Kendala',
                'address' => 'Jl. Tebet Barat Dalam No. 98, Jakarta Selatan',
                'area' => 'Tebet, Jakarta Selatan',
            ]
        );
    }

    private function assignedPackage(): Package
    {
        return Package::create([
            'buyer_id' => $this->buyer->id,
            'created_by' => $this->admin->id,
            'courier_id' => $this->courier->id,
            'recipient_name' => $this->buyer->name,
            'recipient_phone' => $this->buyer->phone,
            'recipient_address' => $this->buyer->address,
            'status' => PackageStatus::COURIER_ASSIGNED,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'weight_kg' => 1.00,
        ])->refresh();
    }

    public function test_the_courier_report_reaches_the_hub_without_notifying_the_buyer(): void
    {
        Storage::fake('local');

        $package = $this->assignedPackage();

        $this->actingAs($this->courier)
            ->postJson("/api/courier/deliveries/{$package->tracking_number}/issue", [
                'issue_category' => 'kemasan_bocor',
                'description' => 'Kemasan luar sobek dan basah saat pemeriksaan sebelum berangkat.',
                'photo' => UploadedFile::fake()->image('issue.jpg', 640, 480),
            ])
            ->assertOk();

        $this->assertSame(PackageStatus::ISSUE_REPORTED, $package->refresh()->status);

        // The hub is told to decide; the buyer is no longer asked to choose.
        $this->assertTrue(Notification::where('template', 'issue_reported')
            ->where('recipient_type', RecipientType::ADMIN)
            ->exists());

        $this->assertSame(0, Notification::where('package_id', $package->id)
            ->where('recipient_type', RecipientType::BUYER)
            ->count());
    }

    public function test_the_admin_return_decision_reaches_the_buyer(): void
    {
        Storage::fake('local');

        $package = $this->assignedPackage();

        $this->actingAs($this->courier)
            ->postJson("/api/courier/deliveries/{$package->tracking_number}/issue", [
                'issue_category' => 'kemasan_bocor',
                'description' => 'Kemasan luar sobek dan basah saat pemeriksaan sebelum berangkat.',
                'photo' => UploadedFile::fake()->image('issue.jpg', 640, 480),
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/exceptions/{$package->tracking_number}/decision", [
                'decision' => 'RETURN',
                'decision_reason' => 'Kemasan bocor, paket dikembalikan ke hub asal untuk klaim retur.',
            ])
            ->assertOk();

        $this->assertSame(PackageStatus::RETURNED, $package->refresh()->status);

        $this->assertTrue(Notification::where('template', 'issue_decision_update')
            ->where('recipient_type', RecipientType::BUYER)
            ->exists());
    }
}
