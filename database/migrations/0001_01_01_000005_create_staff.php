<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE staff (
    id             bigint GENERATED ALWAYS AS IDENTITY,
    name           text NOT NULL,
    role           text NOT NULL,
    phone          text NOT NULL,
    email          text,
    password       text,
    remember_token text,
    is_active      boolean NOT NULL DEFAULT true,
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_staff PRIMARY KEY (id),
    CONSTRAINT uq_staff_phone UNIQUE (phone),
    CONSTRAINT fk_staff_role FOREIGN KEY (role) REFERENCES staff_roles (code)
);

CREATE UNIQUE INDEX uq_staff_email ON staff (lower(email)) WHERE email IS NOT NULL;
CREATE INDEX idx_staff_role ON staff (role) WHERE is_active;

CREATE TRIGGER trg_staff_updated_at BEFORE UPDATE ON staff
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS staff;');
    }
};
