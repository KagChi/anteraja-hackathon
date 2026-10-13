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
        DB::statement("
            ALTER TABLE packages
                ADD COLUMN service_type        text NOT NULL DEFAULT 'REGULER',
                ADD COLUMN koli_count          integer NOT NULL DEFAULT 1,
                ADD COLUMN courier_accepted_at timestamptz;
        ");

        DB::statement("
            ALTER TABLE packages ADD CONSTRAINT ck_packages_service_type CHECK (service_type IN ('REGULER', 'INSTANT_FROZEN'));
        ");

        DB::statement('
            ALTER TABLE packages ADD CONSTRAINT ck_packages_koli_count CHECK (koli_count > 0);
        ');

        DB::statement('
            ALTER TABLE packages ADD CONSTRAINT ck_packages_accepted_needs_courier
                CHECK (courier_accepted_at IS NULL OR courier_id IS NOT NULL);
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE packages DROP CONSTRAINT IF EXISTS ck_packages_accepted_needs_courier;');
        DB::statement('ALTER TABLE packages DROP CONSTRAINT IF EXISTS ck_packages_koli_count;');
        DB::statement('ALTER TABLE packages DROP CONSTRAINT IF EXISTS ck_packages_service_type;');
        DB::statement('ALTER TABLE packages DROP COLUMN IF EXISTS courier_accepted_at;');
        DB::statement('ALTER TABLE packages DROP COLUMN IF EXISTS koli_count;');
        DB::statement('ALTER TABLE packages DROP COLUMN IF EXISTS service_type;');
    }
};
