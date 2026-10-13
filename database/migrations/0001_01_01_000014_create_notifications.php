<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE notifications (
    id              bigint GENERATED ALWAYS AS IDENTITY,
    package_id      bigint NOT NULL,
    recipient_type  text NOT NULL,
    recipient_id    bigint NOT NULL,
    template        text NOT NULL,
    payload         jsonb NOT NULL DEFAULT '{}'::jsonb,
    status          text NOT NULL DEFAULT 'QUEUED',
    sent_at         timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_notifications PRIMARY KEY (id),
    CONSTRAINT fk_notifications_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    CONSTRAINT ck_notifications_recipient_type CHECK (recipient_type IN ('BUYER', 'COURIER', 'ADMIN')),
    CONSTRAINT ck_notifications_status CHECK (status IN ('QUEUED', 'SENT', 'FAILED'))
);

CREATE INDEX idx_notifications_package_id ON notifications (package_id);
CREATE INDEX idx_notifications_recipient  ON notifications (recipient_type, recipient_id);
CREATE INDEX idx_notifications_queue      ON notifications (created_at) WHERE status = 'QUEUED';

CREATE TRIGGER trg_notifications_updated_at BEFORE UPDATE ON notifications
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS notifications;');
    }
};
