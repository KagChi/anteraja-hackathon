<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\Package;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CourierFlowTest extends TestCase {
    use DatabaseTransactions;

    private Staff $courier;

    private Staff $otherCourier;

    private Staff $admin;

    protected function setUp(): void {
        parent::setUp();

        $this->courier = Staff::where('role', StaffRole::COURIER)->where('email', 'budi@anteraja.id')->firstOrFail();
        $this->otherCourier = Staff::where('role', StaffRole::COURIER)->where('email', 'ahmad@anteraja.id')->firstOrFail();
        $this->admin = Staff::where('role', StaffRole::ADMIN)->firstOrFail();
    }

    public function test_access_controls(): void {
        // 1. Guest -> 401
        $this->getJson('/api/courier/tasks')->assertUnauthorized();

        // 2. Admin -> 403
        $this->actingAs($this->admin)->getJson('/api/courier/tasks')->assertForbidden();

        // 3. Inactive courier -> 403
        $inactiveCourier = new Staff([
            'name' => 'Inactive Courier',
            'role' => StaffRole::COURIER,
            'is_active' => false,
        ]);
        $this->actingAs($inactiveCourier)->getJson('/api/courier/tasks')->assertForbidden();

        // 4. Active courier -> 200
        $this->actingAs($this->courier)->getJson('/api/courier/tasks')->assertOk();
    }

    public function test_courier_cannot_access_another_couriers_package(): void {
        // AJ-FRZ-0012351 belongs to Budi
        // Ahmad should get 404
        $response = $this->actingAs($this->otherCourier)
            ->getJson('/api/courier/deliveries/AJ-FRZ-0012351');

        $response->assertNotFound();
    }

    public function test_courier_tasks_index_splits_new_and_active_tasks(): void {
        $response = $this->actingAs($this->courier)->getJson('/api/courier/tasks');

        $response->assertOk();
        $response->assertJsonStructure([
            'newTasks',
            'activeTasks',
            'courier' => ['name', 'status'],
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['newTasks']);
        $this->assertSame('AJ-FRZ-0012351', $data['newTasks'][0]['tracking']);
    }

    public function test_courier_task_distance_endpoint(): void {
        // Distance with the recipient's coordinates
        $response = $this->actingAs($this->courier)->getJson('/api/courier/tasks/AJ-FRZ-0012351/distance?lat=-6.220000&lng=106.840000');
        $response->assertOk();
        $response->assertJsonStructure(['distance_km', 'eta_minutes']);

        // Distance with no destination coordinates -> 204
        $pkg = Package::where('tracking_number', 'AJ-FRZ-0012351')->firstOrFail();
        $pkg->update(['recipient_latitude' => null, 'recipient_longitude' => null]);

        $response2 = $this->actingAs($this->courier)->getJson('/api/courier/tasks/AJ-FRZ-0012351/distance?lat=-6.220000&lng=106.840000');
        $response2->assertNoContent();
    }

    public function test_courier_accept_task_flow(): void {
        $response = $this->actingAs($this->courier)->postJson('/api/courier/tasks/AJ-FRZ-0012351/accept');

        $response->assertOk();
        $response->assertJsonPath('message', 'Tugas berhasil diterima.');

        $pkg = Package::where('tracking_number', 'AJ-FRZ-0012351')->firstOrFail();
        $this->assertNotNull($pkg->courier_accepted_at);

        // Idempotent / silent success on double-tap
        $retryResponse = $this->actingAs($this->courier)->postJson('/api/courier/tasks/AJ-FRZ-0012351/accept');
        $retryResponse->assertOk();
    }

    public function test_courier_reject_task_flow(): void {
        $response = $this->actingAs($this->courier)->postJson('/api/courier/tasks/AJ-FRZ-0012351/reject', [
            'reason' => 'Kendaraan mogok',
        ]);

        $response->assertOk();

        $pkg = Package::where('tracking_number', 'AJ-FRZ-0012351')->firstOrFail();
        $this->assertSame(PackageStatus::CONFIRMED, $pkg->status);
        $this->assertNull($pkg->courier_id);
        $this->assertNull($pkg->courier_accepted_at);
    }

    public function test_courier_delivery_show_and_guards(): void {
        // Unaccepted package cannot view delivery details directly
        $unacceptedResponse = $this->actingAs($this->courier)->getJson('/api/courier/deliveries/AJ-FRZ-0012351');
        $unacceptedResponse->assertStatus(422);

        // Accepted package can view delivery details
        $response = $this->actingAs($this->courier)->getJson('/api/courier/deliveries/AJ-FRZ-0012354');
        $response->assertOk();
        $response->assertJsonStructure([
            'delivery' => [
                'tracking',
                'service',
                'recipient',
                'phone',
                'phoneHref',
                'address',
                'instruction',
                'mapLabel',
                'mapHref',
                'status',
                'stage',
                'canDepart',
            ],
        ]);
    }

    public function test_courier_depart_flow(): void {
        // 1. Depart with issue -> returns 422
        $badDepartResponse = $this->actingAs($this->courier)->postJson('/api/courier/deliveries/AJ-FRZ-0012354/depart', [
            'checklist' => [
                'packaging_damaged' => ['answer' => true, 'detail' => 'Basah'],
            ],
        ]);
        $badDepartResponse->assertStatus(422);
        $badDepartResponse->assertJsonPath('hasIssue', true);

        // 2. Normal depart -> transitions to IN_DELIVERY
        // The recipient confirmed the receiving window, so the courier may go.
        $assigned = Package::where('tracking_number', 'AJ-FRZ-0012354')->firstOrFail();
        ScheduleConfirmation::firstOrCreate(
            ['package_id' => $assigned->id, 'response' => 'READY'],
            ['requested_at' => now(), 'responded_at' => now()],
        );

        $departResponse = $this->actingAs($this->courier)->postJson('/api/courier/deliveries/AJ-FRZ-0012354/depart', [
            'checklist' => [
                'packaging_damaged' => ['answer' => false],
                'ice_cream_melting' => ['answer' => false],
            ],
            'temperature_c' => -20.5,
        ]);

        $departResponse->assertOk();
        $pkg = Package::where('tracking_number', 'AJ-FRZ-0012354')->firstOrFail();
        $this->assertSame(PackageStatus::IN_DELIVERY, $pkg->status);
        // Departing mints the hand-over PIN and sends it to the recipient.
        $this->assertNotNull($pkg->delivery_pin_hash);
        $this->assertDatabaseHas('notifications', [
            'package_id' => $pkg->id,
            'template' => 'delivery_pin',
        ]);
    }

    public function test_courier_arrive_flow(): void {
        // AJ-FRZ-0012352 is IN_DELIVERY with DEPARTED milestone
        $response = $this->actingAs($this->courier)->postJson('/api/courier/deliveries/AJ-FRZ-0012352/arrive', [
            'latitude' => -6.229746,
            'longitude' => 106.848529,
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Status berhasil diperbarui: Tiba di lokasi.');

        // Idempotent check
        $retry = $this->actingAs($this->courier)->postJson('/api/courier/deliveries/AJ-FRZ-0012352/arrive');
        $retry->assertOk();
    }

    public function test_courier_proof_create_and_store_flow(): void {
        Storage::fake('local');

        // 1. GET proof form
        $createResponse = $this->actingAs($this->courier)->getJson('/api/courier/deliveries/AJ-FRZ-0012353/proof');
        $createResponse->assertOk();
        $createResponse->assertJsonStructure(['proofForm' => ['tracking', 'recipient', 'relations']]);

        // 2. POST proof confirmation
        // The recipient received the PIN on departure; the courier types it back.
        $proofPackage = Package::where('tracking_number', 'AJ-FRZ-0012353')->firstOrFail();
        $proofPackage->update(['delivery_pin_hash' => Hash::make('123456')]);

        $fakePhoto = UploadedFile::fake()->image('proof.jpg', 640, 480);
        $storeResponse = $this->actingAs($this->courier)->postJson('/api/courier/deliveries/AJ-FRZ-0012353/proof', [
            'received_by' => 'Rina Putri',
            'recipient_relation' => 'Penerima Sendiri',
            'pin' => '123456',
            'receipt_note' => 'Diserahkan langsung ke penerima di teras depan',
            'photo' => $fakePhoto,
        ]);

        $storeResponse->assertOk();
        $pkg = Package::where('tracking_number', 'AJ-FRZ-0012353')->firstOrFail();
        $this->assertSame(PackageStatus::RECEIVED, $pkg->status);
        $this->assertNotNull($pkg->delivery);
        $this->assertSame('Rina Putri', $pkg->delivery->received_by);
        // The code is single-use: the hash is cleared on success.
        $this->assertNull($pkg->delivery_pin_hash);
    }

    public function test_a_wrong_delivery_pin_is_refused(): void {
        Storage::fake('local');

        $pinPackage = Package::where('tracking_number', 'AJ-FRZ-0012353')->firstOrFail();
        $pinPackage->update(['delivery_pin_hash' => Hash::make('123456')]);

        $fakePhoto = UploadedFile::fake()->image('proof.jpg', 640, 480);
        $storeResponse = $this->actingAs($this->courier)->postJson('/api/courier/deliveries/AJ-FRZ-0012353/proof', [
            'received_by' => 'Rina Putri',
            'recipient_relation' => 'Penerima Sendiri',
            'pin' => '000000',
            'photo' => $fakePhoto,
        ]);

        $storeResponse->assertStatus(422);
        $this->assertNull(Package::where('tracking_number', 'AJ-FRZ-0012353')->firstOrFail()->delivery);
    }

    public function test_courier_report_issue_flow(): void {
        Storage::fake('local');

        // AJ-FRZ-0012354 (accepted COURIER_ASSIGNED)
        $createResponse = $this->actingAs($this->courier)->getJson('/api/courier/deliveries/AJ-FRZ-0012354/issue');
        $createResponse->assertOk();
        $createResponse->assertJsonStructure(['issueForm' => ['tracking', 'recipient', 'reporter', 'options']]);

        $fakePhoto = UploadedFile::fake()->image('issue.jpg', 640, 480);
        $storeResponse = $this->actingAs($this->courier)->postJson('/api/courier/deliveries/AJ-FRZ-0012354/issue', [
            'issue_category' => 'kemasan_bocor',
            'description' => 'Kemasan es krim bocor dan mencair di lokasi tujuan',
            'photo' => $fakePhoto,
            'checklist' => [
                'packaging_damaged' => ['answer' => true, 'detail' => 'Bocor'],
            ],
        ]);

        $storeResponse->assertOk();
        $pkg = Package::where('tracking_number', 'AJ-FRZ-0012354')->firstOrFail();
        $this->assertSame(PackageStatus::ISSUE_REPORTED, $pkg->status);
    }

    public function test_courier_history_and_profile(): void {
        // History
        $histResponse = $this->actingAs($this->courier)->getJson('/api/courier/history');
        $histResponse->assertOk();
        $histResponse->assertJsonStructure(['history', 'summary', 'pagination']);

        // Profile
        $profResponse = $this->actingAs($this->courier)->getJson('/api/courier/profile');
        $profResponse->assertOk();
        $profResponse->assertJsonStructure([
            'profile' => ['name', 'status', 'id', 'phone', 'hub', 'vehicle', 'stats'],
        ]);
        $this->assertSame('Budi Santoso', $profResponse->json('profile.name'));
    }
}
