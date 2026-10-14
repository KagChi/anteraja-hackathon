<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Courier GPS validation, ported from the capstone's FRD-01 and FRD-06.
     *
     * geofence_policies   -- the radius the hub tolerates per service type.
     * location_checks     -- every assessment the server made, kept as evidence.
     * gps_lock_requests   -- a blocked courier asking the hub to review, and the
     *                        hub's decision, which is what lets a retry through.
     *
     * Coordinates are plain numeric lat/lng like buyers.latitude, so distance is
     * computed with the haversine helper rather than PostGIS.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE geofence_policies (
    service_type text PRIMARY KEY,
    radius_m     integer NOT NULL,
    updated_by   bigint,
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT fk_geofence_policies_updated_by FOREIGN KEY (updated_by) REFERENCES staff (id),
    CONSTRAINT ck_geofence_policies_service CHECK (service_type IN ('REGULER', 'INSTANT_FROZEN')),
    CONSTRAINT ck_geofence_policies_radius  CHECK (radius_m BETWEEN 10 AND 1000)
);

CREATE TRIGGER trg_geofence_policies_updated_at BEFORE UPDATE ON geofence_policies
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE location_checks (
    id              bigint GENERATED ALWAYS AS IDENTITY,
    package_id      bigint NOT NULL,
    staff_id        bigint,
    action          text NOT NULL,
    latitude        numeric(9,6) NOT NULL,
    longitude       numeric(9,6) NOT NULL,
    accuracy_m      numeric(8,2),
    level           text NOT NULL,
    reasons         jsonb NOT NULL DEFAULT '[]'::jsonb,
    distance_m      integer,
    radius_m        integer,
    inside_geofence boolean,
    applied         boolean NOT NULL DEFAULT false,
    override_id     bigint,
    created_at      timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_location_checks PRIMARY KEY (id),
    CONSTRAINT fk_location_checks_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    CONSTRAINT fk_location_checks_staff   FOREIGN KEY (staff_id)   REFERENCES staff (id),
    CONSTRAINT ck_location_checks_action CHECK (action IN ('DEPART', 'ARRIVE', 'POD')),
    CONSTRAINT ck_location_checks_level  CHECK (level IN ('CLEAN', 'SUSPECTED', 'BLOCKED'))
);

CREATE INDEX idx_location_checks_package ON location_checks (package_id, created_at);
CREATE INDEX idx_location_checks_level   ON location_checks (level, created_at DESC) WHERE level <> 'CLEAN';

CREATE TABLE gps_lock_requests (
    id              bigint GENERATED ALWAYS AS IDENTITY,
    package_id      bigint NOT NULL,
    staff_id        bigint,
    action          text NOT NULL,
    level           text NOT NULL,
    reasons         jsonb NOT NULL DEFAULT '[]'::jsonb,
    latitude        numeric(9,6),
    longitude       numeric(9,6),
    status          text NOT NULL DEFAULT 'PENDING',
    decided_by      bigint,
    decision_reason text,
    decided_at      timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_gps_lock_requests PRIMARY KEY (id),
    CONSTRAINT fk_gps_lock_requests_package   FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    CONSTRAINT fk_gps_lock_requests_staff     FOREIGN KEY (staff_id)   REFERENCES staff (id),
    CONSTRAINT fk_gps_lock_requests_decided_by FOREIGN KEY (decided_by) REFERENCES staff (id),
    CONSTRAINT ck_gps_lock_requests_action CHECK (action IN ('DEPART', 'ARRIVE', 'POD')),
    CONSTRAINT ck_gps_lock_requests_level  CHECK (level IN ('SUSPECTED', 'BLOCKED')),
    CONSTRAINT ck_gps_lock_requests_status CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED')),
    CONSTRAINT ck_gps_lock_requests_decision CHECK ((status = 'PENDING') = (decided_at IS NULL))
);

-- One open request per action, so a courier tapping "minta tinjauan" twice does
-- not queue a second review.
CREATE UNIQUE INDEX uq_gps_lock_requests_open
    ON gps_lock_requests (package_id, action) WHERE status = 'PENDING';
CREATE INDEX idx_gps_lock_requests_status ON gps_lock_requests (status, created_at DESC);

ALTER TABLE location_checks
    ADD CONSTRAINT fk_location_checks_override FOREIGN KEY (override_id) REFERENCES gps_lock_requests (id);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE location_checks DROP CONSTRAINT IF EXISTS fk_location_checks_override;
DROP TABLE IF EXISTS gps_lock_requests;
DROP TABLE IF EXISTS location_checks;
DROP TABLE IF EXISTS geofence_policies;
SQL);
    }
};
