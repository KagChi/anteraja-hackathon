<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Riwayat pesan WhatsApp: arah masuk dan keluar, isi, waktu, status
     * terkirim/gagal, dan siapa yang mengirim atau menerima.
     *
     * Notifikasi keluar sudah tercatat di tabel notifications sebagai template;
     * tabel ini menyimpan teks yang benar-benar dikirim, plus semua balasan
     * yang masuk. Pesan keluar dari workflow n8n dicatat lewat endpoint
     * internal yang sama.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE whatsapp_messages (
    id              bigint GENERATED ALWAYS AS IDENTITY,
    phone           text NOT NULL,
    role            text,
    direction       text NOT NULL,
    template        text,
    body            text NOT NULL,
    reply_body      text,
    status          text NOT NULL DEFAULT 'SENT',
    tracking_number text,
    external_id     text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_whatsapp_messages PRIMARY KEY (id),
    CONSTRAINT ck_whatsapp_messages_role CHECK (role IS NULL OR role IN ('BUYER', 'COURIER', 'ADMIN')),
    CONSTRAINT ck_whatsapp_messages_direction CHECK (direction IN ('IN', 'OUT')),
    CONSTRAINT ck_whatsapp_messages_status CHECK (status IN ('SENT', 'FAILED', 'RECEIVED'))
);

-- Satu pesan WhatsApp hanya boleh tercatat sekali: id-nya yang membuat balasan
-- dobel tidak pernah memicu aksi kedua.
CREATE UNIQUE INDEX uq_whatsapp_messages_external
    ON whatsapp_messages (external_id) WHERE external_id IS NOT NULL;
CREATE INDEX idx_whatsapp_messages_phone ON whatsapp_messages (phone, created_at DESC);
CREATE INDEX idx_whatsapp_messages_tracking ON whatsapp_messages (tracking_number, created_at DESC);

CREATE TRIGGER trg_whatsapp_messages_updated_at BEFORE UPDATE ON whatsapp_messages
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS whatsapp_messages;');
    }
};
