-- State percakapan WhatsApp yang dipakai workflow n8n Anteraja Frozen.
-- Tabel ini hidup di database n8n (bukan database aplikasi) karena hanya
-- berisi konteks pengiriman pesan dan nomor WhatsApp penerima.
CREATE TABLE IF NOT EXISTS anteraja_chat_state (
    phone           text PRIMARY KEY,
    tracking_number text,
    client_token    text,
    last_template   text,
    last_message_at timestamptz,
    context         jsonb NOT NULL DEFAULT '{}'::jsonb,
    updated_at      timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_anteraja_chat_state_tracking
    ON anteraja_chat_state (tracking_number);
