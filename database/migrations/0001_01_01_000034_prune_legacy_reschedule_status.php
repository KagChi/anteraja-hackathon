<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * RESCHEDULE_REQUESTED used to be a package status; the schedule refactor
     * turned it into a schedule_confirmations.response instead, so the enum no
     * longer carries the case. PackageStatusRef casts code to PackageStatus, so
     * a database seeded before the refactor still holds the stale row and its
     * transitions, which makes the admin shipment index throw. Prune it once no
     * parcel is parked on the status; a database that never had it is a no-op.
     */
    public function up(): void {
        if (DB::table('packages')->where('status', 'RESCHEDULE_REQUESTED')->exists()) {
            return;
        }

        DB::table('package_status_transitions')
            ->where('from_status', 'RESCHEDULE_REQUESTED')
            ->orWhere('to_status', 'RESCHEDULE_REQUESTED')
            ->delete();

        DB::table('package_statuses')
            ->where('code', 'RESCHEDULE_REQUESTED')
            ->delete();
    }

    public function down(): void {
        DB::table('package_statuses')->insertOrIgnore([
            'code' => 'RESCHEDULE_REQUESTED',
            'label' => 'Minta Jadwal Ulang',
            'is_waiting' => false,
            'is_terminal' => false,
            'sort_order' => 30,
        ]);

        DB::table('package_status_transitions')->insertOrIgnore([
            ['from_status' => 'AWAITING_CONFIRMATION', 'to_status' => 'RESCHEDULE_REQUESTED'],
            ['from_status' => 'RESCHEDULE_REQUESTED', 'to_status' => 'AWAITING_CONFIRMATION'],
        ]);
    }
};
