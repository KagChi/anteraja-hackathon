<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE buyers (
    id          bigint GENERATED ALWAYS AS IDENTITY,
    name        text NOT NULL,
    phone       text NOT NULL,
    address     text NOT NULL,
    area        text,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_buyers PRIMARY KEY (id)
);

CREATE INDEX idx_buyers_phone ON buyers (phone);
CREATE INDEX idx_buyers_area  ON buyers (area);

CREATE TRIGGER trg_buyers_updated_at BEFORE UPDATE ON buyers
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS buyers;');
    }
};
