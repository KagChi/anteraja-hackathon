<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tracking;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * Public resi lookup, for the "Lacak Pesananmu" panel on the landing page.
 *
 * A tracking number is the only thing the visitor has, so this endpoint is
 * reachable without a session — which means it must not hand out anything that
 * identifies the recipient. It answers with the status, the agreed window, the
 * destination city (regency level, derived from the buyer's area) and a timeline
 * built from fixed labels: no names, no phone numbers, no street addresses, and
 * no free text written by staff or couriers.
 */
final class TrackController extends Controller
{
    /**
     * Public wording for each package status. A step is described by what
     * happened, never by who did it.
     *
     * @var array<string, array{label: string, detail: string}>
     */
    private const STEPS = [
        'CREATED' => ['label' => 'Pesanan diterima', 'detail' => 'Paket terdaftar di sistem hub.'],
        'AWAITING_CONFIRMATION' => ['label' => 'Menunggu konfirmasi jadwal', 'detail' => 'Jendela pengantaran belum dikonfirmasi.'],
        'RESCHEDULE_REQUESTED' => ['label' => 'Jadwal ulang diminta', 'detail' => 'Jendela pengantaran sedang disusun ulang.'],
        'CONFIRMED' => ['label' => 'Jadwal dikonfirmasi', 'detail' => 'Pengiriman masuk jadwal hub.'],
        'COURIER_ASSIGNED' => ['label' => 'Kurir ditugaskan', 'detail' => 'Paket beku ini dipegang seorang kurir.'],
        'CHECKED' => ['label' => 'Pemeriksaan selesai', 'detail' => 'Kondisi dan suhu paket diperiksa sebelum berangkat.'],
        'ISSUE_REPORTED' => ['label' => 'Pengiriman terkendala', 'detail' => 'Hub sedang menangani kendala pada paket ini.'],
        'IN_DELIVERY' => ['label' => 'Paket sedang diantar', 'detail' => 'Kurir menuju alamat penerima.'],
        'RECEIVED' => ['label' => 'Paket diterima', 'detail' => 'Paket sudah diserahkan ke penerima.'],
        'RETURNED' => ['label' => 'Paket dikembalikan', 'detail' => 'Paket kembali ke hub.'],
        'CLOSED' => ['label' => 'Pengiriman selesai', 'detail' => 'Paket sudah ditutup.'],
    ];

    public function __invoke(string $resi): JsonResponse
    {
        $trackingNumber = strtoupper(trim($resi));

        $package = Package::query()
            ->where('tracking_number', $trackingNumber)
            ->with(['buyer', 'statusRef', 'statusLogs', 'deliveryMilestones'])
            ->first();

        if ($package === null) {
            return response()->json([
                'message' => 'Nomor resi tidak ditemukan. Periksa kembali nomor di bukti pengiriman.',
                'code' => 'TRACKING_NOT_FOUND',
            ], 404);
        }

        $timeline = $this->timeline($package);
        $lastEvent = $timeline === [] ? $package->updated_at : null;

        return response()->json([
            'data' => [
                'resi' => $package->tracking_number,
                'status' => [
                    'code' => $package->status->value,
                    'label' => $package->statusRef?->label ?? $package->status->value,
                    'tone' => $package->status->tone()->value,
                ],
                'service' => $package->service_type?->label() ?? 'Reguler',
                'category' => $package->package_category,
                // Same wording the customer app shows on its order lists.
                'cold_chain' => 'Beku -18°C',
                'destination_city' => $this->city($package->buyer?->area),
                'schedule_label' => DisplayTime::windowLabel($package->scheduled_at, $package->scheduled_end_at),
                'updated_label' => $lastEvent !== null
                    ? DisplayTime::dateTimeIndo($lastEvent)
                    : ($timeline === [] ? '-' : (string) $timeline[count($timeline) - 1]['time']),
                'timeline' => $timeline,
            ],
        ]);
    }

    /**
     * The journey as status changes and courier milestones, oldest first.
     *
     * @return list<array{label: string, time: string, detail: string, is_accent: bool}>
     */
    private function timeline(Package $package): array
    {
        $entries = [];

        foreach ($package->statusLogs as $log) {
            $code = $log->to_status instanceof \App\Enums\PackageStatus ? $log->to_status->value : (string) $log->to_status;
            $step = self::STEPS[$code] ?? ['label' => $code, 'detail' => ''];
            $entries[] = [
                'at' => Carbon::instance($log->changed_at),
                'label' => $step['label'],
                'detail' => $step['detail'],
            ];
        }

        foreach ($package->deliveryMilestones as $milestone) {
            $entries[] = [
                'at' => Carbon::instance($milestone->occurred_at),
                'label' => $milestone->milestone === \App\Enums\DeliveryMilestoneEnum::DEPARTED
                    ? 'Kurir berangkat'
                    : 'Kurir tiba di lokasi',
                'detail' => $milestone->milestone === \App\Enums\DeliveryMilestoneEnum::DEPARTED
                    ? 'Perjalanan pengantaran dimulai dari hub.'
                    : 'Kurir sudah berada di alamat tujuan.',
            ];
        }

        usort($entries, static fn (array $a, array $b): int => $a['at']->timestamp <=> $b['at']->timestamp);

        $timeline = [];
        $last = count($entries) - 1;

        foreach ($entries as $index => $entry) {
            $timeline[] = [
                'label' => $entry['label'],
                'time' => DisplayTime::dateTimeIndo($entry['at']),
                'detail' => $entry['detail'],
                'is_accent' => $index === $last,
            ];
        }

        return $timeline;
    }

    /** District or city only: "Tebet, Jakarta Selatan" becomes "Jakarta Selatan". */
    private function city(?string $area): ?string
    {
        $area = trim((string) $area);

        if ($area === '') {
            return null;
        }

        $parts = explode(',', $area);
        $city = trim((string) end($parts));

        return $city === '' ? null : $city;
    }
}
