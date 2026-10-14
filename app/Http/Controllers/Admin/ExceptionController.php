<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\IssueCategory;
use App\Http\Controllers\Controller;
use App\Models\ConditionCheck;
use App\Models\IssueReport;
use App\Models\Package;
use App\Queries\ExceptionIndexQuery;
use App\Support\DisplayTime;
use App\Support\MediaStorage;
use App\Support\PackageTimelineBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExceptionController extends Controller
{
    public function index(Request $request, ExceptionIndexQuery $query): JsonResponse
    {
        $tab = $request->query('tab') === 'resolved' ? 'resolved' : 'active';
        $paginated = $query->paginate($tab);

        $transformedIssues = collect($paginated->items())->map(function (IssueReport $issue) {
            $pkg = $issue->check?->package;
            $cat = $issue->issue_category ?? IssueCategory::KEMASAN_BOCOR;
            $reportedAtLocal = DisplayTime::toDisplay($issue->reported_at);

            return [
                'id' => $issue->id,
                'tracking' => $pkg?->tracking_number ?? '-',
                'buyer' => $pkg?->buyer?->name ?? '-',
                'courier' => $issue->check?->courier?->name ?? '-',
                'category' => $cat->label(),
                'categoryKey' => $cat->value,
                'description' => $issue->description,
                'decision' => $issue->decision?->value,
                'decision_label' => $issue->decision?->label(),
                'reported_at' => $reportedAtLocal?->format('H:i').' WIB',
                'reported_date' => DisplayTime::label($issue->reported_at),
                'resolutionStatus' => $issue->resolutionStatus($pkg)->value,
            ];
        });

        $data = [
            'tab' => $tab,
            'issues' => $transformedIssues,
            'paginator' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];

        return response()->json($data);
    }

    public function show(string $trackingNumber, PackageTimelineBuilder $timelineBuilder): JsonResponse
    {
        $package = Package::where('tracking_number', $trackingNumber)
            ->with([
                'buyer',
                'courier',
                'statusRef',
                'items.product',
                'conditionChecks.issueReport.decidedByStaff',
                'conditionChecks.courier',
                'statusLogs.changedByStaff',
            ])
            ->firstOrFail();

        // Find the latest condition check with an issue report
        /** @var ConditionCheck|null $checkWithIssue */
        $checkWithIssue = $package->conditionChecks->first(fn (ConditionCheck $chk) => $chk->issueReport !== null);
        if ($checkWithIssue === null || $checkWithIssue->issueReport === null) {
            abort(404, 'Paket tidak memiliki laporan kendala.');
        }

        $issueReport = $checkWithIssue->issueReport;
        $category = IssueCategory::fromOrFallback($issueReport->issue_category?->value);
        $allowedDecisions = collect($category->allowedDecisions())->map(fn ($d) => [
            'value' => $d->value,
            'label' => $d->label(),
            'helpText' => $d->helpText(),
        ])->values()->all();

        $timeline = $timelineBuilder->build($package);

        $checklistConfig = config('coldchain.checklist_items', []);
        $rawChecklist = is_array($checkWithIssue->checklist) ? $checkWithIssue->checklist : [];

        $checklistItems = [];
        foreach ($checklistConfig as $key => $label) {
            $item = $rawChecklist[$key] ?? null;
            $answered = isset($item['answer']) && $item['answer'] === true;
            $detail = $item['detail'] ?? ($answered ? 'Terbukti' : 'Normal');

            $checklistItems[] = [
                'key' => $key,
                'label' => $label,
                'answered' => $answered,
                'detail' => $detail,
            ];
        }

        $data = [
            'package' => $package,
            'issueReport' => $issueReport,
            'conditionCheck' => $checkWithIssue,
            // The modal shows the delivery status and the resolution side by
            // side; both tones come from the enums so the frontend never has to
            // mirror them.
            'statusLabel' => $package->statusRef?->label ?? $package->status->value,
            'tone' => $package->status->tone()->value,
            'resolutionStatus' => $issueReport->resolutionStatus($package)->value,
            'resolutionTone' => $issueReport->resolutionStatus($package)->tone()->value,
            'category' => [
                'key' => $category->value,
                'label' => $category->label(),
            ],
            'allowedDecisions' => $allowedDecisions,
            'checklist' => $checklistItems,
            'timeline' => $timeline,
            'reportedAtFormatted' => DisplayTime::toDisplay($issueReport->reported_at)?->format('H:i:s').' WIB',
            'photoTemporaryUrl' => $issueReport->photo_url ? MediaStorage::temporaryUrl($issueReport->photo_url) : null,
        ];

        return response()->json($data);
    }
}
