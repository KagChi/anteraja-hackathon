<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE condition_checks (
    id            bigint GENERATED ALWAYS AS IDENTITY,
    package_id    bigint NOT NULL,
    courier_id    bigint NOT NULL,
    is_normal     boolean NOT NULL,
    checklist     jsonb NOT NULL DEFAULT '{}'::jsonb,
    notes         text,
    temperature_c numeric(5,2),
    checked_at    timestamptz NOT NULL DEFAULT now(),
    created_at    timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_condition_checks PRIMARY KEY (id),
    CONSTRAINT fk_condition_checks_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    CONSTRAINT fk_condition_checks_courier FOREIGN KEY (courier_id) REFERENCES staff (id)
);

CREATE INDEX idx_condition_checks_package_id ON condition_checks (package_id, checked_at);
CREATE INDEX idx_condition_checks_courier_id ON condition_checks (courier_id);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS condition_checks;');
    }
};
