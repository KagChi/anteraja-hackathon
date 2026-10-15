<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The parcel's destination is the recipient, not the buyer.
     *
     * The buyer row belongs to the customer who sends the parcel — before this
     * migration it was also the row the courier navigated to. Every shipment
     * now carries its own recipient block: the family member or friend at the
     * door, who has no account and is reached over WhatsApp.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE packages
                ADD COLUMN recipient_name         text,
                ADD COLUMN recipient_phone        text,
                ADD COLUMN recipient_address      text,
                ADD COLUMN recipient_area         text,
                ADD COLUMN recipient_address_type text NOT NULL DEFAULT 'RESIDENTIAL',
                ADD COLUMN recipient_latitude     numeric(9,6),
                ADD COLUMN recipient_longitude    numeric(9,6);
        ");

        DB::statement("
            ALTER TABLE packages ADD CONSTRAINT ck_packages_recipient_address_type
                CHECK (recipient_address_type IN ('RESIDENTIAL', 'COMMERCIAL'));
        ");

        DB::statement('
            ALTER TABLE packages ADD CONSTRAINT ck_packages_recipient_coordinates
                CHECK ((recipient_latitude IS NULL) = (recipient_longitude IS NULL)
                       AND (recipient_latitude IS NULL OR (recipient_latitude BETWEEN -90 AND 90 AND recipient_longitude BETWEEN -180 AND 180)));
        ');

        // Shipments that predate the recipient block were delivered to the
        // buyer, so their destination starts as a copy of that row.
        DB::statement('
            UPDATE packages p
               SET recipient_name         = b.name,
                   recipient_phone        = b.phone,
                   recipient_address      = b.address,
                   recipient_area         = b.area,
                   recipient_address_type = b.address_type,
                   recipient_latitude     = b.latitude,
                   recipient_longitude    = b.longitude
              FROM buyers b
             WHERE b.id = p.buyer_id
               AND p.recipient_name IS NULL;
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE packages DROP CONSTRAINT IF EXISTS ck_packages_recipient_coordinates;');
        DB::statement('ALTER TABLE packages DROP CONSTRAINT IF EXISTS ck_packages_recipient_address_type;');
        DB::statement('
            ALTER TABLE packages
                DROP COLUMN IF EXISTS recipient_longitude,
                DROP COLUMN IF EXISTS recipient_latitude,
                DROP COLUMN IF EXISTS recipient_address_type,
                DROP COLUMN IF EXISTS recipient_area,
                DROP COLUMN IF EXISTS recipient_address,
                DROP COLUMN IF EXISTS recipient_phone,
                DROP COLUMN IF EXISTS recipient_name;
        ');
    }
};
