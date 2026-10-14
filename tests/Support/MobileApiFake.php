<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Canned backend answers for the native screen tests.
 *
 * The screens are wired to the API now, so a component test stands in for the
 * server rather than for the fixtures. The payloads carry the same values the
 * blades render, which is what keeps the existing visible-string assertions
 * meaningful.
 */
final class MobileApiFake {
    public const TRANSPORTING = 'AJ-FRZ-0012345';

    public const WAITING = 'AJ-FRZ-0012347';

    public const ISSUE = 'AJ-FRZ-0012349';

    public const COMPLETED = 'AJ-FRZ-0012320';

    public const COURIER_TASK = 'AJ-FRZ-0012345';

    public const COURIER_HISTORY = 'AJ-FRZ-0012344';

    /** @param  array<string, mixed>  $overrides */
    public static function stub(array $overrides = []): void {
        // Http::fake() appends stubs — it never clears them — so a second call
        // in the same test would lose to the first. Swap in a clean factory so
        // every stub() call, and every failure override, starts from scratch.
        Http::swap(new Factory());
        Http::fake($overrides + self::defaults());
    }

    /** @return array<string, mixed> */
    private static function defaults(): array {
        return [
            // ---- Customer -------------------------------------------------
            '*api/client/orders/'.self::TRANSPORTING.'/tracking' => Http::response(self::tracking('IN_DELIVERY', 'Sedang Diantar')),
            '*api/client/orders/'.self::WAITING.'/tracking' => Http::response(self::tracking('AWAITING_CONFIRMATION', 'Menunggu Konfirmasi')),
            '*api/client/orders/'.self::ISSUE.'/tracking' => Http::response(self::tracking('ISSUE_REPORTED', 'Pengiriman Terkendala')),
            '*api/client/orders/AJ-FRZ-0000000/tracking' => Http::response(['message' => 'Paket tidak ditemukan.'], 404),
            '*api/client/orders/AJ-FRZ-0000000/schedule' => Http::response(['message' => 'Paket tidak ditemukan.'], 404),
            '*api/client/orders/AJ-FRZ-0000000/issue' => Http::response(['message' => 'Paket tidak ditemukan.'], 404),
            '*api/client/orders/'.self::WAITING.'/schedule/confirm' => Http::response(['success' => true, 'message' => 'Jadwal pengantaran berhasil dikonfirmasi.']),
            '*api/client/orders/'.self::WAITING.'/schedule/reschedule' => Http::response(['success' => true, 'message' => 'Permintaan perubahan jadwal berhasil dikirim.']),
            '*api/client/orders/'.self::WAITING.'/schedule' => Http::response(self::schedule()),
            '*api/client/orders/'.self::ISSUE.'/issue/resend' => Http::response([
                'success' => true,
                'message' => 'Permintaan pengiriman ulang berhasil diajukan ke tim operasional.',
                'data' => ['replacement_tracking_number' => self::ISSUE],
            ], 201),
            '*api/client/orders/'.self::ISSUE.'/issue' => Http::response(self::issue()),
            '*api/client/orders*' => function (Request $request) {
                return str_contains($request->url(), 'tab=completed')
                    ? Http::response(self::completedOrders())
                    : Http::response(self::activeOrders());
            },
            '*api/client/me' => Http::response(self::profile()),
            '*api/client/auth/login' => Http::response([
                'data' => ['token' => 'client-test-token', 'token_type' => 'Bearer', 'buyer' => ['name' => 'Rina Putri']],
            ]),
            '*api/client/auth/logout' => Http::response(['message' => 'Berhasil logout.']),

            // ---- Courier --------------------------------------------------
            '*api/courier/auth/login' => Http::response([
                'data' => ['token' => 'courier-test-token', 'token_type' => 'Bearer', 'courier' => ['name' => 'Budi Santoso']],
            ]),
            '*api/courier/auth/logout' => Http::response(['message' => 'Berhasil logout.']),
            '*api/courier/tasks/'.self::COURIER_TASK.'/accept' => Http::response(['message' => 'Tugas berhasil diterima.']),
            '*api/courier/tasks/'.self::COURIER_TASK.'/reject' => Http::response(['message' => 'Tugas '.self::COURIER_TASK.' telah ditolak.']),
            '*api/courier/tasks' => Http::response(self::courierTasks()),
            '*api/courier/deliveries/'.self::COURIER_TASK.'/proof' => Http::response(self::proofForm()),
            '*api/courier/deliveries/'.self::COURIER_TASK.'/issue' => Http::response(self::courierIssueForm()),
            '*api/courier/deliveries/'.self::COURIER_TASK.'/arrive' => Http::response(['message' => 'Status berhasil diperbarui: Tiba di lokasi.']),
            '*api/courier/deliveries/'.self::COURIER_TASK => Http::response(self::courierDelivery()),
            '*api/courier/history*' => Http::response(self::courierHistory()),
            '*api/courier/profile' => Http::response(self::courierProfile()),

            // Anything that slips through is a mistake worth failing on.
            '*' => Http::response(['message' => 'Endpoint tidak difake.'], 500),
        ];
    }

