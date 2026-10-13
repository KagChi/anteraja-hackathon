<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION packages_validate_status() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.status IS DISTINCT FROM OLD.status
       AND NOT EXISTS (
            SELECT 1 FROM package_status_transitions
            WHERE from_status = OLD.status AND to_status = NEW.status
       )
    THEN
        RAISE EXCEPTION 'Invalid package status transition: % -> %', OLD.status, NEW.status
            USING ERRCODE = 'check_violation';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION packages_log_status() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'INSERT' OR NEW.status IS DISTINCT FROM OLD.status THEN
        INSERT INTO status_logs (package_id, from_status, to_status, changed_by)
        VALUES (
            NEW.id,
            CASE WHEN TG_OP = 'UPDATE' THEN OLD.status END,
            NEW.status,
            NULLIF(current_setting('app.staff_id', true), '')::bigint
        );
    END IF;
    RETURN NULL;
END;
$$;

CREATE TRIGGER trg_packages_validate_status BEFORE UPDATE OF status ON packages
    FOR EACH ROW EXECUTE FUNCTION packages_validate_status();

CREATE TRIGGER trg_packages_log_status AFTER INSERT OR UPDATE OF status ON packages
    FOR EACH ROW EXECUTE FUNCTION packages_log_status();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_packages_log_status ON packages;
DROP TRIGGER IF EXISTS trg_packages_validate_status ON packages;
DROP FUNCTION IF EXISTS packages_log_status();
DROP FUNCTION IF EXISTS packages_validate_status();
SQL);
    }
};
