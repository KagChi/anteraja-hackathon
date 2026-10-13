<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE staff_roles (
    code        text PRIMARY KEY,
    label       text NOT NULL
);

CREATE TABLE package_statuses (
    code        text PRIMARY KEY,
    label       text NOT NULL,
    is_waiting  boolean NOT NULL DEFAULT false,
    is_terminal boolean NOT NULL DEFAULT false,
    sort_order  integer NOT NULL
);

CREATE TABLE package_status_transitions (
    from_status text NOT NULL,
    to_status   text NOT NULL,
    CONSTRAINT pk_package_status_transitions PRIMARY KEY (from_status, to_status),
    CONSTRAINT fk_transitions_from FOREIGN KEY (from_status) REFERENCES package_statuses (code),
    CONSTRAINT fk_transitions_to   FOREIGN KEY (to_status)   REFERENCES package_statuses (code),
    CONSTRAINT ck_transitions_not_self CHECK (from_status <> to_status)
);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS package_status_transitions;
DROP TABLE IF EXISTS package_statuses;
DROP TABLE IF EXISTS staff_roles;
SQL);
    }
};
