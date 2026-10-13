<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE TABLE delivery_milestones (
                id          bigint GENERATED ALWAYS AS IDENTITY,
                package_id  bigint NOT NULL,
                milestone   text NOT NULL,
                courier_id  bigint NOT NULL,
                latitude    numeric(9,6),
                longitude   numeric(9,6),
                occurred_at timestamptz NOT NULL DEFAULT now(),
                created_at  timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT pk_delivery_milestones PRIMARY KEY (id),
                CONSTRAINT fk_delivery_milestones_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
                CONSTRAINT fk_delivery_milestones_courier FOREIGN KEY (courier_id) REFERENCES staff (id),
                CONSTRAINT ck_delivery_milestones_milestone CHECK (milestone IN ('DEPARTED', 'ARRIVED'))
            )
        ");

        DB::statement('CREATE INDEX idx_delivery_milestones_package ON delivery_milestones (package_id, id)');
        DB::statement('CREATE INDEX idx_delivery_milestones_courier ON delivery_milestones (courier_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS delivery_milestones');
    }
};
