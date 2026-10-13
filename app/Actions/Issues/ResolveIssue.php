<?php

declare(strict_types=1);

namespace App\Actions\Issues;

use App\Actions\Issues\Decisions\ProceedDecisionHandler;
use App\Actions\Issues\Decisions\RecheckDecisionHandler;
use App\Actions\Issues\Decisions\RescheduleDecisionHandler;
use App\Actions\Issues\Decisions\ResendDecisionHandler;
use App\Actions\Issues\Decisions\ReturnDecisionHandler;
use App\DTOs\ResolveIssueData;
use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Exceptions\IssueAlreadyDecided;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ResolveIssue
{
    public function __construct(
        private readonly ReturnDecisionHandler $returnHandler,
        private readonly ProceedDecisionHandler $proceedHandler,
        private readonly RecheckDecisionHandler $recheckHandler,
        private readonly ResendDecisionHandler $resendHandler,
        private readonly RescheduleDecisionHandler $rescheduleHandler,
    ) {}

    public function execute(Package $package, IssueReport $issueReport, ResolveIssueData $data, Staff $admin): void
    {
        DB::transaction(function () use ($package, $issueReport, $data, $admin): void {
            // Lock package and issue report to prevent concurrent decisions
            /** @var Package $lockedPackage */
            $lockedPackage = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            /** @var IssueReport $lockedIssue */
            $lockedIssue = IssueReport::where('id', $issueReport->id)->lockForUpdate()->firstOrFail();

            if ($lockedPackage->status !== PackageStatus::ISSUE_REPORTED || $lockedIssue->decision !== null) {
                $lockedIssue->loadMissing('decidedByStaff');
                throw new IssueAlreadyDecided($lockedIssue->decidedByStaff?->name);
            }

            // Verify decision is allowed for this issue category
            $category = IssueCategory::fromOrFallback($lockedIssue->issue_category?->value);
            if (! in_array($data->decision, $category->allowedDecisions(), true)) {
                throw new InvalidArgumentException(
                    __('coldchain.errors.invalid_decision', ['decision' => $data->decision->value])
                );
            }

            // Fill decision details
            $nowUtc = Carbon::now('UTC');
            $lockedIssue->update([
                'decision' => $data->decision->value,
                'decision_reason' => $data->decisionReason,
                'quality_inspection_note' => $data->qualityInspectionNote,
                'decided_by' => $admin->id,
                'decided_at' => $nowUtc,
            ]);

            // Delegate to decision handler
            match ($data->decision) {
                IssueDecision::RETURN => $this->returnHandler->handle($lockedPackage, $lockedIssue, $data, $admin),
                IssueDecision::PROCEED => $this->proceedHandler->handle($lockedPackage, $lockedIssue, $data, $admin),
                IssueDecision::RECHECK => $this->recheckHandler->handle($lockedPackage, $lockedIssue, $data, $admin),
                IssueDecision::RESEND => $this->resendHandler->handle($lockedPackage, $lockedIssue, $data, $admin),
                IssueDecision::RESCHEDULE => $this->rescheduleHandler->handle($lockedPackage, $lockedIssue, $data, $admin),
            };
        });
    }
}
