<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE issue_reports
    ADD COLUMN buyer_choice text,
    ADD COLUMN buyer_responded_at timestamptz;

ALTER TABLE issue_reports
    ADD CONSTRAINT ck_issue_reports_buyer_choice
        CHECK (buyer_choice IS NULL OR buyer_choice IN ('RETURN', 'RESEND', 'PROCEED', 'DISCUSS'));
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE issue_reports DROP CONSTRAINT IF EXISTS ck_issue_reports_buyer_choice;
ALTER TABLE issue_reports DROP COLUMN IF EXISTS buyer_responded_at;
ALTER TABLE issue_reports DROP COLUMN IF EXISTS buyer_choice;
SQL);
    }
};
