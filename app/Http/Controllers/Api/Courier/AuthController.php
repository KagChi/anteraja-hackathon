<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Courier;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Couriers sign in the way the admin panel does — staff email and password —
 * but the app keeps a Sanctum token instead of a session cookie, so the same
 * courier API answers both.
 */
final class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var Staff|null $staff */
        $staff = Staff::query()->where('email', $credentials['email'])->first();

        if ($staff === null || $staff->password === null || ! Hash::check($credentials['password'], $staff->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        if ($staff->role !== StaffRole::COURIER) {
            return response()->json([
                'message' => 'Akun ini tidak memiliki akses aplikasi kurir.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        if (! $staff->is_active) {
            return response()->json([
                'message' => 'Akun kurir dinonaktifkan.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        $expiresAt = Carbon::now()->addDays(30);
        $token = $staff->createToken('courier-mobile', ['courier'], $expiresAt)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'courier' => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'phone' => $staff->phone,
                    'status' => $staff->is_active ? 'Aktif' : 'Nonaktif',
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Staff) {
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
        }

        return response()->json([
            'message' => 'Berhasil logout.',
            'code' => 'LOGGED_OUT',
        ]);
    }
}
