<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ServiceType;
use App\DTOs\Courier\GpsSignalData;
use App\Enums\GpsAction;
use App\Enums\GpsLevel;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\LocationCheck;
use App\Models\Package;
use App\Models\Staff;
use App\Services\Gps\FakeGpsDetector;
use App\Services\Gps\GeofenceEvaluator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The FRD-06 thresholds, ported from the capstone and re-expressed against the
 * fix window the courier app ships.
 */
final class GpsDetectionTest extends TestCase
{
    use DatabaseTransactions;

    private FakeGpsDetector $detector;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = app(FakeGpsDetector::class);
        $this->package = $this->makePackage();
    }

    public function test_a_normal_driving_window_is_clean(): void
    {
        $now = Carbon::now('UTC');
        $signals = GpsSignalData::fromArray($this->drivingWindow($now, accuracy: 8.0), (int) $now->valueOf());

        $assessment = $this->detector->assess($this->package, $signals, $now);

        $this->assertSame(GpsLevel::CLEAN, $assessment->level);
        $this->assertSame([], $assessment->reasons);
    }

    public function test_a_sub_metre_accuracy_is_blocked(): void
    {
        $now = Carbon::now('UTC');
        $signals = GpsSignalData::fromArray([
            ['latitude' => -6.229746, 'longitude' => 106.848529, 'accuracy' => 0.0, 'timestamp' => (int) $now->valueOf()],
        ], (int) $now->valueOf());

        $assessment = $this->detector->assess($this->package, $signals, $now);

        $this->assertSame(GpsLevel::BLOCKED, $assessment->level);
        $this->assertContains('accuracy_invalid', array_column($assessment->reasons, 'code'));
    }

    public function test_a_frozen_coordinate_feed_is_blocked(): void
    {
        $now = Carbon::now('UTC');
        $fixes = [];

        for ($i = 0; $i < 10; $i++) {
            $fixes[] = [
                'latitude' => -6.229746,
                'longitude' => 106.848529,
                'accuracy' => 1.5,
                'timestamp' => (int) $now->copy()->subSeconds(60 - ($i * 6))->valueOf(),
            ];
        }

        $assessment = $this->detector->assess($this->package, GpsSignalData::fromArray($fixes, (int) $now->valueOf()), $now);

        $this->assertSame(GpsLevel::BLOCKED, $assessment->level);
        $this->assertContains('frozen_fix', array_column($assessment->reasons, 'code'));
    }

    public function test_weak_signals_only_flag_the_attempt(): void
    {
        $now = Carbon::now('UTC');
        $signals = GpsSignalData::fromArray([
            [
                'latitude' => -6.229746,
                'longitude' => 106.848529,
                'accuracy' => 12.0,
                'timestamp' => (int) $now->copy()->subSeconds(1200)->valueOf(),
            ],
        ], (int) $now->valueOf());

        $assessment = $this->detector->assess($this->package, $signals, $now);

        $this->assertSame(GpsLevel::SUSPECTED, $assessment->level);
        $this->assertNotEmpty($assessment->reasons);
        $this->assertSame(['weak'], array_values(array_unique(array_column($assessment->reasons, 'severity'))));
    }

    public function test_an_impossible_jump_against_recorded_points_is_blocked(): void
    {
        $now = Carbon::now('UTC');

        // 300 km away, five minutes ago: no vehicle covers that. created_at is
        // set after the insert because it is not mass assignable.
        $previous = LocationCheck::create([
            'package_id' => $this->package->id,
            'staff_id' => $this->package->courier_id,
            'action' => GpsAction::ARRIVE,
            'latitude' => -6.917464,
            'longitude' => 107.619123,
            'level' => GpsLevel::CLEAN,
            'reasons' => [],
        ]);

        DB::table('location_checks')
            ->where('id', $previous->id)
            ->update(['created_at' => $now->copy()->subMinutes(5)]);

        $signals = GpsSignalData::fromArray([
            ['latitude' => -6.229746, 'longitude' => 106.848529, 'accuracy' => 6.0, 'timestamp' => (int) $now->valueOf()],
        ], (int) $now->valueOf());

        $assessment = $this->detector->assess($this->package, $signals, $now);

        $this->assertSame(GpsLevel::BLOCKED, $assessment->level);
        $this->assertContains('impossible_travel', array_column($assessment->reasons, 'code'));
    }

    public function test_the_geofence_uses_the_service_radius(): void
    {
        $geofence = app(GeofenceEvaluator::class);
        // The radius is measured against the parcel's destination — the
        // recipient the customer named, not the sender's own address.
        $destination = $this->package->recipient;

        $this->assertSame(100, $geofence->radiusFor(ServiceType::REGULER));
        $this->assertSame(30, $geofence->radiusFor(ServiceType::INSTANT_FROZEN));

        // Roughly 150 m north of the destination.
        $outside = $geofence->evaluate(
            $destination['latitude'],
            $destination['longitude'],
            -6.228400,
            106.845600,
            ServiceType::INSTANT_FROZEN,
        );
        $this->assertFalse($outside['inside']);
        $this->assertGreaterThan($outside['radius_m'], $outside['distance_m']);

        // Roughly 15 m away.
        $inside = $geofence->evaluate(
            $destination['latitude'],
            $destination['longitude'],
            -6.229876,
            106.848529,
            ServiceType::INSTANT_FROZEN,
        );
        $this->assertTrue($inside['inside']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drivingWindow(Carbon $now, float $accuracy): array
    {
        $fixes = [];

        for ($i = 0; $i < 10; $i++) {
            $fixes[] = [
                'latitude' => -6.229746 + ($i * 0.0002),
                'longitude' => 106.848529 + ($i * 0.0002),
                'accuracy' => $accuracy,
                'timestamp' => (int) $now->copy()->subSeconds(90 - ($i * 9))->valueOf(),
            ];
        }

        return $fixes;
    }

    private function makePackage(): Package
    {
        $admin = Staff::where('role', StaffRole::ADMIN)->firstOrFail();
        $courier = Staff::where('role', StaffRole::COURIER)->firstOrFail();

        $buyer = Buyer::create([
            'name' => 'Rina Pengirim',
            'phone' => '+6281255500077',
            'password' => 'rahasia123',
            'address' => 'Jl. Tebet Barat Dalam No. 5',
            'area' => 'Tebet',
            'latitude' => -6.229746,
            'longitude' => 106.848529,
            'is_active' => true,
        ]);

        return Package::create([
            'buyer_id' => $buyer->id,
            'created_by' => $admin->id,
            'courier_id' => $courier->id,
            'recipient_name' => 'Penerima GPS',
            'recipient_phone' => $buyer->phone,
            'recipient_address' => $buyer->address,
            'recipient_area' => $buyer->area,
            'recipient_address_type' => 'RESIDENTIAL',
            'recipient_latitude' => $buyer->latitude,
            'recipient_longitude' => $buyer->longitude,
            'status' => PackageStatus::IN_DELIVERY,
            'scheduled_at' => Carbon::now('UTC')->addHour(),
            'weight_kg' => 1.0,
            'courier_accepted_at' => Carbon::now('UTC'),
        ]);
    }
}
