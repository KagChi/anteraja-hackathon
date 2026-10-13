<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\Client\ScheduleViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleViewService $scheduleViewService
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        $view = $this->scheduleViewService->build($package);

        return response()->json([
            'success' => true,
            'data' => $view->toArray(),
        ]);
    }
}
