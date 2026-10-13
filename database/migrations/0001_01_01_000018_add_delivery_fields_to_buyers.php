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
            ALTER TABLE buyers
                ADD COLUMN address_type text NOT NULL DEFAULT 'RESIDENTIAL',
                ADD COLUMN latitude     numeric(9,6),
                ADD COLUMN longitude    numeric(9,6);
        ");

        DB::statement("
            ALTER TABLE buyers ADD CONSTRAINT ck_buyers_address_type CHECK (address_type IN ('RESIDENTIAL', 'COMMERCIAL'));
        ");

        DB::statement('
            ALTER TABLE buyers ADD CONSTRAINT ck_buyers_coordinates
                CHECK ((latitude IS NULL) = (longitude IS NULL)
                       AND (latitude IS NULL OR (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)));
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE buyers DROP CONSTRAINT IF EXISTS ck_buyers_coordinates;');
        DB::statement('ALTER TABLE buyers DROP CONSTRAINT IF EXISTS ck_buyers_address_type;');
        DB::statement('ALTER TABLE buyers DROP COLUMN IF EXISTS longitude;');
        DB::statement('ALTER TABLE buyers DROP COLUMN IF EXISTS latitude;');
        DB::statement('ALTER TABLE buyers DROP COLUMN IF EXISTS address_type;');
    }
};
