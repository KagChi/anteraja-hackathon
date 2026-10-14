<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE buyers
                ADD COLUMN password text NULL,
                ADD COLUMN phone_verified_at timestamptz NULL,
                ADD COLUMN is_active boolean NOT NULL DEFAULT true;
        ');

        DB::statement("
            UPDATE buyers
            SET phone = '+62' || SUBSTRING(phone FROM 2)
            WHERE phone LIKE '0%';
        ");

        DB::statement("
            UPDATE buyers
            SET phone = '+' || phone
            WHERE phone LIKE '62%';
        ");

        DB::statement('DROP INDEX IF EXISTS idx_buyers_phone;');
        DB::statement('CREATE UNIQUE INDEX uq_buyers_phone ON buyers (phone);');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_buyers_phone;');
        DB::statement('CREATE INDEX idx_buyers_phone ON buyers (phone);');
        DB::statement('
            ALTER TABLE buyers
                DROP COLUMN IF EXISTS is_active,
                DROP COLUMN IF EXISTS phone_verified_at,
                DROP COLUMN IF EXISTS password;
        ');
    }
};
