<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Product;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DemoDataSeeder extends Seeder {
    public function run(): void {
        $now = Carbon::now('UTC');

        // The catalog is upserted on every run rather than guarded: the
        // storefront reads price, weight and art, and a database seeded before
        // those columns existed would otherwise show empty products. The demo
        // shipment graph below is what the guard actually protects.
        $this->seedCatalog();

        // Demo data uses fixed phones, tracking numbers and client tokens, so a
        // second db:seed run would violate every unique index. Packages are the
        // last records created; once they exist the data is already in place.
        if (DB::table('packages')->exists()) {
            $this->command?->info('Demo data already present; skipping DemoDataSeeder.');

            return;
        }

        // 1. Staff (Admin & Couriers)
        // The admin account is upserted on phone so seeding is repeatable and
        // the seeded credentials stay stable. Staff::password carries the
        // "hashed" cast, so the plain value below is hashed on write.
        $adminId = Staff::firstOrCreate(['phone' => '081112345678'], [
            'name' => 'Dimas Pratama',
            'role' => 'ADMIN',
            'email' => 'dimas@anteraja.id',
            'password' => 'password',
            'is_active' => true,
        ])->id;

        // Nomor kurir tidak boleh sama dengan nomor pembeli mana pun: satu
        // nomor WhatsApp hanya bisa dikenali sebagai satu pihak, dan nomor ini
        // yang dipakai kurir untuk membalas tugas di WhatsApp. Database lama
        // yang masih memakai nomor bertabrakan (081298765432, sama dengan
        // pembeli Rina) diperbaiki di bawah.
        $budi = Staff::firstOrCreate(['email' => 'budi@anteraja.id'], [
            'name' => 'Budi Santoso',
            'role' => 'COURIER',
            'phone' => '081298760001',
            'password' => 'password',
            'is_active' => true,
        ]);

        if ($budi->phone !== '081298760001' && ! Staff::where('phone', '081298760001')->exists()) {
            $budi->update(['phone' => '081298760001']);
        }

        $budiId = $budi->id;

        $ahmadId = Staff::firstOrCreate(['phone' => '081234567890'], [
            'name' => 'Ahmad Subagyo',
            'role' => 'COURIER',
            'email' => 'ahmad@anteraja.id',
            'password' => 'password',
            'is_active' => true,
        ])->id;

        $daniId = Staff::firstOrCreate(['phone' => '081311223344'], [
            'name' => 'Dani Kurniawan',
            'role' => 'COURIER',
            'email' => 'dani@anteraja.id',
            'password' => 'password',
            'is_active' => true,
        ])->id;

        $hendraCourierId = Staff::firstOrCreate(['phone' => '081399887766'], [
            'name' => 'Hendra Setiawan',
            'role' => 'COURIER',
            'email' => 'hendra.courier@anteraja.id',
            'password' => 'password',
            'is_active' => true,
        ])->id;

        // 2. Buyers
        // Buyers normalise phone to E.164 on save, so the lookup key must
        // already be in +62 form or a re-run would insert a duplicate.
        // Same password as the staff demo accounts so the run book lists one
        // credential for every role.
        $buyerPassword = 'password';

        $rinaId = Buyer::firstOrCreate(['phone' => '+6281298765432'], [
            'name' => 'Rina Putri',
            'password' => $buyerPassword,
            'phone_verified_at' => $now,
            'is_active' => true,
            'address' => 'Jl. Melati Indah No. 42, RT 03 / RW 07, Tebet Barat, Jakarta Selatan, DKI Jakarta 12810',
            'area' => 'Tebet Barat, Jakarta Selatan',
            'address_type' => 'RESIDENTIAL',
            'latitude' => -6.229746,
            'longitude' => 106.848529,
        ])->id;

        $hendraBuyerId = Buyer::firstOrCreate(['phone' => '+6281222334455'], [
            'name' => 'Hendra Wijaya',
            'password' => $buyerPassword,
            'phone_verified_at' => $now,
            'is_active' => true,
            'address' => 'Jl. Boulevard Raya No. 12, Kelapa Gading, Jakarta Utara',
            'area' => 'Kelapa Gading, Jakarta Utara',
        ])->id;

        $sitiId = Buyer::firstOrCreate(['phone' => '+6281333445566'], [
            'name' => 'Siti Rahma',
            'password' => $buyerPassword,
            'phone_verified_at' => $now,
            'is_active' => true,
            'address' => 'Jl. Kyai Maja No. 8, Kebayoran Baru, Jakarta Selatan',
            'area' => 'Kebayoran Baru, Jakarta Selatan',
        ])->id;

        $kevinId = Buyer::firstOrCreate(['phone' => '+6281744556677'], [
            'name' => 'Kevin Tan',
            'password' => $buyerPassword,
            'phone_verified_at' => $now,
            'is_active' => true,
            'address' => 'Pluit Kencana No. 5, Pluit, Jakarta Utara',
            'area' => 'Pluit, Jakarta Utara',
        ])->id;

        $dewiId = Buyer::firstOrCreate(['phone' => '+6281855667788'], [
            'name' => 'Dewi Lestari',
            'password' => $buyerPassword,
            'phone_verified_at' => $now,
            'is_active' => true,
            'address' => 'Jl. BSD Grand Boulevard, Serpong, Tangerang Selatan',
            'area' => 'Serpong, Tangerang Selatan',
        ])->id;

        $rizkyId = Buyer::firstOrCreate(['phone' => '+6281966778899'], [
            'name' => 'Rizky Ramadhan',
            'password' => $buyerPassword,
            'phone_verified_at' => $now,
            'is_active' => true,
            'address' => 'Jl. TB Simatupang No. 20, Cilandak, Jakarta Selatan',
            'area' => 'Cilandak, Jakarta Selatan',
        ])->id;

        // 3. Products (rows come from the catalog upsert above)
        $p1 = Product::where('name', 'Nugget Ayam 1 kg')->value('id');
        $p2 = Product::where('name', 'Daging Sapi Slice 500g')->value('id');
        $p3 = Product::where('name', 'Salmon Fillet Beku 250g')->value('id');
        $p4 = Product::where('name', 'Es Krim Premium 1L')->value('id');

        // Helper to safely step package statuses through FSM
        $transition = function (int $packageId, string $toStatus, ?int $staffId = null): void {
            if ($staffId !== null) {
                DB::statement("SELECT set_config('app.staff_id', ?, true)", [(string) $staffId]);
            } else {
                DB::statement("SELECT set_config('app.staff_id', '', true)");
            }
            DB::table('packages')->where('id', $packageId)->update([
                'status' => $toStatus,
                'updated_at' => Carbon::now('UTC'),
            ]);
        };

        // Package 1: AJ-FRZ-0012345 (Rina Putri) - ISSUE_REPORTED (Open Issue)
        $pkg1Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012345',
            'buyer_id' => $rinaId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-001',
            'package_category' => 'Frozen Food',
            'weight_kg' => 2.50,
            'scheduled_at' => $now->copy()->addHours(2),
            'scheduled_end_at' => $now->copy()->addHours(4),
            'handling_instructions' => 'Produk beku, serahkan langsung ke penerima. Jangan ditinggal di pagar atau pos satpam.',
            'created_at' => $now->copy()->subHours(5),
            'updated_at' => $now->copy()->subHours(5),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg1Id, 'product_id' => $p1, 'quantity' => 1]);
        DB::table('package_items')->insert(['package_id' => $pkg1Id, 'product_id' => $p4, 'quantity' => 2]);
        DB::table('schedule_confirmations')->insert([
            'package_id' => $pkg1Id,
            'response' => 'READY',
            'proposed_scheduled_at' => $now->copy()->addHours(2),
            'requested_at' => $now->copy()->subHours(5),
            'responded_at' => $now->copy()->subHours(4),
        ]);

        $transition($pkg1Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg1Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg1Id)->update(['courier_id' => $budiId]);
        $transition($pkg1Id, 'COURIER_ASSIGNED', $adminId);
        $transition($pkg1Id, 'CHECKED', $budiId);
        $transition($pkg1Id, 'ISSUE_REPORTED', $budiId);

        $chk1Id = DB::table('condition_checks')->insertGetId([
            'package_id' => $pkg1Id,
            'courier_id' => $budiId,
            'is_normal' => false,
            'checklist' => json_encode([
                'packaging_damaged' => ['answer' => true, 'detail' => 'Terbukti'],
                'ice_cream_melting' => ['answer' => true, 'detail' => 'Meleleh'],
                'seal_opened' => ['answer' => true, 'detail' => 'Bocor'],
                'buyer_rejected' => ['answer' => true],
            ]),
            'notes' => 'Kemasan es beku mencair dan kemasan luar basah/bocor saat tiba di depan gerbang penerima.',
            'temperature_c' => -8.0,
            'checked_at' => $now->copy()->subMinutes(30),
        ]);

        DB::table('issue_reports')->insert([
            'check_id' => $chk1Id,
            'issue_category' => 'kemasan_bocor',
            'description' => 'Kemasan bocor saat proses transit di hub pendingin.',
            'photo_url' => null,
            'quality_inspection_note' => 'Kemasan luar rusak, suhu -8C.',
            'decision' => null, // Open exception
            'decision_reason' => null,
            'decided_by' => null,
            'decided_at' => null,
            'reported_at' => $now->copy()->subMinutes(25),
            'created_at' => $now->copy()->subMinutes(25),
            'updated_at' => $now->copy()->subMinutes(25),
        ]);

        // Package 2: AJ-FRZ-0012346 (Hendra Wijaya) - IN_DELIVERY
        $pkg2Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012346',
            'buyer_id' => $hendraBuyerId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-002',
            'package_category' => 'Frozen Food',
            'weight_kg' => 1.20,
            'scheduled_at' => $now->copy()->subHours(1),
            'scheduled_end_at' => $now->copy()->addHours(1),
            'handling_instructions' => 'Daging beku, simpan di cooler box tertutup rapat.',
            'created_at' => $now->copy()->subHours(4),
            'updated_at' => $now->copy()->subHours(4),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg2Id, 'product_id' => $p2, 'quantity' => 2]);
        $transition($pkg2Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg2Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg2Id)->update(['courier_id' => $ahmadId]);
        $transition($pkg2Id, 'COURIER_ASSIGNED', $adminId);
        $transition($pkg2Id, 'CHECKED', $ahmadId);
        $transition($pkg2Id, 'IN_DELIVERY', $ahmadId);

        // Package 3: AJ-FRZ-0012347 (Siti Rahma) - CONFIRMED (Unassigned)
        $pkg3Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012347',
            'buyer_id' => $sitiId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-003',
            'package_category' => 'Frozen Food',
            'weight_kg' => 3.00,
            'scheduled_at' => $now->copy()->addHours(3),
            'scheduled_end_at' => $now->copy()->addHours(5),
            'handling_instructions' => 'Salmon fillet beku.',
            'created_at' => $now->copy()->subHours(2),
            'updated_at' => $now->copy()->subHours(2),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg3Id, 'product_id' => $p3, 'quantity' => 4]);
        $transition($pkg3Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg3Id, 'CONFIRMED');

        // Package 4: AJ-FRZ-0012348 (Kevin Tan) - RECEIVED (Completed)
        $pkg4Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012348',
            'buyer_id' => $kevinId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-004',
            'package_category' => 'Frozen Food',
            'weight_kg' => 1.50,
            'scheduled_at' => $now->copy()->subHours(4),
            'scheduled_end_at' => $now->copy()->subHours(2),
            'handling_instructions' => 'Serahkan kepada security bila pembeli tidak ada.',
            'created_at' => $now->copy()->subHours(6),
            'updated_at' => $now->copy()->subHours(6),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg4Id, 'product_id' => $p1, 'quantity' => 2]);
        $transition($pkg4Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg4Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg4Id)->update(['courier_id' => $daniId]);
        $transition($pkg4Id, 'COURIER_ASSIGNED', $adminId);
        $transition($pkg4Id, 'CHECKED', $daniId);
        $transition($pkg4Id, 'IN_DELIVERY', $daniId);
        $transition($pkg4Id, 'RECEIVED', $daniId);

        DB::table('deliveries')->insert([
            'package_id' => $pkg4Id,
            'received_by' => 'Keluarga Kevin Tan (Keluarga Serumah)',
            'recipient_relation' => 'FAMILY',
            'photo_url' => null,
            'receipt_note' => 'Paket diterima dalam kondisi beku sempurna.',
            'delivered_at' => $now->copy()->subHours(3),
            'created_at' => $now->copy()->subHours(3),
            'updated_at' => $now->copy()->subHours(3),
        ]);

        // Package 5: AJ-FRZ-0012349 (Dewi Lestari) - RETURNED
        $pkg5Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012349',
            'buyer_id' => $dewiId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-005',
            'package_category' => 'Frozen Food',
            'weight_kg' => 2.00,
            'scheduled_at' => $now->copy()->subHours(5),
            'scheduled_end_at' => $now->copy()->subHours(3),
            'handling_instructions' => 'Produk es krim sensitif suhu.',
            'created_at' => $now->copy()->subHours(8),
            'updated_at' => $now->copy()->subHours(8),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg5Id, 'product_id' => $p4, 'quantity' => 3]);
        $transition($pkg5Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg5Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg5Id)->update(['courier_id' => $budiId]);
        $transition($pkg5Id, 'COURIER_ASSIGNED', $adminId);
        $transition($pkg5Id, 'CHECKED', $budiId);
        $transition($pkg5Id, 'ISSUE_REPORTED', $budiId);

        $chk5Id = DB::table('condition_checks')->insertGetId([
            'package_id' => $pkg5Id,
            'courier_id' => $budiId,
            'is_normal' => false,
            'checklist' => json_encode([
                'ice_cream_melting' => ['answer' => true, 'detail' => 'Suhu naik'],
            ]),
            'notes' => 'Es krim meleleh total di suhu ruang.',
            'temperature_c' => -4.0,
            'checked_at' => $now->copy()->subHours(4),
        ]);

        DB::table('issue_reports')->insert([
            'check_id' => $chk5Id,
            'issue_category' => 'suhu_naik',
            'description' => 'Suhu cooler box drop menjadi -4 derajat Celsius.',
            'photo_url' => null,
            'quality_inspection_note' => 'Produk rusak karena panas.',
            'decision' => 'RETURN',
            'decision_reason' => 'Paket rusak karena suhu drop, dikembalikan ke hub asal.',
            'decided_by' => $adminId,
            'decided_at' => $now->copy()->subHours(3)->subMinutes(30),
            'reported_at' => $now->copy()->subHours(4),
            'created_at' => $now->copy()->subHours(4),
            'updated_at' => $now->copy()->subHours(3)->subMinutes(30),
        ]);

        $transition($pkg5Id, 'RETURNED', $adminId);

        // Package 6: AJ-FRZ-0012350 (Rizky Ramadhan) - CLOSED
        $pkg6Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012350',
            'buyer_id' => $rizkyId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-006',
            'package_category' => 'Frozen Food',
            'weight_kg' => 4.00,
            'scheduled_at' => $now->copy()->subDays(1),
            'scheduled_end_at' => $now->copy()->subDays(1)->addHours(2),
            'handling_instructions' => 'Daging sapi slice beku.',
            'created_at' => $now->copy()->subDays(2),
            'updated_at' => $now->copy()->subDays(2),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg6Id, 'product_id' => $p2, 'quantity' => 5]);
        $transition($pkg6Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg6Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg6Id)->update(['courier_id' => $ahmadId]);
        $transition($pkg6Id, 'COURIER_ASSIGNED', $adminId);
        $transition($pkg6Id, 'CHECKED', $ahmadId);
        $transition($pkg6Id, 'IN_DELIVERY', $ahmadId);
        $transition($pkg6Id, 'RECEIVED', $ahmadId);

        DB::table('deliveries')->insert([
            'package_id' => $pkg6Id,
            'received_by' => 'Keluarga Ramadhan (Keluarga Serumah)',
            'recipient_relation' => 'FAMILY',
            'photo_url' => null,
            'receipt_note' => 'Selesai diantar kemarin.',
            'delivered_at' => $now->copy()->subDays(1)->addHours(1),
            'created_at' => $now->copy()->subDays(1)->addHours(1),
            'updated_at' => $now->copy()->subDays(1)->addHours(1),
        ]);

        $transition($pkg6Id, 'CLOSED', $adminId);

        // --- Courier Demo Packages (AJ-FRZ-0012351 to 0012354) ---
        // Package 7: AJ-FRZ-0012351 (Tugas Baru, belum diterima)
        $pkg7Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012351',
            'buyer_id' => $rinaId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-007',
            'package_category' => 'Frozen Food',
            'service_type' => 'INSTANT_FROZEN',
            'koli_count' => 1,
            'weight_kg' => 1.80,
            'scheduled_at' => Carbon::today()->setTime(13, 0),
            'scheduled_end_at' => Carbon::today()->setTime(15, 0),
            'handling_instructions' => 'Produk beku, serahkan langsung ke penerima. Jangan ditinggal di pagar atau pos satpam.',
            'created_at' => $now->copy()->subMinutes(2),
            'updated_at' => $now->copy()->subMinutes(2),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg7Id, 'product_id' => $p1, 'quantity' => 1]);
        $transition($pkg7Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg7Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg7Id)->update(['courier_id' => $budiId]);
        $transition($pkg7Id, 'COURIER_ASSIGNED', $adminId);

        // Package 8: AJ-FRZ-0012352 (Detail Paket / Sedang Diantar)
        $pkg8Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012352',
            'buyer_id' => $rinaId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-008',
            'package_category' => 'Frozen Food',
            'service_type' => 'REGULER',
            'koli_count' => 1,
            'weight_kg' => 1.50,
            'scheduled_at' => Carbon::today()->setTime(14, 0),
            'scheduled_end_at' => Carbon::today()->setTime(16, 0),
            'handling_instructions' => 'Produk beku, simpan di cooler box tertutup.',
            'created_at' => $now->copy()->subHours(1),
            'updated_at' => $now->copy()->subHours(1),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg8Id, 'product_id' => $p2, 'quantity' => 1]);
        $transition($pkg8Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg8Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg8Id)->update([
            'courier_id' => $budiId,
            'courier_accepted_at' => $now->copy()->subMinutes(40),
        ]);
        $transition($pkg8Id, 'COURIER_ASSIGNED', $adminId);
        $transition($pkg8Id, 'CHECKED', $budiId);
        $transition($pkg8Id, 'IN_DELIVERY', $budiId);
        DB::table('delivery_milestones')->insert([
            'package_id' => $pkg8Id,
            'milestone' => 'DEPARTED',
            'courier_id' => $budiId,
            'latitude' => -6.229746,
            'longitude' => 106.848529,
            'occurred_at' => $now->copy()->subMinutes(20),
            'created_at' => $now->copy()->subMinutes(20),
        ]);

        // Package 9: AJ-FRZ-0012353 (Bukti Serah Terima / ARRIVED)
        $pkg9Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012353',
            'buyer_id' => $rinaId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-009',
            'package_category' => 'Frozen Food',
            'service_type' => 'REGULER',
            'koli_count' => 1,
            'weight_kg' => 2.00,
            'scheduled_at' => Carbon::today()->setTime(12, 0),
            'scheduled_end_at' => Carbon::today()->setTime(14, 0),
            'handling_instructions' => 'Produk beku segera masukkan freezer.',
            'created_at' => $now->copy()->subHours(2),
            'updated_at' => $now->copy()->subHours(2),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg9Id, 'product_id' => $p3, 'quantity' => 2]);
        $transition($pkg9Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg9Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg9Id)->update([
            'courier_id' => $budiId,
            'courier_accepted_at' => $now->copy()->subHours(1),
        ]);
        $transition($pkg9Id, 'COURIER_ASSIGNED', $adminId);
        $transition($pkg9Id, 'CHECKED', $budiId);
        $transition($pkg9Id, 'IN_DELIVERY', $budiId);
        DB::table('delivery_milestones')->insert([
            'package_id' => $pkg9Id,
            'milestone' => 'DEPARTED',
            'courier_id' => $budiId,
            'occurred_at' => $now->copy()->subMinutes(40),
            'created_at' => $now->copy()->subMinutes(40),
        ]);
        DB::table('delivery_milestones')->insert([
            'package_id' => $pkg9Id,
            'milestone' => 'ARRIVED',
            'courier_id' => $budiId,
            'occurred_at' => $now->copy()->subMinutes(5),
            'created_at' => $now->copy()->subMinutes(5),
        ]);

        // Package 10: AJ-FRZ-0012354 (COURIER_ASSIGNED sudah diterima)
        $pkg10Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012354',
            'buyer_id' => $rinaId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'BOX-010',
            'package_category' => 'Frozen Food',
            'service_type' => 'REGULER',
            'koli_count' => 1,
            'weight_kg' => 1.80,
            'scheduled_at' => Carbon::today()->setTime(15, 0),
            'scheduled_end_at' => Carbon::today()->setTime(17, 0),
            'handling_instructions' => 'Produk es krim, simpan rapat.',
            'created_at' => $now->copy()->subMinutes(30),
            'updated_at' => $now->copy()->subMinutes(30),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg10Id, 'product_id' => $p4, 'quantity' => 1]);
        $transition($pkg10Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg10Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg10Id)->update([
            'courier_id' => $budiId,
            'courier_accepted_at' => $now->copy()->subMinutes(15),
        ]);
        $transition($pkg10Id, 'COURIER_ASSIGNED', $adminId);

        // Package 11: AJ-FRZ-0012361 (Client Screen: Schedule Confirmation)
        $pkg11Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012361',
            'buyer_id' => $sitiId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => null,
            'package_category' => 'Frozen Food',
            'service_type' => 'INSTANT_FROZEN',
            'koli_count' => 1,
            'weight_kg' => 1.00,
            'scheduled_at' => Carbon::today()->setTime(13, 0),
            'scheduled_end_at' => Carbon::today()->setTime(15, 0),
            'handling_instructions' => 'Suhu beku terjaga, simpan segera di freezer.',
            'created_at' => $now->copy()->subHours(2),
            'updated_at' => $now->copy()->subHours(2),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg11Id, 'product_id' => $p1, 'quantity' => 1]);
        $transition($pkg11Id, 'AWAITING_CONFIRMATION', $adminId);
        DB::table('schedule_confirmations')->insert([
            'package_id' => $pkg11Id,
            'response' => 'PENDING',
            'requested_at' => $now->copy()->subHours(1),
            'created_at' => $now->copy()->subHours(1),
            'updated_at' => $now->copy()->subHours(1),
        ]);

        // Package 12: AJ-FRZ-0012362 (Client Screen: Tracking / Sedang Diantar)
        $pkg12Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012362',
            'buyer_id' => $rinaId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'Box #04',
            'package_category' => 'Frozen Food',
            'service_type' => 'REGULER',
            'koli_count' => 1,
            'weight_kg' => 1.80,
            'scheduled_at' => Carbon::today()->setTime(13, 0),
            'scheduled_end_at' => Carbon::today()->setTime(15, 0),
            'handling_instructions' => 'Wajib cooler box & ice gel.',
            'created_at' => $now->copy()->subHours(4),
            'updated_at' => $now->copy()->subHours(4),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg12Id, 'product_id' => $p2, 'quantity' => 2]);
        $transition($pkg12Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg12Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg12Id)->update([
            'courier_id' => $budiId,
            'courier_accepted_at' => $now->copy()->subHours(2),
        ]);
        $transition($pkg12Id, 'COURIER_ASSIGNED', $adminId);
        $transition($pkg12Id, 'CHECKED', $budiId);
        $transition($pkg12Id, 'IN_DELIVERY', $budiId);

        $chk12Id = DB::table('condition_checks')->insertGetId([
            'package_id' => $pkg12Id,
            'courier_id' => $budiId,
            'is_normal' => true,
            'temperature_c' => -18.0,
            'notes' => 'Suhu optimal beku terjaga.',
            'checked_at' => $now->copy()->subMinutes(20),
            'created_at' => $now->copy()->subMinutes(20),
        ]);
        DB::table('delivery_milestones')->insert([
            'package_id' => $pkg12Id,
            'milestone' => 'DEPARTED',
            'courier_id' => $budiId,
            'occurred_at' => $now->copy()->subMinutes(35),
            'created_at' => $now->copy()->subMinutes(35),
        ]);

        // Package 13: AJ-FRZ-0012363 (Client Screen: Issue / Pengiriman Terkendala)
        $pkg13Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012363',
            'buyer_id' => $dewiId,
            'created_by' => $adminId,
            'courier_id' => null,
            'status' => 'CREATED',
            'cooler_box_code' => 'Box #02',
            'package_category' => 'Frozen Food',
            'service_type' => 'REGULER',
            'koli_count' => 1,
            'weight_kg' => 1.50,
            'scheduled_at' => Carbon::today()->setTime(11, 0),
            'scheduled_end_at' => Carbon::today()->setTime(13, 0),
            'handling_instructions' => 'Ikan beku higienis.',
            'created_at' => $now->copy()->subHours(6),
            'updated_at' => $now->copy()->subHours(6),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg13Id, 'product_id' => $p3, 'quantity' => 1]);
        $transition($pkg13Id, 'AWAITING_CONFIRMATION', $adminId);
        $transition($pkg13Id, 'CONFIRMED');
        DB::table('packages')->where('id', $pkg13Id)->update([
            'courier_id' => $ahmadId,
            'courier_accepted_at' => $now->copy()->subHours(4),
        ]);
        $transition($pkg13Id, 'COURIER_ASSIGNED', $adminId);
        $transition($pkg13Id, 'CHECKED', $ahmadId);
        $transition($pkg13Id, 'IN_DELIVERY', $ahmadId);
        $transition($pkg13Id, 'ISSUE_REPORTED', $ahmadId);
        $transition($pkg13Id, 'RETURNED', $adminId);

        $chk13Id = DB::table('condition_checks')->insertGetId([
            'package_id' => $pkg13Id,
            'courier_id' => $ahmadId,
            'is_normal' => false,
            'temperature_c' => -18.5,
            'notes' => 'Kemasan bocor saat pemeriksaan.',
            'checked_at' => $now->copy()->subHours(2),
            'created_at' => $now->copy()->subHours(2),
        ]);
        DB::table('issue_reports')->insert([
            'check_id' => $chk13Id,
            'issue_category' => 'kemasan_bocor',
            'description' => 'Kemasan robek dan bocor parah saat transit.',
            'photo_url' => 'https://storage.anteraja.id/issues/leak_01.jpg',
            'decision' => 'RETURN',
            'decision_reason' => 'Kemasan robek parah, tidak layak konsumsi sehingga diretur.',
            'quality_inspection_note' => 'Pemeriksaan Kualitas Cold Chain: Ditemukan kemasan mengalami kebocoran vakum, suhu internal -18°C tetap terjaga namun integritas kemasan rusak sehingga diputuskan retur otomatis.',
            'decided_by' => $adminId,
            'decided_at' => $now->copy()->subHour(),
            'reported_at' => $now->copy()->subHours(2),
            'created_at' => $now->copy()->subHours(2),
            'updated_at' => $now->copy()->subHour(),
        ]);

        // Package 14: AJ-FRZ-0012364 (Admin: propose a new delivery window and
        // ask the recipient to confirm it)
        $pkg14Id = DB::table('packages')->insertGetId([
            'tracking_number' => 'AJ-FRZ-0012364',
            'buyer_id' => $kevinId,
            'created_by' => $adminId,
            'courier_id' => $budiId,
            'status' => 'CREATED',
            'cooler_box_code' => null,
            'package_category' => 'Frozen Food',
            'service_type' => 'REGULER',
            'koli_count' => 1,
            'weight_kg' => 2.00,
            'scheduled_at' => Carbon::today()->setTime(13, 0),
            'scheduled_end_at' => Carbon::today()->setTime(15, 0),
            'handling_instructions' => 'Wajib simpan freezer segera.',
            'created_at' => $now->copy()->subHours(5),
            'updated_at' => $now->copy()->subHours(5),
        ]);
        DB::table('package_items')->insert(['package_id' => $pkg14Id, 'product_id' => $p1, 'quantity' => 2]);
        $transition($pkg14Id, 'COURIER_ASSIGNED', $adminId);

        // Update tracking sequence past the highest sample tracking number
        // 4. Destinations. The buyer is the customer who sends the parcel; the
        //    recipient is the family member at the door. They have no account
        //    and are reached over WhatsApp, so every demo parcel carries its own
        //    name, number, address and coordinates.
        $destinations = [
            'AJ-FRZ-0012345' => ['Ibu Sari Wulandari', '+6281200010001', 'Jl. Cihampelas No. 88, Coblong', 'Bandung, Jawa Barat', -6.9175, 107.6191],
            'AJ-FRZ-0012346' => ['Pak Hendra Wijaya Senior', '+6281200010002', 'Jl. Pandanaran No. 42, Semarang Tengah', 'Semarang, Jawa Tengah', -6.9667, 110.4167],
            'AJ-FRZ-0012347' => ['Mbak Nur Aisyah', '+6281200010003', 'Jl. Kaliurang Km 5, Sleman', 'Yogyakarta, DI Yogyakarta', -7.7956, 110.3695],
            'AJ-FRZ-0012348' => ['Keluarga Kevin Tan', '+6281200010004', 'Jl. Raya Darmo No. 12, Wonokromo', 'Surabaya, Jawa Timur', -7.2575, 112.7521],
            'AJ-FRZ-0012349' => ['Om Robert Lestari', '+6281200010005', 'Jl. Gatot Subroto No. 77, Medan Petisah', 'Medan, Sumatera Utara', 3.5952, 98.6722],
            'AJ-FRZ-0012350' => ['Keluarga Ramadhan', '+6281200010006', 'Jl. Pettarani No. 5, Panakkukang', 'Makassar, Sulawesi Selatan', -5.1477, 119.4327],
            'AJ-FRZ-0012351' => ['Ibu Sari Wulandari', '+6281200010001', 'Jl. Cihampelas No. 88, Coblong', 'Bandung, Jawa Barat', -6.9175, 107.6191],
            'AJ-FRZ-0012352' => ['Ibu Sari Wulandari', '+6281200010001', 'Jl. Cihampelas No. 88, Coblong', 'Bandung, Jawa Barat', -6.9175, 107.6191],
            'AJ-FRZ-0012353' => ['Ibu Sari Wulandari', '+6281200010001', 'Jl. Cihampelas No. 88, Coblong', 'Bandung, Jawa Barat', -6.9175, 107.6191],
            'AJ-FRZ-0012354' => ['Ibu Sari Wulandari', '+6281200010001', 'Jl. Cihampelas No. 88, Coblong', 'Bandung, Jawa Barat', -6.9175, 107.6191],
            'AJ-FRZ-0012361' => ['Mbak Nur Aisyah', '+6281200010003', 'Jl. Kaliurang Km 5, Sleman', 'Yogyakarta, DI Yogyakarta', -7.7956, 110.3695],
            'AJ-FRZ-0012362' => ['Ibu Sari Wulandari', '+6281200010001', 'Jl. Cihampelas No. 88, Coblong', 'Bandung, Jawa Barat', -6.9175, 107.6191],
            'AJ-FRZ-0012363' => ['Om Robert Lestari', '+6281200010005', 'Jl. Gatot Subroto No. 77, Medan Petisah', 'Medan, Sumatera Utara', 3.5952, 98.6722],
            'AJ-FRZ-0012364' => ['Keluarga Kevin Tan', '+6281200010004', 'Jl. Raya Darmo No. 12, Wonokromo', 'Surabaya, Jawa Timur', -7.2575, 112.7521],
        ];

        foreach ($destinations as $resi => [$name, $phone, $address, $area, $latitude, $longitude]) {
            DB::table('packages')->where('tracking_number', $resi)->update([
                'recipient_name' => $name,
                'recipient_phone' => $phone,
                'recipient_address' => $address,
                'recipient_area' => $area,
                'recipient_address_type' => 'RESIDENTIAL',
                'recipient_latitude' => $latitude,
                'recipient_longitude' => $longitude,
            ]);
        }

        DB::statement("SELECT setval('package_tracking_seq', 12370, true)");

        // Output client demo tokens and URLs
        $packages = DB::table('packages')->whereIn('id', [$pkg11Id, $pkg12Id, $pkg13Id, $pkg14Id])->get();
        if ($this->command !== null) {
            $this->command->info('--- CLIENT DEMO URLS ---');
            foreach ($packages as $pkg) {
                $this->command->info("Resi: {$pkg->tracking_number} | Token: {$pkg->client_token} | URL: ".url("/api/client/{$pkg->client_token}"));
            }
        }
    }

    /**
     * The frozen-goods catalog the customer app shops from. Keyed on the
     * product name so a re-run refreshes price, weight and art instead of
     * inserting a second row.
     */
    private function seedCatalog(): void {
        foreach ($this->catalog() as $product) {
            Product::updateOrCreate(
                ['name' => $product['name']],
                $product,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array {
        return [
            [
                'name' => 'Nugget Ayam 1 kg',
                'slug' => 'nugget-ayam-1kg',
                'category' => 'Frozen Food',
                'description' => 'Nugget ayam siap goreng, dikemas beku dengan ice gel. Praktis untuk stok lauk keluarga.',
                'price_idr' => 48000,
                'unit' => 'pack',
                'weight_kg' => 1.00,
                'image_path' => '/images/catalog/nugget-ayam.svg',
                'sort_order' => 10,
                'handling_notes' => 'Makanan Beku (Frozen Food). Suhu standar -18°C s/d -25°C. Wajib cooler box & ice gel.',
            ],
            [
                'name' => 'Daging Sapi Slice 500g',
                'slug' => 'daging-sapi-slice-500g',
                'category' => 'Daging Beku',
                'description' => 'Daging sapi slice tipis untuk shabu, yakiniku, atau tumis cepat. Disimpan pada suhu -18°C.',
                'price_idr' => 132000,
                'unit' => 'pack',
                'weight_kg' => 0.50,
                'image_path' => '/images/catalog/daging-sapi.svg',
                'sort_order' => 20,
                'handling_notes' => 'Daging segar beku. Suhu -18°C. Jaga segel kemasan vakum.',
            ],
            [
                'name' => 'Salmon Fillet Beku 250g',
                'slug' => 'salmon-fillet-250g',
                'category' => 'Seafood Beku',
                'description' => 'Fillet salmon tanpa tulang, higienis, siap panggang. Dikirim dalam rantai dingin penuh.',
                'price_idr' => 89000,
                'unit' => 'pack',
                'weight_kg' => 0.25,
                'image_path' => '/images/catalog/salmon-fillet.svg',
                'sort_order' => 30,
                'handling_notes' => 'Ikan beku higienis. Suhu -20°C.',
            ],
            [
                'name' => 'Es Krim Premium 1L',
                'slug' => 'es-krim-premium-1l',
                'category' => 'Dessert Beku',
                'description' => 'Es krim premium satu liter. Paling sensitif terhadap fluktuasi suhu, selalu dikirim paling akhir.',
                'price_idr' => 65000,
                'unit' => 'box',
                'weight_kg' => 1.00,
                'image_path' => '/images/catalog/es-krim.svg',
                'sort_order' => 40,
                'handling_notes' => 'Sensitif terhadap suhu. Suhu harus di bawah -20°C.',
            ],
            [
                'name' => 'Dimsum Ayam Mentah 500g',
                'slug' => 'dimsum-ayam-500g',
                'category' => 'Frozen Food',
                'description' => 'Dimsum ayam mentah siap kukus, 20 butir per kemasan. Tetap beku sampai pintu rumah Anda.',
                'price_idr' => 42000,
                'unit' => 'pack',
                'weight_kg' => 0.50,
                'image_path' => '/images/catalog/dimsum-ayam.svg',
                'sort_order' => 50,
                'handling_notes' => 'Produk olahan ayam beku. Suhu -18°C, jangan dicairkan ulang.',
            ],
            [
                'name' => 'Edamame Beku 500g',
                'slug' => 'edamame-beku-500g',
                'category' => 'Sayur Beku',
                'description' => 'Edamame kupas siap rebus, sumber protein nabati. Dikemas vakum dan dibekukan cepat.',
                'price_idr' => 28000,
                'unit' => 'pack',
                'weight_kg' => 0.50,
                'image_path' => '/images/catalog/edamame.svg',
                'sort_order' => 60,
                'handling_notes' => 'Sayuran beku. Suhu -18°C, jauhkan dari bahan berbau tajam.',
            ],
            [
                'name' => 'Kentang Goreng Shoestring 1kg',
                'slug' => 'kentang-shoestring-1kg',
                'category' => 'Frozen Food',
                'description' => 'Kentang shoestring potongan halus, hasil renyah tanpa perlu dicairkan sebelum digoreng.',
                'price_idr' => 39000,
                'unit' => 'pack',
                'weight_kg' => 1.00,
                'image_path' => '/images/catalog/kentang-goreng.svg',
                'sort_order' => 70,
                'handling_notes' => 'Kentang beku. Suhu -18°C, hindari benturan kemasan.',
            ],
            [
                'name' => 'Bakso Sapi Siap Masak 500g',
                'slug' => 'bakso-sapi-500g',
                'category' => 'Frozen Food',
                'description' => 'Bakso sapi kenyal siap masak, 25 butir per kemasan. Cocok untuk stok kuah mingguan.',
                'price_idr' => 55000,
                'unit' => 'pack',
                'weight_kg' => 0.50,
                'image_path' => '/images/catalog/bakso-sapi.svg',
                'sort_order' => 80,
                'handling_notes' => 'Olahan daging beku. Suhu -18°C, wajib cooler box saat pengiriman.',
            ],
        ];
    }
}
