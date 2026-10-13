<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE deliveries (
    id                  bigint GENERATED ALWAYS AS IDENTITY,
    package_id          bigint NOT NULL,
    received_by         text NOT NULL,
    recipient_relation  text,
    photo_url           text,
    receipt_note        text,
    delivered_at        timestamptz NOT NULL DEFAULT now(),
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_deliveries PRIMARY KEY (id),
    CONSTRAINT uq_deliveries_package UNIQUE (package_id),
    CONSTRAINT fk_deliveries_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
);

CREATE INDEX idx_deliveries_delivered_at ON deliveries (delivered_at);

CREATE TRIGGER trg_deliveries_updated_at BEFORE UPDATE ON deliveries
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS deliveries;');
    }
};
