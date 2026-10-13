<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Enums\ResolutionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

final class IssueReport extends Model
{
    use HasFactory;

    protected $table = 'issue_reports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'check_id',
        'issue_category',
        'description',
        'photo_url',
        'quality_inspection_note',
        'decision',
        'decision_reason',
        'decided_by',
        'decided_at',
        'reported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_id' => 'integer',
            'issue_category' => IssueCategory::class,
            'decision' => IssueDecision::class,
            'decided_by' => 'integer',
            'decided_at' => 'immutable_datetime',
            'reported_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ConditionCheck, $this>
     */
    public function check(): BelongsTo
    {
        return $this->belongsTo(ConditionCheck::class, 'check_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function decidedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'decided_by');
    }

    /**
     * @return HasOneThrough<Package, ConditionCheck, $this>
     */
    public function package(): HasOneThrough
    {
        return $this->hasOneThrough(
            Package::class,
            ConditionCheck::class,
            'id',          // ConditionCheck.id
            'id',          // Package.id
            'check_id',    // IssueReport.check_id
            'package_id'   // ConditionCheck.package_id
        );
    }

    public function resolutionStatus(?Package $targetPackage = null, ?Package $resendPkg = null): ResolutionStatus
    {
        if ($this->decision === null) {
            return ResolutionStatus::MENUNGGU_KEPUTUSAN;
        }

        $pkg = $targetPackage ?? $this->package;

        return match ($this->decision) {
            IssueDecision::RETURN => ResolutionStatus::SELESAI,
            IssueDecision::PROCEED => ($pkg?->status === PackageStatus::RECEIVED || $pkg?->status === PackageStatus::CLOSED)
                ? ResolutionStatus::SELESAI
                : ResolutionStatus::DILANJUTKAN,
            IssueDecision::RESEND => ($resendPkg?->status === PackageStatus::RECEIVED || $resendPkg?->status === PackageStatus::CLOSED)
                ? ResolutionStatus::SELESAI
                : ResolutionStatus::PROSES_KIRIM_ULANG,
            IssueDecision::RESCHEDULE => ($pkg?->status === PackageStatus::RECEIVED || $pkg?->status === PackageStatus::CLOSED)
                ? ResolutionStatus::SELESAI
                : ResolutionStatus::DIJADWALKAN,
            IssueDecision::RECHECK => ResolutionStatus::PEMERIKSAAN_ULANG,
        };
    }
}