    /** @return array<string, mixed> */
    private static function activeOrders(): array {
        return [
            'data' => [
                [
                    'resi' => self::TRANSPORTING,
                    'status' => ['code' => 'IN_DELIVERY', 'label' => 'Sedang Diantar', 'tone' => 'dark'],
                    'product' => 'Nugget Ayam 1 kg',
                    'product_meta' => '1 Paket • Berat 1.000g',
                    'recipient' => 'Rina Putri',
                    'recipient_area' => 'Kebayoran Baru',
                    'eta_date' => 'Kamis, 15 Okt 2024',
                    'eta_time' => '13.45 WIB',
                    'action_required' => 'track',
                    'next_screen' => 'tracking',
                    'completed_label' => null,
                    'completed_at' => null,
                ],
                [
                    'resi' => self::WAITING,
                    'status' => ['code' => 'AWAITING_CONFIRMATION', 'label' => 'Menunggu Konfirmasi', 'tone' => 'muted'],
                    'product' => 'Es Krim Vanilla & Choco Box',
                    'product_meta' => '2 Paket • Berat 1.400g',
                    'recipient' => 'Rina Putri',
                    'recipient_area' => 'Kebayoran Baru',
                    'eta_date' => 'Kamis, 15 Okt 2024',
                    'eta_time' => '13.00 - 15.00 WIB',
                    'action_required' => 'confirm_schedule',
                    'next_screen' => 'schedule',
                    'completed_label' => null,
                    'completed_at' => null,
                ],
                [
                    'resi' => self::ISSUE,
                    'status' => ['code' => 'ISSUE_REPORTED', 'label' => 'Pengiriman Terkendala', 'tone' => 'danger'],
                    'product' => 'Dimsum Ayam 500g',
                    'product_meta' => '1 Paket • Berat 500g',
                    'recipient' => 'Rina Putri',
                    'recipient_area' => 'Kebayoran Baru',
                    'eta_date' => 'Kamis, 15 Okt 2024',
                    'eta_time' => 'Menunggu keputusan admin',
                    'action_required' => 'view_issue',
                    'next_screen' => 'issue',
                    'completed_label' => null,
                    'completed_at' => null,
                ],
            ],
            'meta' => [
                'active_count' => 3,
                'completed_count' => 2,
                'standard_temp' => 'Beku -18°C',
                'sla_rate' => '96%',
                'tab_counts' => ['active' => 3, 'completed' => 2, 'issue' => 1, 'total' => 5],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 3, 'has_more' => false],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function completedOrders(): array {
        return [
            'data' => [
                [
                    'resi' => self::COMPLETED,
                    'status' => ['code' => 'RECEIVED', 'label' => 'Paket Diterima', 'tone' => 'success'],
                    'product' => 'Kentang Goreng 2 kg',
                    'product_meta' => '1 Paket • Berat 2.000g',
                    'recipient' => 'Rina Putri',
                    'recipient_area' => 'Kebayoran Baru',
                    'eta_date' => null,
                    'eta_time' => null,
                    'action_required' => null,
                    'next_screen' => 'tracking',
                    'completed_label' => 'Diterima hari ini • 09.52 WIB',
                    'completed_at' => '2024-10-15T02:52:00+00:00',
                ],
                [
                    'resi' => 'AJ-FRZ-0012288',
                    'status' => ['code' => 'RECEIVED', 'label' => 'Paket Diterima', 'tone' => 'success'],
                    'product' => 'Frozen Beef Slice 1 kg',
                    'product_meta' => '1 Paket • Berat 1.000g',
                    'recipient' => 'Rina Putri',
                    'recipient_area' => 'Kebayoran Baru',
                    'eta_date' => null,
                    'eta_time' => null,
                    'action_required' => null,
                    'next_screen' => 'tracking',
                    'completed_label' => 'Diterima kemarin • 16.20 WIB',
                    'completed_at' => '2024-10-14T09:20:00+00:00',
                ],
            ],
            'meta' => [
                'active_count' => 3,
                'completed_count' => 2,
                'standard_temp' => 'Beku -18°C',
                'sla_rate' => '96%',
                'tab_counts' => ['active' => 3, 'completed' => 2, 'issue' => 1, 'total' => 5],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 2, 'has_more' => false],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function tracking(string $status, string $label): array {
        return [
            'success' => true,
            'data' => [
                'tracking_number' => self::TRANSPORTING,
                'status' => $status,
                'status_label' => $label,
                'status_tone' => 'dark',
                'estimated_delivery' => 'Kamis, 15 Oktober 2024, 13.00 - 15.00 WIB',
                'buyer_name' => 'Rina Putri',
                'buyer_area' => 'Kebayoran Baru',
                'courier_name' => 'Budi Santoso',
                'cooler_box_code' => 'Box #04',
                'cold_chain' => [
                    'temperature_label' => '-18°C',
                    'temperature_state' => 'Optimal',
                    'chain_state' => 'Terjaga',
                    'is_measured' => true,
                    'recorded_at' => '13.10 WIB',
                ],
                'timeline' => [
                    ['title' => 'Sedang Diantar', 'description' => 'Kurir Budi Santoso sedang menuju alamat penerima.', 'time' => '13.15', 'is_accent' => true],
                    ['title' => 'Paket Keluar Hub', 'description' => 'Paket dipindahkan ke cooler box kurir.', 'time' => '12.30', 'is_accent' => false],
                    ['title' => 'Paket Dijemput', 'description' => 'Paket diterima dari penjual dalam kondisi beku sempurna.', 'time' => '08.30', 'is_accent' => false],
                ],
                'can_contact_courier' => true,
                'courier_phone' => '081234567890',
                'issue_banner' => null,
                'reschedule_banner' => null,
                'product_name' => 'Nugget Ayam 1 kg',
                'product_meta' => '1 Paket • Berat 1.000g',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function schedule(): array {
        return [
            'success' => true,
            'data' => [
                'tracking_number' => self::WAITING,
                'status' => 'AWAITING_CONFIRMATION',
                'status_label' => 'Menunggu Konfirmasi',
                'buyer_name' => 'Rina Putri',
                'buyer_address' => 'Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410',
                'cargo_items' => [
                    ['category' => 'Makanan Beku (Frozen Food)', 'name' => 'Es Krim Vanilla & Choco Box', 'quantity_label' => '2 Paket • Berat 1.400g'],
                ],
                'total_weight_kg' => 1.4,
                'cold_guaranteed_badge' => '-18°C Terjamin',
                'handling_note' => 'Membutuhkan kotak pendingin & ice gel khusus. Wajib diterima langsung.',
                'scheduled_delivery' => 'Kamis, 15 Oktober 2024, 13.00 - 15.00 WIB',
                'has_cold_priority' => true,
                'courier_name' => 'Budi Santoso',
                'courier_badge' => 'Kurir Khusus Frozen Anteraja',
                'can_action' => true,
                'available_slots' => ['09:00-11:00', '13:00-15:00', '15:00-17:00'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function issue(): array {
        return [
            'success' => true,
            'data' => [
                'tracking_number' => self::ISSUE,
                'status_label' => 'Pengiriman Terkendala',
                'buyer_name' => 'Rina Putri',
                'courier_name' => 'Budi Santoso',
                'product_names' => ['Dimsum Ayam 500g'],
                'issue_category_label' => 'Kemasan bocor',
                'issue_summary' => 'Kemasan bocor saat proses transit di hub pendingin.',
                'decision_message' => 'Paket dibatalkan & dikembalikan ke penjual (retur otomatis).',
                'decision_code' => 'RETURN',
                'quality_inspection_note' => 'Tim kontrol kualitas menemukan kerusakan segel kemasan beku.',
                'cold_chain_chip' => 'Suhu: -18°C Terjaga',
                'timeline' => [
                    ['title' => 'Keputusan Admin', 'description' => 'Paket diputuskan untuk dikembalikan ke penjual.', 'time' => '12.45', 'is_accent' => true],
                    ['title' => 'Pemeriksaan Hub', 'description' => 'Petugas hub menemukan kebocoran pada kemasan.', 'time' => '12.10', 'is_accent' => false],
                ],
                'can_request_replacement' => true,
                'replacement_tracking_number' => null,
                'replacement_url' => null,
                'customer_service' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function profile(): array {
        return [
            'data' => [
                'name' => 'Rina Putri',
                'phone' => '0812-9876-5432',
                'address' => 'Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410',
                'area' => 'Kebayoran Baru',
                'stats' => ['active_count' => 3, 'completed_count' => 12, 'on_time_rate' => '96%'],
                'support' => [
                    'whatsapp_url' => 'https://wa.me/6281288991234?text=Halo',
                    'email' => 'cs@anteraja.id',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function courierTasks(): array {
        return [
            'newTasks' => [
                [
                    'id' => 'KRR-8821',
                    'package_id' => 1,
                    'age' => 'Masuk 2 menit lalu',
                    'tracking' => self::COURIER_TASK,
                    'service' => 'Instant Frozen',
                    'serviceKey' => 'INSTANT_FROZEN',
                    'window' => 'Hari ini • 13:00 - 15:00 WIB',
                    'windowStatus' => 'Tepat Waktu',
                    'windowStatusTone' => 'success',
                    'recipient' => 'Rina Putri',
                    'phone' => '0812-9876-5432',
                    'placeType' => 'Rumah / Residensial',
                    'address' => 'Jl. Melati Indah No. 42, Tebet Barat, Jakarta Selatan',
                    'instruction' => 'Produk beku, serahkan langsung ke penerima.',
                    'metrics' => [
                        ['label' => 'Jumlah', 'value' => '1 Koli'],
                        ['label' => 'Kategori', 'value' => 'Frozen Food'],
                        ['label' => 'Berat', 'value' => '1.8 kg'],
                    ],
                    'buyerCoordinates' => ['latitude' => -6.22, 'longitude' => 106.84],
                    'status' => 'COURIER_ASSIGNED',
                    'statusLabel' => 'Kurir Ditugaskan',
                    'stage' => 'PENDING',
                    'stageLabel' => 'Belum Berangkat',
                    'hasIssue' => false,
                    'isNew' => true,
                ],
            ],
            'activeTasks' => [],
            'courier' => ['name' => 'Budi Santoso', 'status' => 'Aktif'],
        ];
    }

    /** @return array<string, mixed> */
    private static function courierDelivery(): array {
        return [
            'delivery' => [
                'tracking' => self::COURIER_TASK,
                'service' => 'Paket Beku',
                'serviceType' => 'Instant Frozen',
                'recipient' => 'Rina Putri',
                'phone' => '0812-9876-5432',
                'phoneHref' => 'tel:081298765432',
                'address' => 'Jl. Melati Indah No. 42, Tebet Barat, Jakarta Selatan',
                'instruction' => 'Produk beku, serahkan langsung ke penerima.',
                'mapLabel' => 'Jl. Melati Indah No. 42, Tebet Barat, Jakarta Selatan',
                'mapHref' => 'https://www.google.com/maps/search/?api=1&query=Jl.+Melati+Indah+No.+42',
                'status' => 'IN_DELIVERY',
                'statusLabel' => 'Sedang Diantar',
                'stage' => 'EN_ROUTE',
                'stageLabel' => 'Dalam Perjalanan',
                'canDepart' => false,
                'canArrive' => true,
                'canProof' => true,
                'canReportIssue' => true,
                'hasOpenIssue' => false,
                'checklistItems' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function proofForm(): array {
        return [
            'proofForm' => [
                'tracking' => self::COURIER_TASK,
                'service' => 'Reguler',
                'recipient' => 'Rina Putri',
                'relations' => ['Penerima Sendiri', 'Keluarga Serumah', 'Rekan Kerja', 'Satpam / Sekuriti'],
                'relationOptions' => [
                    ['value' => 'SELF', 'label' => 'Penerima Sendiri'],
                    ['value' => 'FAMILY', 'label' => 'Keluarga Serumah'],
                    ['value' => 'COLLEAGUE', 'label' => 'Rekan Kerja'],
                    ['value' => 'SECURITY', 'label' => 'Satpam / Sekuriti'],
                ],
                'notesPlaceholder' => 'Contoh: Diterima langsung, diletakkan di teras',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function courierIssueForm(): array {
        return [
            'issueForm' => [
                'tracking' => self::COURIER_TASK,
                'recipient' => 'Rina Putri',
                'reporter' => 'Budi Santoso',
                'noteLimit' => 200,
                'options' => [
                    ['value' => 'kemasan_bocor', 'label' => 'Paket Rusak / Mencair'],
                    ['value' => 'pembeli_tidak_ada', 'label' => 'Penerima Tidak Ada di Tempat'],
                    ['value' => 'alamat_tidak_ditemukan', 'label' => 'Alamat Tidak Ditemukan'],
                ],
                'checklistItems' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function courierHistory(): array {
        return [
            'history' => [
                ['resi' => self::COURIER_HISTORY, 'recipient' => 'Andi Saputra', 'completedAt' => 'Hari ini • 13:42 WIB', 'status' => 'Terkirim'],
                ['resi' => 'AJ-FRZ-0012343', 'recipient' => 'Nadia Suryani', 'completedAt' => 'Hari ini • 12:18 WIB', 'status' => 'Dikembalikan'],
            ],
            'summary' => ['completedToday' => '2 Pengiriman', 'completedCount' => 2, 'onTimeRate' => '97% Tepat Waktu'],
            'pagination' => ['currentPage' => 1, 'lastPage' => 1, 'perPage' => 15, 'total' => 2],
        ];
    }

    /** @return array<string, mixed> */
    private static function courierProfile(): array {
        return [
            'profile' => [
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
            ],
        ];
    }
}
