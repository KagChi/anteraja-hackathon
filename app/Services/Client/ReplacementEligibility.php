<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Models\Package;

final readonly class EligibilityResult
{
    public function __construct(
        public bool $isEligible,
        public ?Package $childPackage = null,
        public ?string $reason = null,
    ) {}
}

final class ReplacementEligibility
{
    public function check(Package $package): EligibilityResult
    {
        /** @var Package|null $child */
        $child = Package::where('resend_of_package_id', $package->id)->first();

        if ($child !== null) {
            return new EligibilityResult(
                isEligible: false,
                childPackage: $child,
                reason: 'Paket pengganti sudah pernah dibuat.',
            );
        }

        $allowedStatuses = [
            PackageStatus::RETURNED,
            PackageStatus::CLOSED,
        ];

        if (! in_array($package->status, $allowedStatuses, true)) {
            return new EligibilityResult(
                isEligible: false,
                childPackage: null,
                reason: 'Status paket saat ini belum selesai dikembalikan.',
            );
        }

        $latestIssue = $package->latestIssue;
        if ($latestIssue === null || $latestIssue->decision !== IssueDecision::RETURN) {
            return new EligibilityResult(
                isEligible: false,
                childPackage: null,
                reason: 'Keputusan kendala bukan pengembalian/retur.',
            );
        }

        return new EligibilityResult(
            isEligible: true,
            childPackage: null,
            reason: null,
        );
    }
}
