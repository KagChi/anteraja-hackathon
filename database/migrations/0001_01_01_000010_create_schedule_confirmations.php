<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE schedule_confirmations (
    id                    bigint GENERATED ALWAYS AS IDENTITY,
    package_id            bigint NOT NULL,
    response              text NOT NULL DEFAULT 'PENDING',
    proposed_scheduled_at timestamptz,
    requested_at          timestamptz NOT NULL DEFAULT now(),
    responded_at          timestamptz,
    created_at            timestamptz NOT NULL DEFAULT now(),
    updated_at            timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_schedule_confirmations PRIMARY KEY (id),
    CONSTRAINT fk_schedule_confirmations_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    CONSTRAINT ck_schedule_confirmations_response
        CHECK (response IN ('PENDING', 'READY', 'RESCHEDULE_REQUESTED')),
    CONSTRAINT ck_schedule_confirmations_responded
        CHECK ((response = 'PENDING' AND responded_at IS NULL)
            OR (response <> 'PENDING' AND responded_at IS NOT NULL))
);

CREATE INDEX idx_schedule_confirmations_package_id ON schedule_confirmations (package_id);
CREATE UNIQUE INDEX uq_schedule_confirmations_one_pending
    ON schedule_confirmations (package_id) WHERE response = 'PENDING';
CREATE INDEX idx_schedule_confirmations_pending_age
    ON schedule_confirmations (requested_at) WHERE response = 'PENDING';

CREATE TRIGGER trg_schedule_confirmations_updated_at BEFORE UPDATE ON schedule_confirmations
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS schedule_confirmations;');
    }
};
