<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The hub assigns the courier first and the recipient confirms the window
 * afterwards, so a parcel is CONFIRMED when the courier inspects it. Finding a
 * problem in that state used to be refused, which left the parcel stuck: the
 * depart was rejected with "laporkan kendala" and the report answered 500.
 */
final class CourierIssueAfterConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $courier;

    private Staff $admin;

    private Buyer $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->courier = Staff::where('role', StaffRole::COURIER)
            ->where('email', 'budi@anteraja.id')
            ->firstOrFail();

        $this->admin = Staff::where('role', StaffRole::ADMIN)->firstOrFail();

        $this->buyer = Buyer::firstOrCreate(
            ['phone' => '+6281200000099'],
            [
                'name' => 'Penerima Konfirmasi',
                'address' => 'Jl. Tebet Barat Dalam No. 99, Jakarta Selatan',
                'area' => 'Tebet, Jakarta Selatan',
            ]
        );
    }

    private function confirmedPackageHeldByCourier(): Package
    {
        return Package::create([
            'buyer_id' => $this->buyer->id,
            'created_by' => $this->admin->id,
            'courier_id' => $this->courier->id,
            'status' => PackageStatus::CONFIRMED,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'weight_kg' => 1.00,
        ])->refresh();
    }

    public function test_courier_can_report_an_issue_after_the_recipient_confirmed(): void
    {
        Storage::fake('local');

        $package = $this->confirmedPackageHeldByCourier();

        $response = $this->actingAs($this->courier)->postJson(
            "/api/courier/deliveries/{$package->tracking_number}/issue",
            [
                'issue_category' => 'kemasan_bocor',
                'description' => 'Kemasan sobek ditemukan saat ceklis sebelum berangkat.',
                'photo' => UploadedFile::fake()->image('issue.jpg', 640, 480),
                'checklist' => [
                    'packaging_damaged' => ['answer' => true, 'detail' => 'Sobek di sudut'],
                ],
            ]
        );

        $response->assertOk();
        $this->assertSame(PackageStatus::ISSUE_REPORTED, $package->refresh()->status);
    }

    public function test_a_refused_courier_action_answers_with_a_readable_422(): void
    {
        $package = $this->confirmedPackageHeldByCourier();

        $this->actingAs($this->courier)
            ->postJson("/api/courier/deliveries/{$package->tracking_number}/arrive")
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Paket tidak dalam proses pengantaran.');
    }

    public function test_the_issue_form_is_offered_after_the_recipient_confirmed(): void
    {
        $package = $this->confirmedPackageHeldByCourier();

        $this->actingAs($this->courier)
            ->getJson("/api/courier/deliveries/{$package->tracking_number}/issue")
            ->assertOk()
            ->assertJsonStructure(['issueForm' => ['tracking', 'recipient', 'reporter', 'options']]);
    }
}
