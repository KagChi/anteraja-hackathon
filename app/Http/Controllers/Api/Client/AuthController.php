<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\ClaimAccountRequest;
use App\Http\Requests\Api\Client\LoginRequest;
use App\Http\Requests\Api\Client\RegisterRequest;
use App\Http\Resources\Client\BuyerResource;
use App\Models\Buyer;
use App\Models\Package;
use App\Support\PhoneFormatter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AuthController extends Controller
{
    /**
     * Self sign-up from the customer app.
     *
     * The customer shops before signing in, so registration is the moment the
     * order can finally be placed: it returns the same bearer token shape the
     * login endpoint does, and the app continues straight to checkout.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $buyer = Buyer::create([
            'name' => (string) $request->validated('name'),
            'phone' => (string) $request->validated('phone'),
            'password' => (string) $request->validated('password'),
            'address' => (string) $request->validated('address'),
            'area' => $request->validated('area'),
            'address_type' => $request->validated('address_type') ?? 'RESIDENTIAL',
            'is_active' => true,
        ]);

        $expiresAt = Carbon::now()->addDays(30);
        $token = $buyer->createToken('client-mobile', ['client'], $expiresAt)->plainTextToken;

        return response()->json([
            'message' => 'Akun berhasil dibuat.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'buyer' => new BuyerResource($buyer),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $phoneInput = (string) $request->validated('phone');
        $password = (string) $request->validated('password');

        $normalizedPhone = PhoneFormatter::toE164Id($phoneInput);

        /** @var Buyer|null $buyer */
        $buyer = Buyer::query()
            ->where('phone', $normalizedPhone)
            ->orWhere('phone', $phoneInput)
            ->first();

        if ($buyer === null || $buyer->password === null || ! Hash::check($password, $buyer->password)) {
            return response()->json([
                'message' => 'Nomor atau password salah.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        if (! $buyer->is_active) {
            return response()->json([
                'message' => 'Akun pembeli dinonaktifkan.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        $expiresAt = Carbon::now()->addDays(30);
        $token = $buyer->createToken('client-mobile', ['client'], $expiresAt)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'buyer' => new BuyerResource($buyer),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Buyer && $user->currentAccessToken() !== null) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Berhasil logout.',
            'code' => 'LOGGED_OUT',
        ]);
    }

    public function claim(ClaimAccountRequest $request): JsonResponse
    {
        /** @var Package|null $package */
        $package = $request->attributes->get('client_package');

        if ($package === null) {
            $tokenParam = $request->route('token') ?? $request->route('package');
            $token = $tokenParam instanceof Package ? $tokenParam->client_token : (string) $tokenParam;
            $package = Package::query()->where('client_token', $token)->first();
        }

        if ($package === null) {
            return response()->json([
                'message' => 'Tautan klaim tidak valid.',
                'code' => 'LINK_INVALID',
            ], 404);
        }

        $buyer = $package->buyer;

        if ($buyer === null) {
            return response()->json([
                'message' => 'Data pembeli tidak ditemukan.',
                'code' => 'BUYER_NOT_FOUND',
            ], 404);
        }

        if ($buyer->password !== null) {
            return response()->json([
                'message' => 'Akun pembeli ini sudah memiliki password. Silakan login langsung.',
                'code' => 'ACCOUNT_ALREADY_CLAIMED',
            ], 409);
        }

        $buyer->password = $request->validated('password');
        $buyer->phone_verified_at = Carbon::now();
        $buyer->save();

        $expiresAt = Carbon::now()->addDays(30);
        $apiToken = $buyer->createToken('client-mobile', ['client'], $expiresAt)->plainTextToken;

        return response()->json([
            'message' => 'Akun berhasil diklaim dan diaktifkan.',
            'data' => [
                'token' => $apiToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'buyer' => new BuyerResource($buyer),
            ],
        ], 200);
    }
}
