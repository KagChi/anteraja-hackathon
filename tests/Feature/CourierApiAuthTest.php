<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Staff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Couriers sign in with staff email + password and get a Sanctum token, which
 * is what lets the courier app reach the same API the console does.
 */
final class CourierApiAuthTest extends TestCase {
    use DatabaseTransactions;

    protected function setUp(): void {
        parent::setUp();

        RateLimiter::clear('courier-auth');
    }

    public function test_a_courier_signs_in_and_reaches_the_courier_api(): void {
        $this->courier('courier.api.auth@anteraja.id');

        $response = $this->postJson('/api/courier/auth/login', [
            'email' => 'courier.api.auth@anteraja.id',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'expires_at', 'courier' => ['name', 'status']]]);

        $token = (string) $response->json('data.token');
        $this->assertNotSame('', $token);

        // The issued token carries the "courier" ability and authenticates the
        // courier API without a session.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/courier/tasks')
            ->assertOk();
    }

    public function test_a_wrong_password_is_rejected(): void {
        $this->courier('courier.api.wrong@anteraja.id');

        $this->postJson('/api/courier/auth/login', [
            'email' => 'courier.api.wrong@anteraja.id',
            'password' => 'not-the-password',
        ])->assertUnauthorized();
    }

    public function test_an_admin_cannot_sign_in_to_the_courier_app(): void {
        Staff::firstOrCreate(
            ['email' => 'admin.api.auth@anteraja.id'],
            [
                'name' => 'Admin Courier Auth',
                'role' => StaffRole::ADMIN,
                'phone' => '+6281100000777',
                'password' => 'secret123',
                'is_active' => true,
            ],
        );

        $this->postJson('/api/courier/auth/login', [
            'email' => 'admin.api.auth@anteraja.id',
            'password' => 'secret123',
        ])->assertForbidden();
    }

    public function test_an_inactive_courier_cannot_sign_in(): void {
        Staff::firstOrCreate(
            ['email' => 'courier.api.inactive@anteraja.id'],
            [
                'name' => 'Inactive Courier',
                'role' => StaffRole::COURIER,
                'phone' => '+6281200000777',
                'password' => 'secret123',
                'is_active' => false,
            ],
        );

        $this->postJson('/api/courier/auth/login', [
            'email' => 'courier.api.inactive@anteraja.id',
            'password' => 'secret123',
        ])->assertForbidden();
    }

    public function test_logout_revokes_the_token(): void {
        $this->courier('courier.api.logout@anteraja.id');

        $token = (string) $this->postJson('/api/courier/auth/login', [
            'email' => 'courier.api.logout@anteraja.id',
            'password' => 'secret123',
        ])->json('data.token');

        $tokenId = (int) explode('|', $token, 2)[0];
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/courier/auth/logout')
            ->assertOk();

        // Revoked for good: the token row is gone, so the app's stored token
        // stops working on the next launch rather than only on this request.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    private function courier(string $email): Staff {
        return Staff::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Courier API Auth',
                'role' => StaffRole::COURIER,
                'phone' => '+6281200000666',
                'password' => 'secret123',
                'is_active' => true,
            ],
        );
    }
}
