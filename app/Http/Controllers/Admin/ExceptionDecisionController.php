<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Issues\ResolveIssue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveIssueRequest;
use App\Models\ConditionCheck;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;

final class ExceptionDecisionController extends Controller
{
    public function __invoke(
        ResolveIssueRequest $request,
        string $trackingNumber,
        ResolveIssue $action
    ): JsonResponse {
        $package = Package::where('tracking_number', $trackingNumber)
            ->with(['conditionChecks.issueReport'])
            ->firstOrFail();

        /** @var ConditionCheck|null $checkWithIssue */
        $checkWithIssue = $package->conditionChecks->first(
            fn (ConditionCheck $chk) => $chk->issueReport !== null && $chk->issueReport->decision === null
        );

        if ($checkWithIssue === null || $checkWithIssue->issueReport === null) {
            return response()->json([
                'message' => 'Kendala sudah diputuskan sebelumnya.',
            ], 422);
        }

        /** @var Staff $admin */
        $admin = $request->user();

        $action->execute($package, $checkWithIssue->issueReport, $request->toData(), $admin);

        return response()->json([
            'message' => "Keputusan kendala untuk resi {$trackingNumber} berhasil disimpan.",
            'package' => $package->fresh(['statusRef']),
        ]);
    }
}
