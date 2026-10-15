<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * A delivery PIN travels to the recipient when the courier departs and is
     * the code the courier types back in to prove the hand-over. Only the hash
     * is kept, so the plain six digits live nowhere but the message.
     */
    public function up(): void {
        DB::statement("
            ALTER TABLE packages
                ADD COLUMN delivery_pin_hash     text,
                ADD COLUMN delivery_pin_sent_at  timestamptz,
                ADD COLUMN delivery_pin_attempts smallint NOT NULL DEFAULT 0;
        ");
    }

    public function down(): void {
        DB::statement('
            ALTER TABLE packages
                DROP COLUMN IF EXISTS delivery_pin_attempts,
                DROP COLUMN IF EXISTS delivery_pin_sent_at,
                DROP COLUMN IF EXISTS delivery_pin_hash;
        ');
    }
};
