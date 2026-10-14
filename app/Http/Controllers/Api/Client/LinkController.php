<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\OrderSummaryResource;
use App\Models\Package;
use App\Services\Client\OrderPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LinkController extends Controller
{
    public function __construct(
        private readonly OrderPresenter $orderPresenter,
    ) {}

    public function resolve(Request $request): JsonResponse
    {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        $nextScreen = $this->orderPresenter->resolveNextScreen($package);

        return response()->json([
            'next_screen' => $nextScreen,
            'order' => new OrderSummaryResource($package),
        ]);
    }
}
