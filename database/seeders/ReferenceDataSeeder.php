<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ReferenceDataSeeder extends Seeder {
    public function run(): void {
        // 1. Staff Roles
        DB::table('staff_roles')->upsert([
            ['code' => 'ADMIN',   'label' => 'Admin'],
            ['code' => 'COURIER', 'label' => 'Courier'],
        ], ['code'], ['label']);

        // 2. Package Statuses (Indonesian labels as required by task-admin-page.md)
        DB::table('package_statuses')->upsert([
            ['code' => 'CREATED',               'label' => 'Dibuat',              'is_waiting' => false, 'is_terminal' => false, 'sort_order' => 10],
            ['code' => 'AWAITING_CONFIRMATION', 'label' => 'Menunggu Konfirmasi', 'is_waiting' => true,  'is_terminal' => false, 'sort_order' => 20],
            ['code' => 'CONFIRMED',             'label' => 'Dijadwalkan',          'is_waiting' => false, 'is_terminal' => false, 'sort_order' => 40],
            ['code' => 'COURIER_ASSIGNED',      'label' => 'Kurir Ditugaskan',     'is_waiting' => false, 'is_terminal' => false, 'sort_order' => 50],
            ['code' => 'CHECKED',               'label' => 'Sudah Diperiksa',      'is_waiting' => false, 'is_terminal' => false, 'sort_order' => 60],
            ['code' => 'ISSUE_REPORTED',        'label' => 'Kendala',              'is_waiting' => true,  'is_terminal' => false, 'sort_order' => 70],
            ['code' => 'IN_DELIVERY',           'label' => 'Sedang Diantar',       'is_waiting' => false, 'is_terminal' => false, 'sort_order' => 80],
            ['code' => 'RECEIVED',              'label' => 'Diterima',             'is_waiting' => false, 'is_terminal' => false, 'sort_order' => 90],
            ['code' => 'RETURNED',              'label' => 'Dikembalikan',         'is_waiting' => false, 'is_terminal' => false, 'sort_order' => 95],
            ['code' => 'CLOSED',                'label' => 'Selesai',              'is_waiting' => false, 'is_terminal' => true,  'sort_order' => 100],
        ], ['code'], ['label', 'is_waiting', 'is_terminal', 'sort_order']);

        // 3. Package Status Transitions (FSM Guard Matrix)
        $transitions = [
            ['from_status' => 'CREATED',               'to_status' => 'COURIER_ASSIGNED'],
            ['from_status' => 'COURIER_ASSIGNED',      'to_status' => 'AWAITING_CONFIRMATION'],
            ['from_status' => 'CREATED',               'to_status' => 'AWAITING_CONFIRMATION'],
            ['from_status' => 'AWAITING_CONFIRMATION', 'to_status' => 'CONFIRMED'],
            ['from_status' => 'CONFIRMED',             'to_status' => 'COURIER_ASSIGNED'],
            ['from_status' => 'COURIER_ASSIGNED',      'to_status' => 'CHECKED'],
            ['from_status' => 'COURIER_ASSIGNED',      'to_status' => 'CONFIRMED'],
            ['from_status' => 'CONFIRMED',             'to_status' => 'CHECKED'],
            ['from_status' => 'CHECKED',               'to_status' => 'IN_DELIVERY'],
            ['from_status' => 'CHECKED',               'to_status' => 'ISSUE_REPORTED'],
            ['from_status' => 'ISSUE_REPORTED',        'to_status' => 'IN_DELIVERY'],
            ['from_status' => 'ISSUE_REPORTED',        'to_status' => 'CHECKED'],
            ['from_status' => 'ISSUE_REPORTED',        'to_status' => 'RETURNED'],
            ['from_status' => 'ISSUE_REPORTED',        'to_status' => 'AWAITING_CONFIRMATION'], // G5
            ['from_status' => 'IN_DELIVERY',           'to_status' => 'RECEIVED'],
            ['from_status' => 'IN_DELIVERY',           'to_status' => 'ISSUE_REPORTED'],
            ['from_status' => 'RECEIVED',              'to_status' => 'CLOSED'],
            ['from_status' => 'RETURNED',              'to_status' => 'CLOSED'],
        ];

        // An empty update list makes upsert() degrade to a plain INSERT, which
        // trips the composite primary key on a re-run. insertOrIgnore keeps the
        // reference data safe to seed repeatedly.
        DB::table('package_status_transitions')->insertOrIgnore($transitions);

        // 4. Geofence radius per service type. The customer app can create
        // INSTANT_FROZEN shipments, so both segments need a row before the
        // courier app can be validated at the destination.
        foreach (\App\Models\GeofencePolicy::DEFAULT_RADIUS_M as $serviceType => $radius) {
            DB::table('geofence_policies')->insertOrIgnore([
                'service_type' => $serviceType,
                'radius_m' => $radius,
            ]);
        }
    }
}
