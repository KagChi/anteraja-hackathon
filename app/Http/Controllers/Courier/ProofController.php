<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Courier\ConfirmDelivery;
use App\Enums\GpsAction;
use App\Enums\PackageStatus;
use App\Enums\RecipientRelation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\ConfirmDeliveryRequest;
use App\Models\Package;
use App\Models\Staff;
use App\Support\MediaStorage;
use App\Services\Gps\GpsValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ProofController extends Controller
{
    public function create(Request $request, string $trackingNumber): JsonResponse
    {
        $package = Package::where('tracking_number', $trackingNumber)
            ->with('buyer')
            ->firstOrFail();

        Gate::authorize('update', $package);

        if ($package->status !== PackageStatus::IN_DELIVERY) {
            return response()->json([
                'message' => 'Paket tidak dalam proses pengantaran aktif.',
                'redirect' => "/courier/deliveries/{$package->tracking_number}",
            ], 422);
        }

        return response()->json([
            'proofForm' => [
                'tracking' => $package->tracking_number,
                'service' => $package->service_type?->label() ?? 'Reguler',
                'recipient' => $package->buyer?->name ?? '',
                'relations' => [
                    'Penerima Sendiri',
                    'Keluarga Serumah',
                    'Rekan Kerja',
                    'Satpam / Sekuriti',
                ],
                'relationOptions' => RecipientRelation::options(),
                'notesPlaceholder' => 'Contoh: Diterima langsung, diletakkan di teras',
            ],
        ]);
    }

    public function store(
        ConfirmDeliveryRequest $request,
        string $trackingNumber,
        ConfirmDelivery $action,
        GpsValidator $gps,
    ): JsonResponse {
        /** @var Staff $courier */
        $courier = $request->user();

        $package = Package::where('tracking_number', $trackingNumber)->firstOrFail();
        Gate::authorize('update', $package);

        if ($package->status !== PackageStatus::IN_DELIVERY) {
            return response()->json([
                'message' => 'Paket tidak dalam status pengantaran aktif.',
                'redirect' => "/courier/deliveries/{$package->tracking_number}",
            ], 422);
        }

        // The radius is enforced here: proof of delivery is the moment the
        // parcel changes hands, so being far from the destination matters.
        $gps->validate(
            package: $package,
            courier: $courier,
            action: GpsAction::POD,
            signals: $request->gpsSignals(),
            latitude: $request->fixLatitude(),
            longitude: $request->fixLongitude(),
            enforceGeofence: true,
        );

        $photoFile = $request->file('photo');
        $photoPath = MediaStorage::storeProof($photoFile, 'proofs');

        $data = $request->toData($photoPath);
        $delivered = $action->execute($package, $courier, $data);

        return response()->json([
            'message' => "Pengiriman {$package->tracking_number} selesai.",
            'package' => $delivered->fresh(['statusRef', 'delivery']),
        ]);
    }
}
