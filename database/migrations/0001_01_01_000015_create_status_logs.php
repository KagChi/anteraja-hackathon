<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE status_logs (
    id          bigint GENERATED ALWAYS AS IDENTITY,
    package_id  bigint NOT NULL,
    from_status text,                    -- null for the first row of a package
    to_status   text NOT NULL,
    changed_by  bigint,                  -- null for buyer or system actions
    changed_at  timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_status_logs PRIMARY KEY (id),
    CONSTRAINT fk_status_logs_package    FOREIGN KEY (package_id)  REFERENCES packages (id) ON DELETE CASCADE,
    CONSTRAINT fk_status_logs_from       FOREIGN KEY (from_status) REFERENCES package_statuses (code),
    CONSTRAINT fk_status_logs_to         FOREIGN KEY (to_status)   REFERENCES package_statuses (code),
    CONSTRAINT fk_status_logs_changed_by FOREIGN KEY (changed_by)  REFERENCES staff (id)
);

CREATE INDEX idx_status_logs_package_id ON status_logs (package_id, changed_at);
CREATE INDEX idx_status_logs_changed_by ON status_logs (changed_by);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS status_logs;');
    }
};
