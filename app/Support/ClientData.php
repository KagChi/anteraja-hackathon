<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fixture data for the native customer screens.
 *
 * Ported one-for-one from resources/js/data/client.ts so the APK shows the
 * same prototype the web console does. Every customer screen reads through
 * this class, so it is the single seam to replace when the flow is wired to
 * the token API (/api/client/{token}/...) — nothing else reads those values.
 */
final class ClientData {
    /**
     * Every order on the signed-in customer's account.
     *
     * status_key drives the chip colour (see statusTone) and which extra
     * action a tracking screen offers; schedule and issue are present only
     * on the orders that have one.
     */
    public static function orders(): array {
        return [
            [
                'resi' => 'AJ-FRZ-0012345',
                'product' => 'Nugget Ayam 1 kg',
                'product_meta' => '1 Paket • Berat 1.000g',
                'recipient' => 'Rina Putri',
                'recipient_area' => 'Kebayoran Baru',
                'address' => 'Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410',
                'status' => 'Sedang Diantar',
                'status_key' => 'transit',
                'eta_date' => 'Kamis, 15 Okt 2024',
                'eta_time' => '13.45 WIB',
                'courier' => 'Budi Santoso',
                'courier_box' => 'Box #04',
                'courier_tel' => 'tel:081234567890',
                'temperature' => '-18°C',
                'temperature_state' => 'Optimal',
                'cold_chain' => 'Terjaga',
                'timeline' => [
                    [
                        'label' => 'Sedang Diantar',
                        'time' => '13.15',
                        'detail' => 'Kurir Budi Santoso sedang menuju alamat penerima membawa cooler box.',
                        'active' => true,
                    ],
                    [
                        'label' => 'Paket Keluar Hub',
                        'time' => '12.30',
                        'detail' => 'Paket dipindahkan ke cooler box kurir di Hub Jakarta Selatan.',
                    ],
                    [
                        'label' => 'Tiba di Hub Cold Storage',
                        'time' => '10.00',
                        'detail' => 'Paket tiba dan disimpan di freezer hub Jakarta Selatan.',
                    ],
                    [
                        'label' => 'Paket Dijemput',
                        'time' => '08.30',
                        'detail' => 'Paket Nugget Ayam 1 kg diterima dari penjual dalam kondisi beku sempurna.',
                    ],
                ],
            ],
            [
                'resi' => 'AJ-FRZ-0012347',
                'product' => 'Es Krim Vanilla & Choco Box',
                'product_meta' => '2 Paket • Berat 1.400g',
                'recipient' => 'Rina Putri',
                'recipient_area' => 'Kebayoran Baru',
                'address' => 'Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410',
                'status' => 'Menunggu Konfirmasi',
                'status_key' => 'waiting',
                'eta_date' => 'Kamis, 15 Okt 2024',
                'eta_time' => '13.00 - 15.00 WIB',
                'courier' => 'Budi Santoso',
                'courier_box' => 'Box #04',
                'courier_tel' => 'tel:081234567890',
                'temperature' => '-18°C',
                'temperature_state' => 'Optimal',
                'cold_chain' => 'Terjaga',
                'timeline' => [
                    [
                        'label' => 'Menunggu Konfirmasi Jadwal',
                        'time' => '09.20',
                        'detail' => 'Paket siap di hub. Konfirmasi jadwal pengantaran untuk melanjutkan.',
                        'active' => true,
                    ],
                    [
                        'label' => 'Paket Dikemas',
                        'time' => '08.10',
                        'detail' => 'Es krim dikemas dengan ice gel tambahan di Hub Jakarta Selatan.',
                    ],
                ],
                'schedule' => [
                    'status' => 'Menunggu Konfirmasi',
                    'cold_badge' => '-18°C Terjamin',
                    'product_category' => 'Makanan Beku (Frozen Food)',
                    'product_name' => 'Es Krim Vanilla & Choco Box',
                    'product_meta' => '2 Paket • Berat 1.400g',
                    'product_note' => 'Membutuhkan kotak pendingin & ice gel khusus. Wajib diterima langsung agar suhu beku terjaga optimal.',
                    'window' => '13.00 - 15.00 WIB',
                    'window_date' => 'Kamis, 15 Oktober 2024',
                    'courier' => 'Budi Santoso',
                    'courier_role' => 'Kurir Khusus Frozen Anteraja',
                    'priority' => 'Prioritas Dingin',
                    'notice_title' => 'Penting Sebelum Konfirmasi',
                    'notice' => 'Kurir akan membawa cooler box. Mohon pastikan ada penerima di lokasi pada jam tersebut agar kualitas makanan beku tetap aman.',
                ],
            ],
            [
                'resi' => 'AJ-FRZ-0012349',
                'product' => 'Dimsum Ayam 500g',
                'product_meta' => '1 Paket • Berat 500g',
                'recipient' => 'Rina Putri',
                'recipient_area' => 'Kebayoran Baru',
                'address' => 'Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410',
                'status' => 'Kendala Pengiriman',
                'status_key' => 'issue',
                'eta_date' => 'Kamis, 15 Okt 2024',
                'eta_time' => 'Menunggu keputusan admin',
                'courier' => 'Budi Santoso',
                'courier_box' => 'Box #04',
                'courier_tel' => 'tel:081234567890',
                'temperature' => '-18°C',
                'temperature_state' => 'Optimal',
                'cold_chain' => 'Terjaga',
                'timeline' => [
                    [
                        'label' => 'Keputusan Admin',
                        'time' => '12.45',
                        'detail' => 'Paket diputuskan untuk dikembalikan (retur) ke penjual karena kemasan bocor.',
                        'active' => true,
                    ],
                    [
                        'label' => 'Pemeriksaan Hub',
                        'time' => '12.10',
                        'detail' => 'Petugas hub menemukan kebocoran pada kemasan Dimsum Ayam 500g saat pengecekan suhu.',
                    ],
                    [
                        'label' => 'Tiba di Hub',
                        'time' => '10.00',
                        'detail' => 'Paket diterima di Hub Cold Storage Jakarta Selatan.',
                    ],
                ],
                'issue' => [
                    'status_badge' => 'Pengiriman Terkendala',
                    'temp_badge' => 'Suhu: -18°C Terjaga',
                    'problem_label' => 'Laporan Masalah Fisik',
                    'problem' => 'Kemasan bocor saat proses transit di hub pendingin.',
                    'decision_label' => 'Keputusan Admin Operasional',
                    'decision' => 'Paket Dibatalkan & Dikembalikan ke Penjual (Retur Otomatis). Penjual akan mengirimkan produk pengganti baru yang segar.',
                    'quality_label' => 'Pemeriksaan Kualitas Cold Chain',
                    'quality' => 'Tim kontrol kualitas menemukan kerusakan segel kemasan beku. Demi menjaga keamanan pangan dan standar higienis, pengantaran dibatalkan secara otomatis tanpa biaya bagi pembeli.',
                    'history' => [
                        [
                            'label' => 'Keputusan Admin',
                            'time' => '12.45 WIB',
                            'detail' => 'Paket diputuskan untuk dikembalikan (retur) ke penjual karena kemasan bocor.',
                            'active' => true,
                        ],
                        [
                            'label' => 'Pemeriksaan Hub',
                            'time' => '12.10 WIB',
                            'detail' => 'Petugas hub menemukan kebocoran pada kemasan Dimsum Ayam 500g saat pengecekan suhu.',
                        ],
                        [
                            'label' => 'Tiba di Hub',
                            'time' => '10.00 WIB',
                            'detail' => 'Paket diterima di Hub Cold Storage Jakarta Selatan.',
                        ],
                    ],
                ],
                'schedule' => [
                    'status' => 'Menunggu Konfirmasi',
                    'cold_badge' => '-18°C Terjamin',
                    'product_category' => 'Makanan Beku (Frozen Food)',
                    'product_name' => 'Dimsum Ayam 500g (Pengganti)',
                    'product_meta' => '1 Paket • Berat 500g',
                    'product_note' => 'Produk pengganti dikirim ulang dengan kotak pendingin. Wajib diterima langsung agar suhu beku terjaga optimal.',
                    'window' => '16.00 - 18.00 WIB',
                    'window_date' => 'Jumat, 16 Oktober 2024',
                    'courier' => 'Budi Santoso',
                    'courier_role' => 'Kurir Khusus Frozen Anteraja',
                    'priority' => 'Prioritas Dingin',
                    'notice_title' => 'Penting Sebelum Konfirmasi',
                    'notice' => 'Kurir akan membawa cooler box. Mohon pastikan ada penerima di lokasi pada jam tersebut agar kualitas makanan beku tetap aman.',
                ],
            ],
            [
                'resi' => 'AJ-FRZ-0012320',
                'product' => 'Kentang Goreng 2 kg',
                'product_meta' => '1 Paket • Berat 2.000g',
                'recipient' => 'Rina Putri',
                'recipient_area' => 'Kebayoran Baru',
                'address' => 'Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410',
                'status' => 'Diterima',
                'status_key' => 'done',
                'eta_date' => 'Diterima hari ini',
                'eta_time' => '09.52 WIB',
                'courier' => 'Budi Santoso',
                'courier_box' => 'Box #04',
                'courier_tel' => 'tel:081234567890',
                'temperature' => '-18°C',
                'temperature_state' => 'Optimal',
                'cold_chain' => 'Terjaga',
                'timeline' => [
                    [
                        'label' => 'Diterima Penerima',
                        'time' => '09.52',
                        'detail' => 'Paket diterima langsung oleh Rina Putri dalam kondisi beku.',
                        'active' => true,
                    ],
                    [
                        'label' => 'Paket Keluar Hub',
                        'time' => '08.40',
                        'detail' => 'Paket dipindahkan ke cooler box kurir di Hub Jakarta Selatan.',
                    ],
                ],
            ],
            [
                'resi' => 'AJ-FRZ-0012288',
                'product' => 'Frozen Beef Slice 1 kg',
                'product_meta' => '1 Paket • Berat 1.000g',
                'recipient' => 'Rina Putri',
                'recipient_area' => 'Kebayoran Baru',
                'address' => 'Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410',
                'status' => 'Diterima',
                'status_key' => 'done',
                'eta_date' => 'Diterima kemarin',
                'eta_time' => '16.20 WIB',
                'courier' => 'Dani Kurniawan',
                'courier_box' => 'Box #02',
                'courier_tel' => 'tel:081234567891',
                'temperature' => '-19°C',
                'temperature_state' => 'Optimal',
                'cold_chain' => 'Terjaga',
                'timeline' => [
                    [
                        'label' => 'Diterima Penerima',
                        'time' => '16.20',
                        'detail' => 'Paket diterima langsung oleh Rina Putri dalam kondisi beku.',
                        'active' => true,
                    ],
                    [
                        'label' => 'Paket Keluar Hub',
                        'time' => '15.05',
                        'detail' => 'Paket dipindahkan ke cooler box kurir di Hub Jakarta Selatan.',
                    ],
                ],
            ],
        ];
    }

