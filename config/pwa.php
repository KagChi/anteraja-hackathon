<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PWA App Registry
|--------------------------------------------------------------------------
|
| The single source of truth for the two installable apps this origin
| serves. The shell route and the manifest route read it, and the marketing
| landing page reads the card copy, so a renamed app cannot drift from the
| page that links to it.
|
| Each app is its own PWA: id, scope and start_url differ, which is what
| lets one phone install Anteraja Frozen and Anteraja Kurir side by side
| from the same host. Both apps are light only, so they share the theme
| colour the rest of the site paints.
|
*/

return [
    'apps' => [
        'client' => [
            'name' => 'Anteraja Frozen',
            'short_name' => 'Frozen',
            'title' => 'Anteraja Frozen',
            'description' => 'Kirim makanan beku: belanja, pilih jadwal, dan pantau paket sampai diterima.',
            'start_url' => '/client',
            // The scope is a prefix, and it must contain start_url, so the bare
            // path is the scope rather than "/client/".
            'scope' => '/client',
            'manifest_url' => '/client.webmanifest',
            'theme_color' => '#f9f9ff',
            'background_color' => '#f9f9ff',
            'icons' => [
                ['src' => '/icons/client-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/client-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/client-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'card' => [
                'audience' => 'Untuk pelanggan',
                'description' => 'Belanja produk beku, pilih jadwal antar, dan pantau status kiriman Anda.',
                'icon' => 'ac_unit',
                'points' => ['Katalog produk frozen', 'Checkout dan jadwal mandiri', 'Lacak resi dan konfirmasi'],
            ],
        ],

        'courier' => [
            'name' => 'Anteraja Kurir',
            'short_name' => 'Kurir',
            'title' => 'Anteraja Kurir',
            'description' => 'Terima tugas, jalankan pengantaran dengan validasi lokasi, dan unggah bukti serah terima.',
            'start_url' => '/courier',
            'scope' => '/courier',
            'manifest_url' => '/courier.webmanifest',
            'theme_color' => '#f9f9ff',
            'background_color' => '#f9f9ff',
            'icons' => [
                ['src' => '/icons/courier-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/courier-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/courier-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'card' => [
                'audience' => 'Untuk kurir',
                'description' => 'Terima tugas, jalankan pengantaran, dan unggah bukti penyerahan.',
                'icon' => 'local_shipping',
                'points' => ['Daftar tugas harian', 'Validasi lokasi serah terima', 'Riwayat pengantaran'],
            ],
        ],
    ],
];
