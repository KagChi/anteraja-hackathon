<?php

declare(strict_types=1);

return [
    'status' => [
        'CREATED' => 'Paket Diproses',
        'AWAITING_CONFIRMATION' => 'Menunggu Konfirmasi',
        'RESCHEDULE_REQUESTED' => 'Menunggu Penjadwalan Ulang',
        'CONFIRMED' => 'Jadwal Dikonfirmasi',
        'COURIER_ASSIGNED' => 'Kurir Ditugaskan',
        'CHECKED' => 'Pemeriksaan Kemasan',
        'ISSUE_REPORTED' => 'Pengiriman Terkendala',
        'IN_DELIVERY' => 'Sedang Diantar',
        'RECEIVED' => 'Paket Diterima',
        'RETURNED' => 'Dikembalikan',
        'CLOSED' => 'Selesai',
    ],

    'issue' => [
        'summary' => [
            'kemasan_bocor' => 'Kemasan bocor saat proses transit.',
            'suhu_naik' => 'Suhu ruang simpan terindikasi naik melampaui batas standar beku.',
            'es_mencair' => 'Ditemukan tanda-tanda es mencair pada isi kemasan.',
            'segel_rusak' => 'Segel kemasan pendingin ditemukan tidak utuh.',
            'penerima_tidak_di_tempat' => 'Penerima belum dapat ditemui di lokasi alamat pengantaran.',
            'akses_lokasi_sulit' => 'Kendala akses jalan atau lokasi menuju alamat penerima.',
            'default' => 'Terjadi kendala operasional saat proses pengantaran paket beku.',
        ],
        'decision' => [
            'RETURN' => 'Paket dibatalkan & dikembalikan ke penjual (retur otomatis). Penjual akan mengirimkan produk pengganti baru yang segar.',
            'RESEND' => 'Kami menyiapkan pengiriman ulang untuk Anda.',
            'PROCEED' => 'Pengiriman dilanjutkan.',
            'RESCHEDULE' => 'Pengiriman dijadwalkan ulang, mohon pilih jadwal baru.',
            'RECHECK' => 'Paket sedang diperiksa ulang.',
            'pending' => 'Laporan sedang ditinjau tim operasional.',
        ],
    ],

    'timeline' => [
        'hub_arrival' => [
            'title' => 'Tiba di Hub Cold Storage',
            'description' => 'Paket tiba dan disimpan di freezer :hub.',
        ],
        'awaiting_confirmation' => [
            'title' => 'Menunggu Konfirmasi Jadwal',
            'description' => 'Mohon konfirmasi jadwal pengantaran.',
        ],
        'reschedule_requested' => [
            'title' => 'Permintaan Jadwal Ulang',
            'description' => 'Permintaan Anda diterima, admin akan mengusulkan jadwal baru.',
        ],
        'confirmed' => [
            'title' => 'Jadwal Dikonfirmasi',
            'description' => 'Jadwal pengantaran telah disepakati.',
        ],
        'courier_assigned' => [
            'title' => 'Kurir Ditugaskan',
            'description' => 'Kurir khusus frozen telah ditugaskan.',
        ],
        'checked_dispatch' => [
            'title' => 'Paket Keluar Hub',
            'description' => 'Paket dipindahkan ke cooler box kurir di :hub.',
        ],
        'checked_recheck' => [
            'title' => 'Pemeriksaan Ulang',
            'description' => 'Paket sedang diperiksa ulang.',
        ],
        'in_delivery' => [
            'title' => 'Sedang Diantar',
            'description' => 'Kurir :courier sedang menuju alamat penerima membawa cooler box.',
        ],
        'in_delivery_resumed' => [
            'title' => 'Pengiriman Dilanjutkan',
            'description' => 'Pengiriman dilanjutkan oleh :courier.',
        ],
        'arrived' => [
            'title' => 'Kurir Tiba di Lokasi',
            'description' => 'Kurir telah tiba di alamat tujuan.',
        ],
        'issue_reported' => [
            'title' => 'Kendala Ditemukan',
            'description' => ':summary',
        ],
        'issue_decided' => [
            'title' => 'Keputusan Admin',
            'description' => ':decision',
        ],
        'returned' => [
            'title' => 'Paket Dikembalikan',
            'description' => 'Paket dikembalikan ke penjual.',
        ],
        'received' => [
            'title' => 'Paket Diterima',
            'description' => 'Diterima oleh :received_by.',
        ],
    ],

    'cargo' => [
        'category_frozen' => 'Makanan Beku (Frozen Food)',
        'default_note' => 'Wajib disimpan dalam suhu freezer (-18°C) segera setelah diterima.',
        'guaranteed_cold' => '-18°C Terjamin',
        'cold_priority' => 'Prioritas Dingin',
        'courier_badge' => 'Kurir Khusus Frozen Anteraja',
    ],

    'expired' => [
        'title' => 'Tautan Tidak Berlaku',
        'body' => 'Tautan pelacakan ini sudah kedaluwarsa atau tidak aktif. Silakan hubungi layanan pelanggan untuk bantuan lebih lanjut.',
    ],

    'errors' => [
        'link_invalid' => 'Tautan tidak valid atau paket tidak ditemukan.',
        'link_expired' => 'Tautan pelacakan telah kedaluwarsa.',
        'schedule_not_actionable' => 'Jadwal pengantaran tidak dapat diubah atau sudah dikonfirmasi.',
        'replacement_not_allowed' => 'Pengajuan pengiriman ulang tidak memenuhi syarat.',
        'too_many_requests' => 'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.',
    ],
];
