# FR-DEL-001 — Layar pengantaran kurir

| Properti | Nilai |
| --- | --- |
| ID | FR-DEL-001 |
| Modul | DEL — Pengantaran dan Bukti Serah Terima |
| Judul | Layar pengantaran kurir |
| Status | Live |
| Berkas modul | docs/frd/04-pengantaran-bukti.md |
| Aturan bisnis | BR-010, BR-020 |
| Berkas kode | app/Http/Controllers/Courier/DeliveryController.php |

## 1. Ringkasan

Menyatukan seluruh informasi yang dibutuhkan kurir di depan pintu penerima: tujuan
kontak, tautan peta, instruksi penanganan, tombol aksi yang sedang boleh dipakai
daftar checklist, dan konteks validasi lokasi.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize view |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | resources/js/pwa/screens/courier/Delivery.tsx |
| Endpoint | GET /api/courier/deliveries/{tracking_number} |
| Nama route | api.courier.deliveries.show |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus salah satu dari COURIER_ASSIGNED, CHECKED, IN_DELIVERY, ISSUE_REPORTED, RECEIVED, RETURNED, CLOSED |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| tracking_number | string path | dicari firstOrFail |

## 6. Proses langkah demi langkah

1. Ambil paket dengan eager load buyer, statusRef, latestMilestone, dan conditionChecks beserta issueReport.
2. Policy memastikan kurir berhak; paket orang lain menghasilkan 404.
3. Bila status di luar daftar tujuh status di atas, batalkan dengan 404 bertuliskan "Paket tidak ditemukan." supaya keberadaan paket pada tahap awal tidak bocor.
4. Bila status COURIER_ASSIGNED dan courier_accepted_at null, balas 422 dengan pesan agar tugas diterima lebih dulu dan redirect ke /courier/tasks.
5. Bila pembeli punya koordinat, bentuk tautan Google Maps dari koordinat; bila tidak, dari alamat yang di-encode.
6. Bentuk tautan tel dari nomor pembeli yang sudah dinormalkan ke E.164.
7. Hitung izin aksi: canDepart untuk COURIER_ASSIGNED atau CHECKED; canArrive untuk IN_DELIVERY yang tahapnya bukan ARRIVED; canProof untuk IN_DELIVERY; canReportIssue untuk tiga status pra-serah-terima dan belum ada kendala tanpa keputusan.
8. Susun konteks GPS: tujuan, radius segmen layanan, verdict terakhir, jarak, akurasi, alasan, dan status permintaan tinjauan yang masih menunggu.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| delivery.tracking | string | resi |
| delivery.service | string | label "Paket Beku" |
| delivery.serviceType | string | label layanan dari enum |
| delivery.recipient | string | nama penerima |
| delivery.phone | string | nomor tampilan |
| delivery.phoneHref | string | bentuk tel: dengan E.164 |
| delivery.address | string | alamat lengkap |
| delivery.instruction | string atau null | instruksi penanganan |
| delivery.mapLabel | string | teks tautan peta |
| delivery.mapHref | string | tautan Google Maps |
| delivery.status, statusLabel | string | status dan labelnya |
| delivery.stage, stageLabel | string | PENDING, DEPARTED, atau ARRIVED |
| delivery.canDepart, canArrive, canProof, canReportIssue | boolean | izin aksi |
| delivery.hasOpenIssue | boolean | ada kendala tanpa keputusan |
| delivery.checklistItems | objek | empat kunci checklist dari konfigurasi |
| delivery.gps | objek | destination, radius_m, distance_m, inside, level, level_label, level_tone, accuracy_m, action, action_label, reasons, checked_at, can_request_review, requested_action, lock |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Paket milik kurir lain | 404 | PackagePolicy |
| Status di luar daftar | 404 | disamarkan sebagai tidak ditemukan |
| Belum menerima tugas | 422 | redirect ke daftar tugas |
| Pembeli tanpa koordinat | 200 | tautan peta memakai alamat |
| Kendala sudah ada | 200 | canReportIssue false dan hasOpenIssue true |
| Sudah ARRIVED | 200 | canArrive false |

## 9. Efek samping

Tidak ada penulisan. Verdict GPS terakhir dibaca dari location_checks dan permintaan
tinjauan dari gps_lock_requests.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier deliveries |
| Controller | Courier/DeliveryController@show |
| Service | Services/Gps/GpsValidator::latestCheck, Services/Gps/GeofenceEvaluator |
| Konfigurasi | config/coldchain.php checklist_items |
| Support | PhoneFormatter, DisplayTime |
| Test | tests/Feature/CourierFlowTest.php test_courier_delivery_show_and_guards, tests/Feature/CourierGpsValidationTest.php test_the_delivery_screen_reports_the_geofence_context, tests/Feature/PwaShellTest.php test_the_delivery_screen_offers_the_route_and_the_handover, test_the_delivery_screen_shows_the_location_validation |
| UI | resources/js/pwa/screens/courier/Delivery.tsx |

## 11. Skenario uji

1. Given paket IN_DELIVERY setelah berangkat, When layar diminta, Then canProof true dan stage DEPARTED.
2. Given paket CONFIRMED, When layar diminta, Then HTTP 404.
3. Given paket COURIER_ASSIGNED yang belum diterima, When layar diminta, Then HTTP 422 dengan redirect ke daftar tugas.
4. Given penilaian GPS terakhir berlevel BLOCKED dan belum ada permintaan tinjauan, When layar diminta, Then gps.can_request_review true dan reasons memuat alasan.

## 12. Yang perlu dikonfirmasi

1. Penyamaran status sebagai 404 dapat menyulitkan diagnosis kurir ketika paketnya sedang dalam status lain. Apakah perlu pesan yang lebih informatif untuk kasus ini?
2. Instruksi penanganan berupa teks bebas tanpa panjang terbatas selain di form admin (1000 karakter). Apakah cukup?

