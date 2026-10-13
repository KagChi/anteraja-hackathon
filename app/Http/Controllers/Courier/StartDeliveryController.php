<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Courier\StartDelivery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\StartDeliveryRequest;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class StartDeliveryController extends Controller
{
    public function __invoke(StartDeliveryRequest $request, string $trackingNumber, StartDelivery $action): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        $data = $request->toData();

        foreach ($data->checklist as $item) {
            if ($item['answer'] === true) {
                return response()->json([
                    'message' => 'Pemeriksaan fisik menemukan kendala. Silakan laporkan kendala.',
                    'redirect' => "/courier/deliveries/{$package->tracking_number}/issue",
                    'hasIssue' => true,
                ], 422);
            }
        }

        $delivered = $action->execute($package, $courier, $data);

        return response()->json([
            'message' => 'Pengantaran telah dimulai.',
            'package' => $delivered->fresh(['statusRef', 'latestMilestone']),
        ]);
    }
}
