# FR-PKG-001 — Membuat paket dari hub

| Properti | Nilai |
| --- | --- |
| ID | FR-PKG-001 |
| Modul | PKG — Paket dan FSM Status |
| Judul | Membuat paket dari hub |
| Status | Live |
| Berkas modul | docs/frd/02-paket-fsm.md |
| Aturan bisnis | BR-001, BR-002, BR-003, BR-005, BR-014 |
| Berkas kode | app/Actions/Packages/CreatePackage.php, app/Http/Requests/Admin/StoreShipmentRequest.php |

## 1. Ringkasan

Titik masuk paket dari sisi hub. Satu pemanggilan membuat baris paket dan baris
itemnya saja: paket berhenti di status CREATED, tanpa konfirmasi jadwal maupun
pesan ke penerima. Kurir ditugaskan lebih dulu oleh admin, baru konfirmasi
tanggal terima diminta oleh kurir atau admin (FR-PKG-009). Paket sengaja dibuat
berstatus CREATED lebih dulu supaya status_logs tetap lengkap dan diaudit.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |
| Aktor transisi | staf yang sedang login, ditulis ke app.staff_id |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | form pada resources/js/pages/admin/Shipment.tsx |
| Endpoint | POST /api/admin/shipments |
| Nama route | api.admin.shipments.store |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | minimal satu produk ada; jadwal tidak di masa lalu menurut zona Jakarta |
| Postcondition | paket berstatus AWAITING_CONFIRMATION, menunggu jawaban pembeli, dan muncul di antrean shipment |

## 5. Input dan validasi

| Field | Tipe | Aturan | Catatan |
| --- | --- | --- | --- |
| buyer_id | integer | nullable, required_without buyer, exists buyers.id | salah satu jalur pembeli wajib ada |
| buyer.name | string | required_with buyer, max 120 | hanya bila membuat pembeli baru |
| buyer.phone | string | required_with buyer, max 20 | dinormalkan E.164 |
| buyer.address | string | required_with buyer | - |
| buyer.area | string | nullable, max 120 | - |
| buyer.address_type | string | nullable, in RESIDENTIAL, COMMERCIAL | - |
| buyer.latitude | numeric | nullable, antara -90 dan 90 | - |
| buyer.longitude | numeric | nullable, antara -180 dan 180 | - |
| items | array | required, min 1 | - |
| items.*.product_id | integer | required, exists products.id, distinct | produk tidak boleh dobel dalam satu permintaan |
| items.*.quantity | integer | required, min 1, max 999 | - |
| scheduled_date | date | required, format Y-m-d, after_or_equal hari ini Jakarta | - |
| window_start | string | required, format H:i | - |
| window_end | string | required, format H:i, after window_start | - |
| weight_kg | numeric | required, min 0,01, max 999,99 | - |
| cooler_box_code | string | nullable, max 30 | - |
| package_category | string | nullable, max 50 | bawaan "Frozen Food" |
| service_type | string | nullable, in REGULER, INSTANT_FROZEN | bawaan REGULER |
| koli_count | integer | nullable, min 1 | bawaan 1 |
| handling_instructions | string | nullable, max 1000 | - |

## 6. Proses langkah demi langkah

1. Bila buyer_id kosong, cari pembeli berdasarkan nomor E.164 lalu berdasarkan bentuk asli. Bila tidak ada, buat buyers baru berisi name, phone ternormalisasi, address, area, address_type (bawaan RESIDENTIAL), dan koordinat bila dikirim.
2. Parsing jadwal di zona Asia/Jakarta memakai format Y-m-d H:i untuk start dan end, lalu konversi ke UTC. Bila parsing gagal, start jatuh ke waktu sekarang UTC dan end dua jam setelahnya.
3. Buat baris packages dengan buyer_id, created_by id admin, courier_id null, status CREATED, service_type, koli_count, weight_kg, jadwal, cooler_box_code, package_category, dan handling_instructions.
4. Refresh model agar tracking_number dari default PostgreSQL terisi.
5. Buat satu baris package_items per item.
6. Kembalikan paket berstatus CREATED. Tidak ada schedule_confirmations, tidak ada
   transisi status, dan tidak ada notifikasi pada langkah ini.

## 7. Output

HTTP 201, bentuk respons:

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Shipment {resi} dibuat. Tugaskan kurir, lalu minta konfirmasi jadwal." |
| package | objek | model Package hasil load buyer, items.product, statusRef |
| package.tracking_number | string | bentuk AJ-FRZ- diikuti 7 digit |
| package.status | string | CREATED |
| package.scheduled_at | string | UTC |
| package.source | string | ADMIN |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Tidak ada buyer_id dan tidak ada objek buyer | 422 | required_without |
| Tanggal lampau | 422 | errors.scheduled_date |
| window_end tidak setelah window_start | 422 | errors.window_end |
| product_id duplikat | 422 | aturan distinct |
| product_id tidak ada di tabel | 422 | aturan exists |
| Berat di luar rentang | 422 | - |
| Nomor pembeli sudah terdaftar | 201 | memakai baris pembeli yang ada, bukan membuat duplikat (perilaku yang diuji) |
| Transisi ditolak database | 500 | InvalidStatusTransition, seharusnya tidak terjadi karena matriks mengizinkan |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Baris paket | packages |
| Baris item | package_items |
| Baris log status | status_logs INSERT dengan from_status null dan to_status CREATED, changed_by id admin |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin shipments |
| Controller | Admin/ShipmentController@store |
| Action | Actions/Packages/CreatePackage |
| FormRequest | Requests/Admin/StoreShipmentRequest.php |
| Support | PackageStatusTransitioner, NotificationQueue, PhoneFormatter, DisplayTime |
| Model | Package, PackageItem, ScheduleConfirmation, Buyer |
| Migrasi | 0001_01_01_000008 (packages), 0001_01_01_000010 (schedule_confirmations), 0001_01_01_000016 (guard status) |
| Test | tests/Feature/AdminShipmentTest.php test_admin_can_create_new_shipment, tests/Feature/ClientApiAuthTest.php test_create_package_reuses_existing_buyer_by_phone |
| UI | resources/js/pages/admin/Shipment.tsx |

## 11. Skenario uji

1. Given pembeli baru, satu produk, dan jadwal besok, When shipment dibuat, Then HTTP 201, resi berawalan AJ-FRZ-, status CREATED, dan tidak ada baris schedule_confirmations maupun notifikasi.
2. Given scheduled_date kemarin, When shipment dibuat, Then HTTP 422 pada errors.scheduled_date dan tidak ada baris packages baru.
3. Given nomor pembeli yang sudah punya akun, When shipment dibuat dengan objek buyer baru, Then baris buyers tidak bertambah dan paket memakai pembeli yang ada.
4. Given dua item dengan product_id yang sama, When shipment dibuat, Then HTTP 422 karena aturan distinct.

## 12. Yang perlu dikonfirmasi

1. Berat paket diisi manual dan tidak dihitung dari item, berbeda dengan checkout pelanggan yang menghitung otomatis. Apakah admin perlu hitungan otomatis juga?
2. Kapasitas harian tidak diperiksa saat pembuatan paket, sehingga paket boleh melebihi kapasitas armada yang dipakai KPI.
3. Tidak ada pemeriksaan kuota koli terhadap jumlah item.

