# FR-PKG-006 — Penjagaan FSM oleh database

| Properti | Nilai |
| --- | --- |
| ID | FR-PKG-006 |
| Modul | PKG — Paket dan FSM Status |
| Judul | Penjagaan FSM oleh database |
| Status | Live |
| Berkas modul | docs/frd/02-paket-fsm.md |
| Aturan bisnis | BR-001, BR-002 |
| Berkas kode | app/Support/PackageStatusTransitioner.php |

## 1. Ringkasan

Matriks transisi hidup di tabel database, bukan di kode PHP. Trigger menolak setiap
perubahan status yang tidak ada di matriks, dan aplikasi menerjemahkan penolakan itu
menjadi exception yang dapat dibaca.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | sistem, tidak bergantung peran |
| Cakupan | berlaku untuk seluruh penulis, termasuk psql manual |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Trigger database | BEFORE UPDATE OF status ON packages, fungsi packages_validate_status |
| Titik masuk aplikasi | Support/PackageStatusTransitioner::transition |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | baris paket ada dan status tujuan adalah nilai sah dari package_statuses |
| Postcondition | status berubah hanya bila pasangan from dan to ada di package_status_transitions |

## 5. Input dan validasi

| Parameter transition | Tipe | Keterangan |
| --- | --- | --- |
| package | Package | model tanpa syarat status awal |
| to | PackageStatus | status tujuan |
| actor | Staff atau null | penulis aktor ke sesi PostgreSQL |
| attributes | array atau string | atribut tambahan yang ingin diubah bersamaan, misalnya courier_id |

## 6. Proses langkah demi langkah

1. Bila level transaksi saat itu 0, transitioner membuka DB::transaction sendiri; bila sudah ada transaksi aktif, ia memakai transaksi yang ada agar transisi menyatu dengan perubahan lain.
2. Catat status asal dari model.
3. Jalankan SELECT set_config dengan app.staff_id berisi id aktor, atau string kosong bila aktor null.
4. Set kolom status dan setiap atribut tambahan pada model.
5. Simpan model. Di titik ini trigger BEFORE UPDATE memvalidasi pasangan status.
6. Bila QueryException dengan SQLSTATE 23514 atau pesan memuat "Invalid package status transition", lemparkan InvalidStatusTransition yang menyimpan fromStatus, toStatus, dan exception sebelumnya.
7. Refresh model dan kembalikannya.

## 7. Output

| Keluaran | Tipe | Keterangan |
| --- | --- | --- |
| return | Package | model yang sudah di-refresh |
| efek samping | baris status_logs | ditulis trigger AFTER INSERT OR UPDATE |

## 8. Error dan edge case

| Kasus | Keluaran |
| --- | --- |
| Pasangan status tidak ada di matriks | InvalidStatusTransition |
| Status tujuan bukan nilai yang ada di package_statuses | error foreign key, bukan InvalidStatusTransition |
| Transisi ke status yang sama | ditolak constraint ck_transitions_not_self pada matriks; update yang tidak mengubah status tidak memicu validasi |
| Update status langsung dari luar transitioner | tetap ditolak trigger, termasuk dari psql |
| Perubahan atribut lain tanpa mengubah status | trigger validasi tidak berjalan karena memakai BEFORE UPDATE OF status |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Baris log status | status_logs dengan from_status, to_status, changed_by, changed_at |
| Pesan exception | log Laravel bila tidak ditangani |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Migrasi matriks | database/migrations/0001_01_01_000004_create_reference_tables.php |
| Migrasi trigger | database/migrations/0001_01_01_000016_add_package_status_guard.php |
| Seed matriks | database/seeders/ReferenceDataSeeder.php |
| Tambahan transisi | database/migrations/0001_01_01_000020_add_courier_status_transitions.php |
| Kode | app/Support/PackageStatusTransitioner.php, app/Exceptions/InvalidStatusTransition.php |
| Test | tests/Feature/AdminConsoleE2ETest.php, tests/Feature/FullEndpointVerificationTest.php |

## 11. Skenario uji

1. Given paket CREATED, When aplikasi mencoba memindahkannya langsung ke RECEIVED, Then transisi ditolak dan status tetap CREATED.
2. Given paket CONFIRMED, When AssignCourier memindahkan ke COURIER_ASSIGNED, Then transisi berhasil dan satu baris status_logs muncul.
3. Given update yang hanya mengubah scheduled_at tanpa mengubah status, When disimpan, Then trigger validasi tidak berjalan dan tidak ada baris log baru.

## 12. Yang perlu dikonfirmasi

1. Menambah status atau transisi baru adalah pekerjaan migrasi plus seed. Siapa yang berwenang menyetujui perubahan matriks?
2. Apakah transisi ke status yang sama perlu dipersilakan (misalnya idempotensi ulang), mengingat saat ini dibiarkan tanpa validasi?

