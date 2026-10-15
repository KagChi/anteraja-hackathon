# FR-GPS-003 — Pencatatan bukti penilaian lokasi

| Properti | Nilai |
| --- | --- |
| ID | FR-GPS-003 |
| Modul | GPS — Cold Chain dan Validasi Lokasi |
| Judul | Pencatatan bukti penilaian lokasi |
| Status | Live |
| Berkas modul | docs/frd/05-coldchain-gps.md |
| Aturan bisnis | BR-011 |
| Berkas kode | app/Services/Gps/GpsValidator.php, app/Models/LocationCheck.php |

## 1. Ringkasan

Setiap penilaian lokasi disimpan sebagai baris evidence, apa pun hasilnya, termasuk
yang ditolak. Ini yang membuat pertanyaan "kenapa kurir tidak bisa menyelesaikan"
dapat dijawab dari data, bukan dari ingatan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | sistem |
| Peran staf | staff_id diisi kurir yang melakukan aksi, boleh null |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Method | GpsValidator::validate |
| Syarat | koordinat kiriman tidak null |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | ada latitude dan longitude |
| Postcondition | satu baris location_checks, atau tidak ada baris bila koordinat kosong |

## 5. Input dan validasi

Tidak ada input tambahan; data berasal dari hasil detektor dan evaluator geofence.

## 6. Proses langkah demi langkah

1. Bila latitude atau longitude null, kembalikan null tanpa menulis apa pun.
2. Jalankan detektor untuk mendapatkan tingkat dan alasan.
3. Jalankan evaluator geofence untuk mendapatkan jarak, radius, dan status di dalam.
4. Tentukan apakah harus memblokir: blokir bila ada alasan kuat, atau bila enforceGeofence true dan posisi di luar radius.
5. Bila seharusnya diblokir, cari override terakhir berstatus APPROVED untuk paket dan aksi yang sama. Bila ada, blokir dibatalkan.
6. Bila tetap diblokir dan belum ada permintaan PENDING, buka permintaan tinjauan.
7. Tulis baris location_checks berisi package_id, staff_id, action, latitude, longitude, accuracy_m, level, reasons, distance_m, radius_m, inside_geofence, applied (true bila tidak diblokir), dan override_id.
8. Bila diblokir karena alasan kuat, lempar FakeGpsSuspected yang menyertakan id permintaan tinjauan. Bila diblokir karena radius, lempar OutsideGeofence dengan jarak dan radius.
9. Bila tidak diblokir, kembalikan baris evidence.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| package_id | bigint | paket |
| staff_id | bigint atau null | kurir |
| action | string | DEPART, ARRIVE, atau POD |
| latitude, longitude | numeric | posisi saat penilaian |
| accuracy_m | numeric atau null | akurasi fix terakhir |
| level | string | CLEAN, SUSPECTED, BLOCKED |
| reasons | jsonb | daftar alasan berisi code, label, detail, severity |
| distance_m, radius_m | integer atau null | konteks geofence |
| inside_geofence | boolean atau null | hasil evaluasi |
| applied | boolean | true bila aksi benar-benar diizinkan |
| override_id | bigint atau null | id permintaan tinjauan yang membuka aksi |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Tanpa koordinat | tidak ada baris, tanpa blokir |
| Ditolak karena radius | baris tetap ditulis dengan applied false |
| Aksi diulang setelah persetujuan | baris baru ditulis dengan override_id terisi dan applied true |
| SUSPECTED | baris ditulis dengan applied true, aksi tetap jalan |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Baris evidence | location_checks |
| Permintaan tinjauan | gps_lock_requests bila diblokir |
| Exception | FakeGpsSuspected atau OutsideGeofence |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Service | Services/Gps/GpsValidator.php |
| Model | app/Models/LocationCheck.php |
| Migrasi | 0001_01_01_000028_create_gps_validation_tables.php |
| Exception | app/Exceptions/FakeGpsSuspected.php, app/Exceptions/OutsideGeofence.php |
| Test | tests/Feature/CourierGpsValidationTest.php, tests/Feature/AdminGpsConsoleTest.php test_the_gps_queue_lists_flagged_checks_and_open_requests |

## 11. Skenario uji

1. Given aksi POD di luar radius, When bukti dikirim, Then satu baris location_checks tersimpan dengan inside_geofence false dan applied false.
2. Given aksi depart tanpa koordinat, When kurir berangkat, Then tidak ada baris location_checks dan pengiriman tetap berjalan.
3. Given permintaan tinjauan yang sudah APPROVED, When kurir mengulang aksi, Then baris baru memuat override_id dan applied true.

## 12. Yang perlu dikonfirmasi

1. Berapa lama evidence lokasi perlu disimpan? Tidak ada kebijakan retensi di kode.
2. Apakah evidence boleh dibaca kurir sendiri? Saat ini hanya konsol admin yang menampilkannya.

