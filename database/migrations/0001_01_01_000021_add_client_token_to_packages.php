<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE packages
    ADD COLUMN client_token text NOT NULL
        DEFAULT replace(gen_random_uuid()::text || gen_random_uuid()::text, '-', '');

ALTER TABLE packages ADD CONSTRAINT uq_packages_client_token UNIQUE (client_token);
ALTER TABLE packages ADD CONSTRAINT ck_packages_client_token_length CHECK (length(client_token) >= 32);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE packages DROP CONSTRAINT IF EXISTS ck_packages_client_token_length;
ALTER TABLE packages DROP CONSTRAINT IF EXISTS uq_packages_client_token;
ALTER TABLE packages DROP COLUMN IF EXISTS client_token;
SQL);
    }
};
