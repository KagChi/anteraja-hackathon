<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE SEQUENCE IF NOT EXISTS package_tracking_seq START 1;

CREATE TABLE packages (
    id                    bigint GENERATED ALWAYS AS IDENTITY,
    tracking_number       text NOT NULL DEFAULT ('AJ-FRZ-' || lpad(nextval('package_tracking_seq')::text, 7, '0')),
    buyer_id              bigint NOT NULL,
    created_by            bigint NOT NULL,
    courier_id            bigint,
    cooler_box_code       text,
    package_category      text NOT NULL DEFAULT 'Frozen Food',
    weight_kg             numeric(6,2) NOT NULL DEFAULT 1.00,
    status                text NOT NULL DEFAULT 'CREATED',
    scheduled_at          timestamptz NOT NULL,
    scheduled_end_at      timestamptz,
    resend_of_package_id  bigint,
    handling_instructions text,
    created_at            timestamptz NOT NULL DEFAULT now(),
    updated_at            timestamptz NOT NULL DEFAULT now(),
    closed_at             timestamptz,
    CONSTRAINT pk_packages PRIMARY KEY (id),
    CONSTRAINT uq_packages_tracking_number UNIQUE (tracking_number),
    CONSTRAINT fk_packages_buyer      FOREIGN KEY (buyer_id)   REFERENCES buyers (id),
    CONSTRAINT fk_packages_created_by FOREIGN KEY (created_by) REFERENCES staff (id),
    CONSTRAINT fk_packages_courier    FOREIGN KEY (courier_id) REFERENCES staff (id),
    CONSTRAINT fk_packages_status     FOREIGN KEY (status)     REFERENCES package_statuses (code),
    CONSTRAINT fk_packages_resend_of  FOREIGN KEY (resend_of_package_id) REFERENCES packages (id)
);

CREATE INDEX idx_packages_tracking_number ON packages (tracking_number);
CREATE INDEX idx_packages_buyer_id        ON packages (buyer_id);
CREATE INDEX idx_packages_created_by      ON packages (created_by);
CREATE INDEX idx_packages_courier_id      ON packages (courier_id);
CREATE INDEX idx_packages_status          ON packages (status);
CREATE INDEX idx_packages_scheduled_at    ON packages (scheduled_at);
CREATE INDEX idx_packages_resend_of       ON packages (resend_of_package_id) WHERE resend_of_package_id IS NOT NULL;

CREATE TRIGGER trg_packages_updated_at BEFORE UPDATE ON packages
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS packages;
DROP SEQUENCE IF EXISTS package_tracking_seq;
SQL);
    }
};
