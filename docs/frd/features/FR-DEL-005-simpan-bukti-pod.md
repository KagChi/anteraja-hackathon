# FR-DEL-005 — Menyimpan bukti serah terima

| Properti | Nilai |
| --- | --- |
| ID | FR-DEL-005 |
| Modul | DEL — Pengantaran dan Bukti Serah Terima |
| Judul | Menyimpan bukti serah terima |
| Status | Live |
| Berkas modul | docs/frd/04-pengantaran-bukti.md |
| Aturan bisnis | BR-002, BR-003, BR-009, BR-010, BR-022 |
| Berkas kode | app/Actions/Courier/ConfirmDelivery.php, app/Http/Controllers/Courier/ProofController.php |

## 1. Ringkasan

Momen ketika paket berpindah tangan. Radius titik penerima ditegakkan di sini
foto wajib disertakan, dan paket berpindah ke RECEIVED. Aksi ini juga menjadi satu
tempat yang menangani kegagalan dengan membersihkan foto yang sudah tersimpan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize update |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | form bukti serah terima pada resources/js/pwa/screens/courier/Proof.tsx |
| Endpoint | POST /api/courier/deliveries/{tracking_number}/proof (multipart) |
| Nama route | api.courier.proof.store |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus IN_DELIVERY dan milik kurir ini |
| Postcondition | paket berstatus RECEIVED dengan baris deliveries berisi foto, nama penerima, dan hubungannya |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| received_by | string | required, min 2, max 120 |
| recipient_relation | string | required, harus nilai RecipientRelation |
| photo | file | required, image, mimes jpeg, jpg, png, webp, max 5120 KB |
| receipt_note | string | nullable, max 300 |
| latitude | numeric | nullable, antara -90 dan 90 |
| longitude | numeric | nullable, antara -180 dan 180, required_with latitude |
| device_timestamp | integer | nullable |
| gps_signals | array | nullable, maksimal 60 fix |

prepareForValidation menerima label Indonesia untuk hubungan penerima dan
mengubahnya menjadi nilai enum sebelum validasi berjalan.

## 6. Proses langkah demi langkah

1. Policy memastikan kepemilikan paket.
2. Controller memeriksa status; bila bukan IN_DELIVERY, balas 422 dengan redirect ke layar pengantaran paket itu.
3. Validasi GPS dijalankan dengan aksi POD dan enforceGeofence true sehingga radius ditegakkan.
4. Foto disimpan memakai MediaStorage::storeProof dengan folder proofs, sehingga path berbentuk proofs/{Y}/{M}/{ulid}.jpg.
5. Controller membentuk ConfirmDeliveryData dan memanggil Action.
6. Action membuka transaksi dan mengunci baris paket.
7. Bila courier_id bukan kurir ini, lempar DeliveryNotActionable.
8. Bila status RECEIVED, hapus foto yang baru diunggah dan kembalikan paket tanpa perubahan.
9. Bila status bukan IN_DELIVERY, hapus foto dan lempar DeliveryNotActionable.
10. Bila sudah ada baris deliveries untuk paket itu, hapus foto dan kembalikan paket tanpa duplikasi.
11. Buat baris deliveries berisi received_by, recipient_relation, photo_url, receipt_note, dan delivered_at sekarang.
12. Bila milestone terakhir bukan ARRIVED, buat milestone ARRIVED dengan waktu yang sama.
13. Transisi ke RECEIVED dengan aktor kurir dan deskripsi "Diserahkan ke {nama} ({hubungan})".
14. Antrekan notifikasi delivered ke pembeli.
15. Bila ada exception apa pun, hapus foto yang sudah tersimpan lalu lemparkan ulang exception itu.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Pengiriman {resi} selesai." |
| package | objek | fresh dengan statusRef dan delivery |
| package.status | string | RECEIVED |
| package.delivery.photo_url | string | path relatif pada disk local |
| package.delivery.received_by | string | nama penerima |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Di luar radius | OutsideGeofence | paket tidak berubah dan foto dibersihkan oleh catch |
| Foto lebih dari 5 MB | 422 | validasi max |
| Foto berformat selain daftar | 422 | validasi mimes |
| Nama penerima kurang dari 2 karakter | 422 | validasi min |
| GPS terdeteksi palsu | FakeGpsSuspected | permintaan tinjauan dibuat |
| Paket sudah RECEIVED | 200 | foto baru dihapus, tanpa baris deliveries kedua |
| Sudah ada baris deliveries | 200 | idempoten |
| Kegagalan di tengah transaksi | exception asli | foto dibersihkan, tidak ada file yatim |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Berkas foto | disk local, folder proofs/{Y}/{M} |
| Bukti serah terima | deliveries |
| Milestone | delivery_milestones ARRIVED bila belum ada |
| Log status | status_logs IN_DELIVERY ke RECEIVED dengan deskripsi serah terima |
| Notifikasi | notifications template delivered |
| Evidence lokasi | location_checks dengan applied true atau false |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier deliveries proof |
| Controller | Courier/ProofController@store |
| Action | Actions/Courier/ConfirmDelivery |
| FormRequest | Requests/Courier/ConfirmDeliveryRequest.php |
| DTO | DTOs/Courier/ConfirmDeliveryData.php |
| Support | MediaStorage, NotificationQueue, PackageStatusTransitioner |
| Service | Services/Gps/GpsValidator |
| Test | tests/Feature/CourierFlowTest.php test_courier_proof_create_and_store_flow, tests/Feature/CourierGpsValidationTest.php test_proof_of_delivery_outside_the_radius_is_refused, test_proof_of_delivery_inside_the_radius_completes_the_shipment, tests/Feature/AdminConsoleE2ETest.php test_the_delivery_journey_completes_end_to_end, tests/Feature/PwaShellTest.php test_proof_completes_the_delivery, test_proof_will_not_submit_without_a_recipient_name |
| UI | resources/js/pwa/screens/courier/Proof.tsx |

## 11. Skenario uji

1. Given paket IN_DELIVERY dan kurir dalam radius, When bukti dikirim lengkap, Then HTTP 200, paket RECEIVED, baris deliveries memuat foto, dan notifikasi delivered terantre.
2. Given kurir di luar radius, When bukti dikirim, Then aksi ditolak, tidak ada baris deliveries, dan berkas foto tidak tertinggal.
3. Given paket yang sudah RECEIVED, When bukti dikirim lagi, Then HTTP 200 tanpa baris deliveries tambahan.
4. Given permintaan tanpa foto, When dikirim, Then HTTP 422.

## 12. Yang perlu dikonfirmasi

1. Foto selalu disimpan dengan ekstensi jpg walau berkas sumbernya png atau webp. Apakah konversi format perlu dilakukan agar tidak menyesatkan?
2. Radius ditegakkan pada serah terima, tetapi bila pembeli meminta paket dititipkan ke sekuriti, kurir dapat diblokir. Apakah perlu jalur khusus untuk titip?
3. Bukti suhu tidak diminta pada langkah serah terima; apakah cold chain tidak perlu dicatat lagi di titik ini?

