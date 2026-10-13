<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\Client\TrackingViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackingController extends Controller
{
    public function __construct(
        private readonly TrackingViewService $trackingViewService
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        $view = $this->trackingViewService->build($package);

        return response()->json([
            'success' => true,
            'data' => $view->toArray(),
        ]);
    }
}
