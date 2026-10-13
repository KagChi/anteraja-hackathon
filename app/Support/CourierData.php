<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fixture data for the native courier screens.
 *
 * Ported one-for-one from resources/js/data/courier.ts so the APK shows the
 * same prototype the web console does. When the courier flow is wired to the
 * API this class is the single seam to replace — every screen reads through
 * it, so nothing else has to change.
 */
final class CourierData {
    /** Signed-in courier identity, shown in the header and profile tab. */
    public static function courier(): array {
        return [
            'name' => 'Budi Santoso',
            'status' => 'Aktif',
        ];
    }

    /** The one task card the prototype renders, with every field it shows. */
    public static function task(): array {
        return [
            'id' => 'KRR-8821',
            'age' => 'Masuk 2 mnt lalu',
            'tracking' => 'AJ-FRZ-0012345',
            'service' => 'Instant Frozen',
            'window' => '13:00 - 15:00 WIB (Hari ini)',
            'window_status' => 'Tepat Waktu',
            'recipient' => 'Rina Putri',
            'place_type' => 'Rumah / Residensial',
            'address' => 'Jl. Melati Indah No. 42, RT 03 / RW 07, Tebet Barat, Jakarta Selatan, DKI Jakarta 12810',
            'instruction' => 'Produk beku, serahkan langsung ke penerima. Jangan ditinggal di pagar atau pos satpam.',
            'metrics' => [
                ['label' => 'Jumlah', 'value' => '1 Koli'],
                ['label' => 'Kategori', 'value' => 'Frozen Food'],
                ['label' => 'Berat', 'value' => '1.8 kg'],
            ],
            'distance' => '2.4 km dari lokasi Anda',
            'eta' => '~12 Menit',
        ];
    }

    /** The shipment currently on the road, for the delivery screen. */
    public static function activeDelivery(): array {
        return [
            'tracking' => 'AJ-FRZ-0012345',
            'service' => 'Paket Beku',
            'recipient' => 'Rina Putri',
            'phone' => '0812-9876-5432',
            'phone_href' => 'tel:081298765432',
            'address' => 'Jl. Melati Indah No. 42, RT 03 / RW 07, Tebet Barat, Jakarta Selatan',
            'instruction' => 'Produk beku, serahkan langsung ke penerima',
            'map_label' => 'Jl. Melati Indah No. 42, Tebet Barat, Jakarta Selatan',
            'map_href' => 'https://www.google.com/maps/search/?api=1&query='
                . rawurlencode('Jl. Melati Indah No. 42, RT 03 / RW 07, Tebet Barat, Jakarta Selatan'),
        ];
    }

    /** Reasons offered in the delivery screen's issue sheet. */
    public static function issueSheetOptions(): array {
        return [
            'Penerima Tidak Ada di Tempat',
            'Alamat Tidak Ditemukan',
            'Paket Rusak / Mencair',
        ];
    }

    /** Prefilled proof-of-delivery form. */
    public static function proofForm(): array {
        return [
            'tracking' => 'AJ-FRZ-0012345',
            'service' => 'Reguler',
            'recipient' => 'Rina Putri',
            'photo_name' => 'bukti_serah_terima.jpg',
            'photo_meta' => '1 foto terlampir • 1.4 MB',
            'notes_placeholder' => 'Contoh: Diterima langsung, diletakkan di teras',
            'relations' => ['Penerima Sendiri', 'Keluarga Serumah', 'Rekan Kerja', 'Satpam / Sekuriti'],
        ];
    }

    /** Prefilled field-issue report. */
    public static function issueForm(): array {
        return [
            'tracking' => 'AJ-FRZ-0012345',
            'recipient' => 'Rina Putri',
            'reporter' => 'Budi Santoso',
            'photo_name' => 'foto_kemasan_bocor.jpg',
            'photo_meta' => '1.8 MB • Baru saja',
            'note_limit' => 200,
            'note' => 'Kemasan es beku mencair dan kemasan luar basah/bocor saat tiba di depan gerbang penerima.',
            'options' => [
                ['value' => 'kemasan_bocor', 'label' => 'Kemasan bocor'],
                ['value' => 'pembeli_tidak_ada', 'label' => 'Pembeli tidak ada'],
                ['value' => 'alamat_tidak_ditemukan', 'label' => 'Alamat tidak ditemukan'],
                ['value' => 'pembayaran_cod_ditolak', 'label' => 'Penerima menolak bayar COD'],
                ['value' => 'akses_ditutup', 'label' => 'Akses jalan/lokasi ditutup'],
            ],
        ];
    }

    /** Completed deliveries listed on the history tab. */
    public static function history(): array {
        return [
            ['resi' => 'AJ-FRZ-0012344', 'recipient' => 'Andi Saputra', 'completed_at' => 'Hari ini • 13:42 WIB', 'status' => 'Terkirim'],
            ['resi' => 'AJ-FRZ-0012343', 'recipient' => 'Nadia Suryani', 'completed_at' => 'Hari ini • 12:18 WIB', 'status' => 'Terkirim'],
            ['resi' => 'AJ-FRZ-0012341', 'recipient' => 'Bagus Prasetyo', 'completed_at' => 'Hari ini • 11:05 WIB', 'status' => 'Terkirim'],
            ['resi' => 'AJ-FRZ-0012339', 'recipient' => 'Tania Wijaya', 'completed_at' => 'Hari ini • 10:47 WIB', 'status' => 'Terkirim'],
            ['resi' => 'AJ-FRZ-0012336', 'recipient' => 'Rendra Kusuma', 'completed_at' => 'Hari ini • 09:52 WIB', 'status' => 'Dikembalikan'],
        ];
    }

    /** Courier profile tab: identity, today's numbers, assignment details. */
    public static function profile(): array {
        return [
            'name' => 'Budi Santoso',
            'status' => 'Aktif',
            'id' => 'KRR-8821',
            'phone' => '0812-3456-7890',
            'hub' => 'Hub Jakarta Selatan',
            'vehicle' => 'Motor Box #04',
            'stats' => [
                ['label' => 'Selesai hari ini', 'value' => '8'],
                ['label' => 'Tepat waktu', 'value' => '97%'],
                ['label' => 'Rating', 'value' => '4.8'],
            ],
        ];
    }
}
