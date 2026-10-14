<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\BuyerProfileResource;
use App\Models\Buyer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Buyer $buyer */
        $buyer = $request->user();

        return response()->json([
            'data' => new BuyerProfileResource($buyer),
        ]);
    }
}