    /** Parcels still moving — the Pesanan tab. */
    public static function activeOrders(): array {
        return array_values(array_filter(
            self::orders(),
            static fn (array $order): bool => $order['status_key'] !== 'done'
        ));
    }

    /** Parcels that arrived — the Riwayat tab. */
    public static function pastOrders(): array {
        return array_values(array_filter(
            self::orders(),
            static fn (array $order): bool => $order['status_key'] === 'done'
        ));
    }

    /**
     * One order by resi, or null when the resi is unknown — which is what
     * every pushed screen renders its "not found" card from.
     */
    public static function find(?string $resi): ?array {
        if ($resi === null || $resi === '') {
            return null;
        }

        foreach (self::orders() as $order) {
            if ($order['resi'] === $resi) {
                return $order;
            }
        }

        return null;
    }

    /** Customer identity and lifetime numbers for the Profil tab. */
    public static function profile(): array {
        return [
            'name' => 'Rina Putri',
            'phone' => '0812-9876-5432',
            'email' => 'rina.putri@email.com',
            'address' => 'Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410',
            'stats' => [
                ['label' => 'Pesanan aktif', 'value' => '3'],
                ['label' => 'Selesai', 'value' => '12'],
                ['label' => 'Tepat waktu', 'value' => '96%'],
            ],
        ];
    }

