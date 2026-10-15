<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GpsAction;
use App\Enums\GpsLevel;
use App\Enums\GpsLockStatus;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\GpsLockRequest;
use App\Models\LocationCheck;
use App\Models\Package;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The courier side of GPS validation: a blocked action, the review request that
 * follows it, and the hub approval that unlocks the retry.
 */
final class CourierGpsValidationTest extends TestCase {
    use DatabaseTransactions;

    private Staff $courier;

    private Staff $admin;

    /** Destination in Tebet, Jakarta Selatan. */
    private const DESTINATION_LAT = -6.229746;

    private const DESTINATION_LNG = 106.848529;

    protected function setUp(): void {
        parent::setUp();

        Storage::fake('local');

        $this->courier = Staff::where('role', StaffRole::COURIER)->where('email', 'budi@anteraja.id')->firstOrFail();
        $this->admin = Staff::where('role', StaffRole::ADMIN)->firstOrFail();
    }

    public function test_a_mocked_fix_blocks_the_depart_and_opens_a_review(): void {
        $package = $this->makePackage();

        $response = $this->actingAs($this->courier)->postJson(
            "/api/courier/deliveries/{$package->tracking_number}/depart",
            $this->departPayload($this->mockedWindow()),
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'FAKE_GPS_SUSPECTED')
            ->assertJsonPath('action', 'DEPART')
            ->assertJsonStructure(['reasons' => [['code', 'label', 'detail', 'severity']], 'lock_request_id']);

        // The parcel never left the hub.
        $this->assertSame(PackageStatus::COURIER_ASSIGNED, $package->fresh()?->status);

        $check = LocationCheck::where('package_id', $package->id)->latest('id')->firstOrFail();
        $this->assertSame(GpsLevel::BLOCKED, $check->level);
        $this->assertFalse($check->applied);

        $lock = GpsLockRequest::where('package_id', $package->id)->firstOrFail();
        $this->assertSame(GpsLockStatus::PENDING, $lock->status);
        $this->assertSame($lock->id, $response->json('lock_request_id'));
    }

    public function test_the_hub_approval_unlocks_the_retry(): void {
        $package = $this->makePackage();
        $this->confirmWindow($package);
        $payload = $this->departPayload($this->mockedWindow());

        $this->actingAs($this->courier)
            ->postJson("/api/courier/deliveries/{$package->tracking_number}/depart", $payload)
            ->assertStatus(422);

        $lock = GpsLockRequest::where('package_id', $package->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/gps-checks/{$lock->id}/decision", [
                'decision' => 'APPROVED',
                'decision_reason' => 'Petugas hub memverifikasi kurir memang di hub.',
            ])
            ->assertOk()
            ->assertJsonPath('lock.status', 'APPROVED');

        // The courier retries with the same device signals and gets through.
        $retry = $this->actingAs($this->courier)->postJson(
            "/api/courier/deliveries/{$package->tracking_number}/depart",
            $payload,
        );

        $retry->assertOk();
        $this->assertSame(PackageStatus::IN_DELIVERY, $package->fresh()?->status);

