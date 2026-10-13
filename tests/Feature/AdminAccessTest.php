<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Staff;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
{
    public function test_guest_is_unauthorized_when_accessing_admin_api(): void
    {
        $response = $this->getJson('/api/admin/shipments');

        $response->assertUnauthorized();
    }

    public function test_courier_is_forbidden_from_admin_api(): void
    {
        $courier = new Staff([
            'name' => 'Test Courier',
            'role' => StaffRole::COURIER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($courier)->getJson('/api/admin/shipments');

        $response->assertForbidden();
    }

    public function test_inactive_admin_is_forbidden_from_admin_api(): void
    {
        $inactiveAdmin = new Staff([
            'name' => 'Inactive Admin',
            'role' => StaffRole::ADMIN,
            'is_active' => false,
        ]);

        $response = $this->actingAs($inactiveAdmin)->getJson('/api/admin/shipments');

        $response->assertForbidden();
    }

    public function test_login_api_authenticates_admin(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'dimas@anteraja.id',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email', 'role', 'phone'],
        ]);
    }
}
