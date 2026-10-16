<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Staff;
use App\Services\Client\OrderPresenter;
use App\Support\DisplayTime;
use App\Support\PhoneFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menghubungkan satu nomor WhatsApp dengan paket yang sedang ia tangani, jadi
 * workflow n8n inbound tahu langkah apa yang sedang menunggu.
 *
 * Penerima dan admin dikenali dari nomor pengirimnya:
 *
 * - BUYER: nomor harus sama dengan nomor penerima paket itu — orang yang
 *   menerima kiriman, bukan pelanggan yang mengirimnya. Pelanggan memantau
 *   pesanannya di aplikasi dan tidak ikut percakapan pengantaran.
 * - ADMIN: paket yang sedang menunggu keputusan kendala.
 *
 * Kurir tidak memakai WhatsApp, jadi tidak ada peran COURIER di sini.
 *
 * Token paket ikut dikembalikan karena workflow memakainya untuk menerapkan
 * balasan penerima; tautan aplikasi pelanggan tidak, sebab penerima tidak
 * punya akun.
 */
final class WhatsappLookupController extends Controller {
    public function __invoke(
        Request $request,
        OrderPresenter $presenter,
    ): JsonResponse {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'resi' => ['required', 'string', 'max:32'],
        ]);

        $phone = PhoneFormatter::toE164Id($validated['phone']);
        $resi = strtoupper(trim($validated['resi']));

        /** @var Package|null $package */
        $package = Package::query()
            ->where('tracking_number', $resi)
            ->with(['buyer', 'courier', 'items.product'])
            ->first();

        if ($package === null) {
            return response()->json(['found' => false, 'role' => null]);
        }

        if ($package->recipient_phone !== null && $package->recipient_phone === $phone) {
            return response()->json([
                'found' => true,
                'role' => 'BUYER',
                'tracking_number' => $package->tracking_number,
                'client_token' => $package->client_token,
                'template' => $this->templateFor($presenter, $package),
                'status_label' => $this->statusLabel($package),
                'window' => DisplayTime::windowLabel($package->scheduled_at, $package->scheduled_end_at),
            ]);
        }

        /** @var Staff|null $staff */
        $staff = Staff::query()
            ->where('is_active', true)
            ->whereIn('phone', PhoneFormatter::candidates($validated['phone']))
            ->first();

        if ($staff !== null && $staff->role === StaffRole::ADMIN) {
            return response()->json([
                'found' => true,
                'role' => 'ADMIN',
                'tracking_number' => $package->tracking_number,
                'template' => $package->status === PackageStatus::ISSUE_REPORTED ? 'admin_decision' : 'none',
                'status_label' => $this->statusLabel($package),
                'window' => DisplayTime::windowLabel($package->scheduled_at, $package->scheduled_end_at),
            ]);
        }

        // Nomor orang lain: jangan mengaku resi itu miliknya.
        return response()->json(['found' => false, 'role' => null]);
    }

    /**
     * Apa yang menunggu pembeli, dinyatakan sebagai template notifikasi yang
     * akan dikirim — workflow memakai ini untuk memilih balasannya, jadi
     * domainnya yang menentukan, bukan workflow yang menebak.
     */
    private function templateFor(OrderPresenter $presenter, Package $package): string {
        return match ($presenter->resolveActionRequired($package)) {
            'confirm_schedule' => 'schedule_request',
            'request_replacement' => 'issue_decision',
            'track' => 'in_delivery',
            default => 'none',
        };
    }

    private function statusLabel(Package $package): string {
        return (string) __("client.status.{$package->status->value}", [], 'id');
    }
}
