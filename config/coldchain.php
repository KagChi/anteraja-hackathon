<?php

declare(strict_types=1);

return [
    'target_temperature' => '-18°C ~ -25°C',
    'standard_temperature_label' => '-18°C (Frozen Strict)',
    'delivery_window_default_hours' => 2,
    'cold_box_class' => 'Cold Box L3',
    'hub_name' => 'Hub Jaksel',
    'daily_fleet_capacity' => 1500,
    'checklist_items' => [
        'packaging_damaged' => 'Kemasan Basah/Rusak',
        'ice_cream_melting' => 'Es Krim Melunak',
        'seal_opened' => 'Segel Terbuka',
        'buyer_rejected' => 'Tolak Penerimaan',
    ],
    'standard_temperature_range' => [
        'min' => -25,
        'max' => -18,
    ],
    'hub' => [
        'display_name' => 'Hub Jakarta Selatan',
    ],
    'customer_service' => [
        'phone' => '+628111928374',
        'whatsapp_url' => 'https://wa.me/628111928374',
    ],
    'client' => [
        'link_ttl_days' => 30,
        'resend_auto_approve' => true,
        'reschedule_max_days' => 14,
        'min_lead_hours' => 2,
        'unanswered_after_minutes' => 120,
        'slots' => [
            '09:00-11:00',
            '11:00-13:00',
            '13:00-15:00',
            '15:00-17:00',
            '17:00-19:00',
        ],
    ],
];
