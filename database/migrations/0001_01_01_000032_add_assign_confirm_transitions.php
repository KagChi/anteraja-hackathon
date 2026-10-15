<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Assignment now comes before the receiving-date confirmation: a hub
     * shipment is handed to a courier while it is still CREATED, the courier or
     * the admin then asks the recipient to confirm the window, and a confirmed
     * parcel can go straight out for delivery.
     */
    public function up(): void {
        if (DB::table('package_statuses')->whereIn('code', ['CREATED', 'COURIER_ASSIGNED', 'AWAITING_CONFIRMATION', 'CONFIRMED', 'CHECKED'])->count() >= 5) {
            DB::statement("
                INSERT INTO package_status_transitions (from_status, to_status)
                VALUES
                    ('CREATED', 'COURIER_ASSIGNED'),
                    ('COURIER_ASSIGNED', 'AWAITING_CONFIRMATION'),
                    ('CONFIRMED', 'CHECKED')
                ON CONFLICT (from_status, to_status) DO NOTHING;
            ");
        }
    }

    public function down(): void {
        DB::statement("
            DELETE FROM package_status_transitions
            WHERE (from_status = 'CREATED' AND to_status = 'COURIER_ASSIGNED')
               OR (from_status = 'COURIER_ASSIGNED' AND to_status = 'AWAITING_CONFIRMATION')
               OR (from_status = 'CONFIRMED' AND to_status = 'CHECKED');
        ");
    }
};
