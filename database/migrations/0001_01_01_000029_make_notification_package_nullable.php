<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ringkasan harian tidak terikat pada satu paket, jadi kolom package_id
     * harus boleh kosong. Notifikasi lain tetap selalu menunjuk paketnya.
     */
    public function up(): void
    {
        DB::unprepared('ALTER TABLE notifications ALTER COLUMN package_id DROP NOT NULL;');
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE notifications ALTER COLUMN package_id SET NOT NULL;');
    }
};
