<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GpsAction;
use App\Enums\GpsLevel;
use App\Enums\GpsLockStatus;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Buyer;
use App\Models\GeofencePolicy;
use App\Models\GpsLockRequest;
use App\Models\LocationCheck;
use App\Models\Package;
use App\Models\Staff;
use App\Services\Gps\GeofenceEvaluator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Pengaturan radius and Validasi GPS on the hub console.
 */
final class AdminGpsConsoleTest extends TestCase
{
    use DatabaseTransactions;

    private Staff $admin;

    private Staff $courier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = Staff::where('role', StaffRole::ADMIN)->firstOrFail();
        $this->courier = Staff::where('role', StaffRole::COURIER)->where('email', 'budi@anteraja.id')->firstOrFail();
    }

    public function test_the_gps_console_screens_render_for_an_admin(): void
    {
        $this->get('/admin/gps')->assertRedirect('/login');

        $this->actingAs($this->admin)
            ->get('/admin/gps')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/GpsChecks'));

        $this->actingAs($this->admin)
            ->get('/admin/geofence')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/Geofence'));
    }

    public function test_the_radius_settings_are_admin_only(): void
    {
        $this->getJson('/api/admin/geofence')->assertUnauthorized();
        $this->actingAs($this->courier)->getJson('/api/admin/geofence')->assertForbidden();
    }

    public function test_the_console_lists_the_radius_per_segment(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/geofence');

        $response->assertOk()->assertJsonStructure([
            'segments' => [['service_type', 'label', 'sla', 'description', 'min', 'max', 'step', 'radius_m', 'default_m']],
            'updated_at',
        ]);

        $this->assertSame('REGULER', $response->json('segments.1.service_type'));
        $this->assertSame(100, $response->json('segments.1.radius_m'));
    }

    public function test_the_radius_can_be_raised_within_the_segment_bounds(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/admin/geofence', [
            'policies' => [
                'REGULER' => 250,
                'INSTANT_FROZEN' => 45,
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Radius GPS berhasil diperbarui.');

        $this->assertSame(250, GeofencePolicy::find('REGULER')?->radius_m);
        $this->assertSame(45, GeofencePolicy::find('INSTANT_FROZEN')?->radius_m);
        $this->assertSame($this->admin->id, GeofencePolicy::find('REGULER')?->updated_by);

        // The courier endpoints read through this cache, so the write must land
        // there immediately.
        app(GeofenceEvaluator::class)->forget();
        $this->assertSame(250, app(GeofenceEvaluator::class)->radiusFor(\App\Enums\ServiceType::REGULER));
    }

    public function test_a_radius_outside_the_segment_bounds_is_rejected(): void
    {
        $this->actingAs($this->admin)->putJson('/api/admin/geofence', [
            'policies' => ['INSTANT_FROZEN' => 400],
        ])->assertStatus(422)->assertJsonValidationErrors('policies.INSTANT_FROZEN');
    }

    public function test_the_gps_queue_lists_flagged_checks_and_open_requests(): void
    {
        $package = $this->makePackage();
        $lock = $this->makeLock($package);

        LocationCheck::create([
            'package_id' => $package->id,
            'staff_id' => $this->courier->id,
            'action' => GpsAction::POD,
            'latitude' => -6.211700,
            'longitude' => 106.848529,
            'accuracy_m' => 9.0,
            'level' => GpsLevel::CLEAN,
            'reasons' => [],
            'distance_m' => 2000,
            'radius_m' => 100,
            'inside_geofence' => false,
            'applied' => false,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/gps-checks');

        $response->assertOk()->assertJsonStructure([
            'tab',
            'checks' => [['tracking', 'courier', 'action', 'action_label', 'level', 'tone', 'distance_m', 'radius_m', 'inside']],
            'locks' => [['id', 'tracking', 'courier', 'action_label', 'status', 'status_label', 'reasons']],
            'summary' => ['pending_requests', 'blocked_today', 'suspect_today', 'outside_radius_today'],
            'radius',
        ]);

        $this->assertSame(1, $response->json('summary.pending_requests'));
        $this->assertSame($lock->id, $response->json('locks.0.id'));
        $this->assertSame('danger', $response->json('checks.0.tone'));
    }

    public function test_approving_a_request_unlocks_the_shipment(): void
    {
        $package = $this->makePackage();
        $lock = $this->makeLock($package);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/gps-checks/{$lock->id}/decision", [
                'decision' => 'APPROVED',
                'decision_reason' => 'Kurir terverifikasi berada di lokasi penerima.',
            ])
            ->assertOk()
            ->assertJsonPath('lock.status', 'APPROVED');

        $decided = $lock->fresh();
        $this->assertSame(GpsLockStatus::APPROVED, $decided?->status);
        $this->assertSame($this->admin->id, $decided?->decided_by);
        $this->assertNotNull($decided?->decided_at);

        // A second decision on the same request is refused.
        $this->actingAs($this->admin)
            ->postJson("/api/admin/gps-checks/{$lock->id}/decision", [
                'decision' => 'REJECTED',
                'decision_reason' => 'Sudah diputuskan sebelumnya.',
            ])
            ->assertStatus(422);
    }

    public function test_a_decision_needs_a_reason(): void
    {
        $package = $this->makePackage();
        $lock = $this->makeLock($package);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/gps-checks/{$lock->id}/decision", [
                'decision' => 'REJECTED',
                'decision_reason' => 'pendek',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('decision_reason');
    }

    private function makeLock(Package $package): GpsLockRequest
    {
        return GpsLockRequest::create([
            'package_id' => $package->id,
            'staff_id' => $this->courier->id,
            'action' => GpsAction::POD,
            'level' => GpsLevel::BLOCKED,
            'reasons' => [
                ['code' => 'outside_geofence', 'label' => 'Di luar radius titik pengantaran', 'detail' => 'Jarak 2000 m.', 'severity' => 'strong'],
            ],
            'latitude' => -6.211700,
            'longitude' => 106.848529,
            'status' => GpsLockStatus::PENDING,
        ]);
    }

    private function makePackage(): Package
    {
        $buyer = Buyer::create([
            'name' => 'Penerima Radius',
            'phone' => '+6281299900044',
            'password' => 'rahasia123',
            'address' => 'Jl. Tebet Barat Dalam No. 5',
            'area' => 'Tebet',
            'latitude' => -6.229746,
            'longitude' => 106.848529,
            'is_active' => true,
        ]);

        return Package::create([
            'buyer_id' => $buyer->id,
            'created_by' => $this->admin->id,
            'courier_id' => $this->courier->id,
            'status' => PackageStatus::IN_DELIVERY,
            'scheduled_at' => Carbon::now('UTC')->addHour(),
            'weight_kg' => 1.0,
        ])->refresh();
    }
}
