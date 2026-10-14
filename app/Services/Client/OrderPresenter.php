<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Enums\ConfirmationResponse;
use App\Enums\PackageStatus;
use App\Models\Package;

final class OrderPresenter
{
    public function __construct(
        private readonly ClientScreenResolver $screenResolver,
        private readonly ReplacementEligibility $replacementEligibility,
    ) {}

    public function resolveNextScreen(Package $package): string
    {
        return $this->screenResolver->resolve($package)->value;
    }

    public function resolveActionRequired(Package $package): ?string
    {
        $status = $package->status;

        if ($status === PackageStatus::AWAITING_CONFIRMATION) {
            $latestConfirmation = $package->relationLoaded('scheduleConfirmations')
                ? $package->scheduleConfirmations->sortByDesc('created_at')->first()
                : $package->scheduleConfirmations()->latest('created_at')->first();

            if ($latestConfirmation === null || $latestConfirmation->response === ConfirmationResponse::PENDING) {
                return 'confirm_schedule';
            }
        }

        if (in_array($status, [PackageStatus::RETURNED, PackageStatus::CLOSED], true)) {
            $eligibility = $this->replacementEligibility->check($package);
            if ($eligibility->isEligible) {
                return 'request_replacement';
            }
        }

        if (
            in_array($status, [PackageStatus::ISSUE_REPORTED, PackageStatus::RETURNED], true)
            && $package->conditionChecks()->whereHas('issueReport')->exists()
        ) {
            return 'view_issue';
        }

        if ($status === PackageStatus::IN_DELIVERY) {
            return 'track';
        }

        return null;
    }

    /**
     * @return array{product: string, product_meta: string}
     */
    public function formatProductSummary(Package $package): array
    {
        $items = $package->relationLoaded('items')
            ? $package->items
            : $package->items()->with('product')->get();

        $firstItem = $items->first();
        $firstName = $firstItem?->product?->name ?? 'Paket Frozen';
        $additionalCount = $items->count() - 1;

        $product = $additionalCount > 0
            ? "{$firstName} +{$additionalCount} lainnya"
            : $firstName;

        $koli = $package->koli_count ?? $items->count() ?: 1;
        $weightGrams = (int) round(((float) ($package->weight_kg ?? 1.0)) * 1000);
        $weightFormatted = number_format($weightGrams, 0, ',', '.');
        $productMeta = "{$koli} Paket • Berat {$weightFormatted}g";

        return [
            'product' => $product,
            'product_meta' => $productMeta,
        ];
    }

    /**
     * @return array{eta_date: ?string, eta_time: ?string}
     */
    public function formatSchedule(Package $package): array
    {
        $scheduledAt = $package->scheduled_at?->timezone('Asia/Jakarta');
        $scheduledEndAt = $package->scheduled_end_at?->timezone('Asia/Jakarta');

        if ($scheduledAt === null) {
            return [
                'eta_date' => null,
                'eta_time' => null,
            ];
        }

        $etaDate = $scheduledAt->locale('id')->translatedFormat('l, d M Y');
        $startTime = $scheduledAt->format('H.i');
        $endTime = $scheduledEndAt !== null
            ? $scheduledEndAt->format('H.i')
            : $scheduledAt->addHours(2)->format('H.i');

        $etaTime = "{$startTime} - {$endTime} WIB";

        return [
            'eta_date' => $etaDate,
            'eta_time' => $etaTime,
        ];
    }

    /**
     * @return array{code: string, label: string, tone: string}
     */
    public function formatStatus(Package $package): array
    {
        $status = $package->status;
        $label = (string) __("client.status.{$status->value}", [], 'id');
        $tone = $status->tone()->value;

        return [
            'code' => $status->value,
            'label' => $label,
            'tone' => $tone,
        ];
    }

    /**
     * @return array{completed_label: ?string, completed_at: ?string}
     */
    public function formatCompletion(Package $package): array
    {
        $delivery = $package->relationLoaded('delivery') ? $package->delivery : $package->delivery;
        $deliveredAt = $delivery?->delivered_at;

        if ($deliveredAt === null) {
            return [
                'completed_label' => null,
                'completed_at' => null,
            ];
        }

        $jktTime = $deliveredAt->timezone('Asia/Jakarta');
        $timePart = $jktTime->format('H.i');
        $label = $jktTime->isToday()
            ? "Diterima hari ini • {$timePart} WIB"
            : "Diterima {$jktTime->locale('id')->translatedFormat('d M Y')} • {$timePart} WIB";

        return [
            'completed_label' => $label,
            'completed_at' => $deliveredAt->toIso8601String(),
        ];
    }
}
