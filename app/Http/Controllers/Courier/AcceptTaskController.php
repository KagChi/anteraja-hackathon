<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Courier\AcceptTask;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AcceptTaskController extends Controller
{
    public function __invoke(Request $request, string $trackingNumber, AcceptTask $action): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        $accepted = $action->execute($package, $courier);

        return response()->json([
            'message' => 'Tugas berhasil diterima.',
            'package' => $accepted->fresh(['statusRef']),
        ]);
    }
}