    /** Customer-service channel behind the help action and the resend request. */
    public static function support(): array {
        return [
            'label' => 'Layanan Pelanggan Anteraja Frozen',
            'phone' => '0812-8899-1234',
            'whatsapp' => '6281288991234',
        ];
    }

    /** WhatsApp deep link with a pre-filled message. */
    public static function whatsappLink(string $text): string {
        return 'https://wa.me/'.self::support()['whatsapp'].'?text='.rawurlencode($text);
    }

    /** The question the header help action asks customer service. */
    public static function helpMessage(): string {
        return 'Halo, saya butuh bantuan dengan pengiriman Anteraja Frozen.';
    }

    /**
     * Pill fill per order state, mirroring CLIENT_STATUS_TONE in the web
     * fixtures. Shared so the Pesanan and Riwayat lists cannot drift apart.
     *
     * Fill and label are separate methods because a text colour set on a
     * container does not reach the label on Android — each platform paints
     * the pill's two halves independently.
     */
    public static function statusTone(string $statusKey): string {
        return match ($statusKey) {
            'transit' => 'bg-theme-primary-fixed',
            'waiting' => 'bg-theme-secondary-container',
            'issue' => 'bg-theme-error-container',
            'done' => 'bg-theme-tertiary-fixed',
            default => 'bg-theme-surface-container',
        };
    }

    /** Label colour that pairs with the fill from statusTone. */
    public static function statusLabelTone(string $statusKey): string {
        return match ($statusKey) {
            'transit' => 'text-theme-primary',
            'waiting' => 'text-theme-on-secondary-container',
            'issue' => 'text-theme-on-error-container',
            'done' => 'text-theme-tertiary',
            default => 'text-theme-secondary',
        };
    }
}