        $check = LocationCheck::where('package_id', $package->id)->latest('id')->firstOrFail();
        $this->assertTrue($check->applied);
        $this->assertSame($lock->id, $check->override_id);
    }

    public function test_a_courier_can_ask_the_hub_to_review_and_the_request_is_idempotent(): void {
        $package = $this->makePackage();

        $this->actingAs($this->courier)
            ->postJson("/api/courier/deliveries/{$package->tracking_number}/depart", $this->departPayload($this->mockedWindow()))
            ->assertStatus(422);

        $first = $this->actingAs($this->courier)->postJson(
            "/api/courier/deliveries/{$package->tracking_number}/gps-review",
            ['action' => 'DEPART'],
        );
        $first->assertCreated()->assertJsonPath('data.status', 'PENDING');

        $second = $this->actingAs($this->courier)->postJson(
            "/api/courier/deliveries/{$package->tracking_number}/gps-review",
            ['action' => 'DEPART'],
        );
        $second->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, GpsLockRequest::where('package_id', $package->id)->count());
    }

    public function test_proof_of_delivery_outside_the_radius_is_refused(): void {
        $package = $this->makePackage(status: PackageStatus::IN_DELIVERY);
        $package->update(['delivery_pin_hash' => Hash::make('123456')]);

        $response = $this->actingAs($this->courier)->post(
            "/api/courier/deliveries/{$package->tracking_number}/proof",
            [
                'received_by' => 'Rina Penerima',
                'recipient_relation' => 'SELF',
                'pin' => '123456',
                // Roughly 2 km north of the destination.
                'latitude' => -6.211700,
                'longitude' => self::DESTINATION_LNG,
                'gps_signals' => [
                    [
                        'latitude' => -6.211700,
                        'longitude' => self::DESTINATION_LNG,
                        'accuracy' => 9.0,
                        'timestamp' => (int) Carbon::now('UTC')->valueOf(),
                    ],
                ],
                'device_timestamp' => (int) Carbon::now('UTC')->valueOf(),
                'photo' => UploadedFile::fake()->image('proof.jpg'),
            ],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'OUTSIDE_GEOFENCE')
            ->assertJsonStructure(['distance_m', 'radius_m', 'lock_request_id']);

        $this->assertSame(PackageStatus::IN_DELIVERY, $package->fresh()?->status);
    }

    public function test_proof_of_delivery_inside_the_radius_completes_the_shipment(): void {
        $package = $this->makePackage(status: PackageStatus::IN_DELIVERY);
        $package->update(['delivery_pin_hash' => Hash::make('123456')]);

        $response = $this->actingAs($this->courier)->post(
            "/api/courier/deliveries/{$package->tracking_number}/proof",
            [
                'received_by' => 'Rina Penerima',
                'recipient_relation' => 'SELF',
                'pin' => '123456',
                // About 15 m from the destination.
                'latitude' => -6.229876,
                'longitude' => self::DESTINATION_LNG,
                'gps_signals' => [
                    [
                        'latitude' => -6.229876,
                        'longitude' => self::DESTINATION_LNG,
                        'accuracy' => 9.0,
                        'timestamp' => (int) Carbon::now('UTC')->valueOf(),
                    ],
                ],
                'device_timestamp' => (int) Carbon::now('UTC')->valueOf(),
                'photo' => UploadedFile::fake()->image('proof.jpg'),
            ],
            ['Accept' => 'application/json'],
        );

        $response->assertOk();
        $this->assertSame(PackageStatus::RECEIVED, $package->fresh()?->status);

        $check = LocationCheck::where('package_id', $package->id)->latest('id')->firstOrFail();
        $this->assertSame(GpsAction::POD, $check->action);
        $this->assertTrue($check->inside_geofence);
        $this->assertTrue($check->applied);
    }

    public function test_the_courier_flow_still_works_without_a_gps_fix(): void {
        $package = $this->makePackage();
        $this->confirmWindow($package);

        $this->actingAs($this->courier)->postJson(
            "/api/courier/deliveries/{$package->tracking_number}/depart",
            ['checklist' => []],
        )->assertOk();

        $this->assertSame(PackageStatus::IN_DELIVERY, $package->fresh()?->status);
        $this->assertSame(0, LocationCheck::where('package_id', $package->id)->count());
    }

    public function test_the_delivery_screen_reports_the_geofence_context(): void {
        $package = $this->makePackage(status: PackageStatus::IN_DELIVERY);

        $this->actingAs($this->courier)
            ->getJson("/api/courier/deliveries/{$package->tracking_number}")
            ->assertOk()
            ->assertJsonPath('delivery.gps.radius_m', 100)
            ->assertJsonPath('delivery.gps.destination.latitude', self::DESTINATION_LAT);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mockedWindow(): array {
        $now = Carbon::now('UTC');

        return [
            [
                'latitude' => self::DESTINATION_LAT,
                'longitude' => self::DESTINATION_LNG,
                'accuracy' => 0.0,
                'timestamp' => (int) $now->valueOf(),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $signals
     * @return array<string, mixed>
     */
    private function departPayload(array $signals): array {
        return [
            'checklist' => [],
            'temperature_c' => -20.5,
            'latitude' => self::DESTINATION_LAT,
            'longitude' => self::DESTINATION_LNG,
            'device_timestamp' => (int) Carbon::now('UTC')->valueOf(),
            'gps_signals' => $signals,
        ];
    }

    private function confirmWindow(Package $package): void {
        ScheduleConfirmation::create([
            'package_id' => $package->id,
            'response' => 'READY',
            'requested_at' => Carbon::now('UTC'),
            'responded_at' => Carbon::now('UTC'),
        ]);
    }

    private function makePackage(PackageStatus $status = PackageStatus::COURIER_ASSIGNED): Package {
        $buyer = Buyer::create([
            'name' => 'Rina Pengirim',
            'phone' => '+6281299900033',
            'password' => 'rahasia123',
            'address' => 'Jl. Kemang Raya No. 10',
            'area' => 'Kemang, Jakarta Selatan',
            'latitude' => -6.260000,
            'longitude' => 106.810000,
            'is_active' => true,
        ]);

        $package = Package::create([
            'buyer_id' => $buyer->id,
            'created_by' => $this->admin->id,
            'courier_id' => $this->courier->id,
            // The destination the courier is measured against is the recipient.
            'recipient_name' => 'Rina Penerima',
            'recipient_phone' => '+6281299900044',
            'recipient_address' => 'Jl. Tebet Barat Dalam No. 5',
            'recipient_area' => 'Tebet',
            'recipient_address_type' => 'RESIDENTIAL',
            'recipient_latitude' => self::DESTINATION_LAT,
            'recipient_longitude' => self::DESTINATION_LNG,
            'status' => $status,
            'scheduled_at' => Carbon::now('UTC')->addHour(),
            'weight_kg' => 1.0,
            'courier_accepted_at' => Carbon::now('UTC'),
        ]);

        // The tracking number is a PostgreSQL default, so re-read to get it.
        return $package->refresh();
    }
}
