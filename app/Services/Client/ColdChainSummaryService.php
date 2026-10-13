<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\DTOs\Client\ColdChainSummary;
use App\Enums\Client\ColdChainState;
use App\Models\ConditionCheck;
use App\Models\Package;

final class ColdChainSummaryService
{
    public function forPackage(Package $package): ColdChainSummary
    {
        /** @var ConditionCheck|null $latestCheck */
        $latestCheck = $package->conditionChecks()->latest('checked_at')->first();

        $min = (float) config('coldchain.standard_temperature_range.min', -25.0);
        $max = (float) config('coldchain.standard_temperature_range.max', -18.0);

        if ($latestCheck !== null && $latestCheck->temperature_c !== null) {
            $temp = (float) $latestCheck->temperature_c;
            $isOptimal = ($temp >= $min && $temp <= $max);
            $temperatureLabel = rtrim(rtrim(sprintf('%.1f', $temp), '0'), '.').'°C';
            $temperatureState = $isOptimal ? 'Optimal' : 'DiLuarBatas';
            $isMeasured = true;
            $recordedAt = $latestCheck->checked_at?->timezone('Asia/Jakarta')->format('H.i').' WIB';
        } else {
            $temperatureLabel = (string) config('coldchain.target_temperature', '-18°C ~ -25°C');
            $temperatureState = 'Target';
            $isMeasured = false;
            $recordedAt = null;
        }

        $hasAnyCheck = $package->conditionChecks()->exists();

        if (! $hasAnyCheck) {
            $chainState = ColdChainState::MONITORED->value;
        } else {
            $hasBreach = $package->conditionChecks()
                ->where(function ($query) use ($min, $max) {
                    $query->where('temperature_c', '<', $min)
                        ->orWhere('temperature_c', '>', $max);
                })->exists();

            $hasTempIssue = $package->conditionChecks()
                ->whereHas('issueReport', function ($query) {
                    $query->where('issue_category', 'suhu_naik');
                })->exists();

            if ($hasBreach || $hasTempIssue) {
                $chainState = ColdChainState::NEEDS_ATTENTION->value;
            } else {
                $chainState = ColdChainState::MAINTAINED->value;
            }
        }

        return new ColdChainSummary(
            temperatureLabel: $temperatureLabel,
            temperatureState: $temperatureState,
            chainState: $chainState,
            isMeasured: $isMeasured,
            recordedAt: $recordedAt,
        );
    }
}
