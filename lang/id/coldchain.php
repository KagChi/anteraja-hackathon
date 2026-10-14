<?php

declare(strict_types=1);

return [
    'errors' => [
        'invalid_status_transition' => 'Transisi status tidak valid dari :from ke :to.',
        'issue_already_decided' => 'Kendala sudah diputuskan oleh :decided_by.',
        'package_not_assignable' => 'Paket tidak dapat ditugaskan ke kurir pada status saat ini.',
        'invalid_decision' => 'Keputusan :decision tidak diizinkan untuk kategori kendala ini.',
    ],
    'notifications' => [
        'schedule_request' => 'Permintaan jadwal pengiriman untuk resi :tracking_number.',
        'schedule_rescheduled' => 'Konfirmasi jadwal ulang pengiriman untuk resi :tracking_number.',
        'issue_options' => 'Menu pilihan penyelesaian kendala untuk resi :tracking_number.',
        'buyer_issue_choice' => 'Pilihan penyelesaian pembeli untuk resi :tracking_number: :choice_label.',
        'task_assigned' => 'Penugasan pengiriman resi :tracking_number.',
        'issue_decision' => 'Keputusan kendala resi :tracking_number: :decision.',
        'issue_decision_update' => 'Pembaruan kendala resi :tracking_number: :decision.',
    ],
    'decisions' => [
        'RETURN' => 'Kembalikan Paket',
        'RESEND' => 'Kirim Ulang',
        'PROCEED' => 'Lanjutkan',
        'RESCHEDULE' => 'Jadwal Ulang',
        'RECHECK' => 'Periksa Ulang',
    ],
];
