<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE package_items (
    id          bigint GENERATED ALWAYS AS IDENTITY,
    package_id  bigint NOT NULL,
    product_id  bigint NOT NULL,
    quantity    integer NOT NULL DEFAULT 1,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pk_package_items PRIMARY KEY (id),
    CONSTRAINT uq_package_items_package_product UNIQUE (package_id, product_id),
    CONSTRAINT fk_package_items_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    CONSTRAINT fk_package_items_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT ck_package_items_quantity CHECK (quantity > 0)
);

CREATE INDEX idx_package_items_product_id ON package_items (product_id);

CREATE TRIGGER trg_package_items_updated_at BEFORE UPDATE ON package_items
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS package_items;');
    }
};
