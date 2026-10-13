<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
DROP INDEX IF EXISTS idx_packages_resend_of;

CREATE UNIQUE INDEX uq_packages_one_replacement
    ON packages (resend_of_package_id) WHERE resend_of_package_id IS NOT NULL;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP INDEX IF EXISTS uq_packages_one_replacement;

CREATE INDEX idx_packages_resend_of
    ON packages (resend_of_package_id) WHERE resend_of_package_id IS NOT NULL;
SQL);
    }
};
