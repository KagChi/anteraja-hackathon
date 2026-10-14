<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The catalog the customer app shops from.
     *
     * Products started life as an admin-side picklist (name + handling notes);
     * the storefront needs a stable URL, a price, a unit weight and art.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE products
                ADD COLUMN slug         text,
                ADD COLUMN category     text NOT NULL DEFAULT 'Frozen Food',
                ADD COLUMN description  text,
                ADD COLUMN price_idr    bigint NOT NULL DEFAULT 0,
                ADD COLUMN unit         text NOT NULL DEFAULT 'pack',
                ADD COLUMN weight_kg    numeric(6,2) NOT NULL DEFAULT 1.00,
                ADD COLUMN image_path   text,
                ADD COLUMN is_available boolean NOT NULL DEFAULT true,
                ADD COLUMN sort_order   integer NOT NULL DEFAULT 0;
        ");

        DB::statement('CREATE UNIQUE INDEX uq_products_slug ON products (slug) WHERE slug IS NOT NULL;');
        DB::statement('CREATE INDEX idx_products_available ON products (is_available, sort_order);');
        DB::statement('ALTER TABLE products ADD CONSTRAINT ck_products_price CHECK (price_idr >= 0);');
        DB::statement('ALTER TABLE products ADD CONSTRAINT ck_products_weight CHECK (weight_kg > 0);');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS ck_products_weight;');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS ck_products_price;');
        DB::statement('DROP INDEX IF EXISTS idx_products_available;');
        DB::statement('DROP INDEX IF EXISTS uq_products_slug;');
        DB::statement('
            ALTER TABLE products
                DROP COLUMN IF EXISTS sort_order,
                DROP COLUMN IF EXISTS is_available,
                DROP COLUMN IF EXISTS image_path,
                DROP COLUMN IF EXISTS weight_kg,
                DROP COLUMN IF EXISTS unit,
                DROP COLUMN IF EXISTS price_idr,
                DROP COLUMN IF EXISTS description,
                DROP COLUMN IF EXISTS category,
                DROP COLUMN IF EXISTS slug;
        ');
    }
};
