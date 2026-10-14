<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A shipment can now be opened by the customer app instead of the hub, so
     * "who created it" stops being a staff row: created_by becomes nullable and
     * source says which door it came through. status_logs.changed_by was already
     * nullable for buyer and system actions, so the transition trigger needs no
     * change.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE packages ALTER COLUMN created_by DROP NOT NULL;');
        DB::statement("ALTER TABLE packages ADD COLUMN source text NOT NULL DEFAULT 'ADMIN';");
        DB::statement("
            ALTER TABLE packages ADD CONSTRAINT ck_packages_source
                CHECK (source IN ('ADMIN', 'CLIENT_APP'));
        ");
        DB::statement("
            ALTER TABLE packages ADD CONSTRAINT ck_packages_creator_matches_source
                CHECK (source = 'CLIENT_APP' OR created_by IS NOT NULL);
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE packages DROP CONSTRAINT IF EXISTS ck_packages_creator_matches_source;');
        DB::statement('ALTER TABLE packages DROP CONSTRAINT IF EXISTS ck_packages_source;');
        DB::statement('ALTER TABLE packages DROP COLUMN IF EXISTS source;');
        DB::statement('UPDATE packages SET created_by = (SELECT id FROM staff ORDER BY id LIMIT 1) WHERE created_by IS NULL;');
        DB::statement('ALTER TABLE packages ALTER COLUMN created_by SET NOT NULL;');
    }
};
