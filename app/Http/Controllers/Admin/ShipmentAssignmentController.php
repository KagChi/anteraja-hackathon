<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Packages\AssignCourier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCourierRequest;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;

final class ShipmentAssignmentController extends Controller
{
    public function __invoke(
        AssignCourierRequest $request,
        string $trackingNumber,
        AssignCourier $action
    ): JsonResponse {
        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        $courier = Staff::findOrFail((int) $request->validated('courier_id'));

        /** @var Staff $admin */
        $admin = $request->user();

        $action->execute($package, $courier, $admin);

        return response()->json([
            'message' => "Kurir {$courier->name} berhasil ditugaskan untuk resi {$trackingNumber}.",
            'package' => $package->fresh(['courier', 'statusRef']),
        ]);
    }
}
