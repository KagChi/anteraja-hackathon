<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Courier\RejectTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\RejectTaskRequest;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class RejectTaskController extends Controller
{
    public function __invoke(RejectTaskRequest $request, string $trackingNumber, RejectTask $action): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        $rejected = $action->execute($package, $courier, $request->input('reason'));

        return response()->json([
            'message' => "Tugas {$package->tracking_number} telah ditolak.",
            'package' => $rejected->fresh(['statusRef']),
        ]);
    }
}
