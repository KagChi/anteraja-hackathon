<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Courier\ReportIssue;
use App\Enums\IssueCategory;
use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\ReportIssueRequest;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\Staff;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class IssueReportController extends Controller
{
    public function create(Request $request, string $trackingNumber): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)
            ->with(['buyer', 'statusRef'])
            ->firstOrFail();

        Gate::authorize('update', $package);

        $allowedStatuses = [
            PackageStatus::COURIER_ASSIGNED,
            PackageStatus::CHECKED,
            PackageStatus::IN_DELIVERY,
        ];

        if (! in_array($package->status, $allowedStatuses, true)) {
            return response()->json([
                'message' => 'Paket tidak dalam status yang dapat dilaporkan kendala.',
                'redirect' => "/courier/deliveries/{$package->tracking_number}",
            ], 422);
        }

        if ($package->status === PackageStatus::COURIER_ASSIGNED && $package->courier_accepted_at === null) {
            return response()->json([
                'message' => 'Tugas belum diterima. Silakan terima tugas terlebih dahulu.',
                'redirect' => '/courier/tasks',
            ], 422);
        }

        $openIssue = IssueReport::join('condition_checks', 'condition_checks.id', '=', 'issue_reports.check_id')
            ->where('condition_checks.package_id', $package->id)
            ->whereNull('issue_reports.decision')
            ->exists();

        if ($openIssue) {
            return response()->json([
                'message' => 'Kendala pada paket ini sudah dilaporkan sebelumnya.',
                'redirect' => "/courier/deliveries/{$package->tracking_number}",
            ], 422);
        }

        $options = array_map(
            fn (IssueCategory $cat) => ['value' => $cat->value, 'label' => $cat->label()],
            IssueCategory::cases()
        );

        return response()->json([
            'issueForm' => [
                'tracking' => $package->tracking_number,
                'recipient' => $package->buyer?->name ?? '',
                'reporter' => $courier->name,
                'noteLimit' => 200,
                'options' => $options,
                'checklistItems' => config('coldchain.checklist_items', []),
            ],
        ]);
    }

    public function store(ReportIssueRequest $request, string $trackingNumber, ReportIssue $action): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        $photoFile = $request->file('photo');
        $photoPath = MediaStorage::storeProof($photoFile, 'issues');

        $data = $request->toData($photoPath);
        $reported = $action->execute($package, $courier, $data);

        return response()->json([
            'message' => 'Laporan terkirim, menunggu keputusan admin.',
            'package' => $reported->fresh(['statusRef']),
        ]);
    }
}
