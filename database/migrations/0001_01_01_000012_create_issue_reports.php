<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE issue_reports (
    id                      bigint GENERATED ALWAYS AS IDENTITY,
    check_id                bigint NOT NULL,
    issue_category          text,
    description             text NOT NULL,
    photo_url               text,
    quality_inspection_note text,
    decision                text,
    decision_reason         text,
    decided_by              bigint,
    decided_at              timestamptz,
    reported_at             timestamptz NOT NULL DEFAULT now(),
    created_at              timestamptz NOT NULL DEFAULT now(),
    updated_at              timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_issue_reports PRIMARY KEY (id),
    CONSTRAINT uq_issue_reports_check UNIQUE (check_id),
    CONSTRAINT fk_issue_reports_check      FOREIGN KEY (check_id)   REFERENCES condition_checks (id) ON DELETE CASCADE,
    CONSTRAINT fk_issue_reports_decided_by FOREIGN KEY (decided_by) REFERENCES staff (id),
    CONSTRAINT ck_issue_reports_decision CHECK (decision IN ('PROCEED', 'RECHECK', 'RETURN', 'RESEND', 'RESCHEDULE')),
    CONSTRAINT ck_issue_reports_decided_together
        CHECK ((decision IS NULL AND decided_by IS NULL AND decided_at IS NULL)
            OR (decision IS NOT NULL AND decided_by IS NOT NULL AND decided_at IS NOT NULL)),
    CONSTRAINT ck_issue_reports_reason_with_decision
        CHECK (decision IS NULL OR decision_reason IS NOT NULL)
);

CREATE INDEX idx_issue_reports_decided_by ON issue_reports (decided_by);
CREATE INDEX idx_issue_reports_open ON issue_reports (reported_at) WHERE decision IS NULL;

CREATE TRIGGER trg_issue_reports_updated_at BEFORE UPDATE ON issue_reports
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS issue_reports;');
    }
};
