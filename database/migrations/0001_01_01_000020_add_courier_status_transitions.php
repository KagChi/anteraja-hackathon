<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('package_statuses')->whereIn('code', ['COURIER_ASSIGNED', 'CONFIRMED', 'IN_DELIVERY', 'ISSUE_REPORTED'])->count() >= 4) {
            DB::statement("
                INSERT INTO package_status_transitions (from_status, to_status)
                VALUES
                    ('COURIER_ASSIGNED', 'CONFIRMED'),
                    ('IN_DELIVERY', 'ISSUE_REPORTED')
                ON CONFLICT (from_status, to_status) DO NOTHING;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            DELETE FROM package_status_transitions
            WHERE (from_status = 'COURIER_ASSIGNED' AND to_status = 'CONFIRMED')
               OR (from_status = 'IN_DELIVERY' AND to_status = 'ISSUE_REPORTED');
        ");
    }
};
