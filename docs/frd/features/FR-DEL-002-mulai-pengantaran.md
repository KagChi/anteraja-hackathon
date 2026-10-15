# FR-DEL-002 — Mulai pengantaran dengan checklist

| Properti | Nilai |
| --- | --- |
| ID | FR-DEL-002 |
| Modul | DEL — Pengantaran dan Bukti Serah Terima |
| Judul | Mulai pengantaran dengan checklist |
| Status | Live |
| Berkas modul | docs/frd/04-pengantaran-bukti.md |
| Aturan bisnis | BR-002, BR-003, BR-004, BR-010, BR-011, BR-022 |
| Berkas kode | app/Actions/Courier/StartDelivery.php, app/Http/Controllers/Courier/StartDeliveryController.php |

## 1. Ringkasan

Gerbang rantai dingin. Kurir tidak boleh berangkat sebelum mengisi checklist fisik;
bila satu pun item checklist bernilai bermasalah, sistem menolak berangkat dan
mengarahkan ke laporan kendala. Bila normal, pemeriksaan tersimpan sebagai evidence
dan paket mulai bergerak.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize update |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tombol berangkat pada layar pengantaran |
| Endpoint | POST /api/courier/deliveries/{tracking_number}/depart |
| Nama route | api.courier.deliveries.depart |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus COURIER_ASSIGNED atau CHECKED dan milik kurir ini |
| Postcondition | paket berstatus IN_DELIVERY dengan condition check normal dan milestone DEPARTED |

## 5. Input dan validasi

| Field | Tipe | Aturan | Keterangan |
| --- | --- | --- | --- |
| checklist | array | nullable | dinormalkan ke empat kunci konfigurasi ditambah buyer_rejected |
| checklist.[key].answer | boolean | dibaca dengan filter_var | setiap item berisi answer dan detail |
| checklist.[key].detail | string | dipotong ke 100 karakter | - |
| temperature_c | numeric | nullable, antara -60 dan 40 | - |
| latitude | numeric | nullable, antara -90 dan 90 | - |
| longitude | numeric | nullable, antara -180 dan 180, required_with latitude | - |
| device_timestamp | integer | nullable | dibandingkan server untuk sinyal clock skew |
| gps_signals | array | nullable, maksimal 60 fix | tiap fix: latitude, longitude, accuracy 0 sampai 10000, timestamp |

## 6. Proses langkah demi langkah

1. Policy memastikan kepemilikan paket.
2. Controller membentuk StartDeliveryData dari request.
3. Controller memeriksa checklist: bila ada item dengan answer true, balas 422 dengan redirect ke layar laporan kendala dan penanda hasIssue, tanpa transisi apa pun.
4. Validasi GPS dijalankan dengan aksi DEPART dan enforceGeofence false, sehingga radius tidak ditegakkan tetapi detektor manipulasi tetap berjalan.
5. Action membuka transaksi, mengunci baris paket, dan memastikan courier_id sesuai.
6. Bila status tidak termasuk COURIER_ASSIGNED atau CHECKED, lempar DeliveryNotActionable.
7. Periksa ulang checklist di dalam Action sebagai pengaman kedua.
8. Buat ConditionCheck dengan is_normal true, checklist, catatan "Pemeriksaan sebelum berangkat normal", temperature_c, dan waktu pemeriksaan.
9. Bila status COURIER_ASSIGNED, transisi ke CHECKED dengan deskripsi pemeriksaan selesai.
10. Transisi ke IN_DELIVERY dengan deskripsi kurir berangkat.
11. Buat DeliveryMilestone DEPARTED beserta koordinat dan waktu.
12. Antrekan notifikasi on_the_way ke pembeli berisi nama kurir dan jendela.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Pengantaran telah dimulai." |
| package | objek | fresh dengan statusRef dan latestMilestone |
| package.status | string | IN_DELIVERY |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Satu item checklist bernilai true | 422 | hasIssue true, tanpa condition check dan tanpa transisi |
| Status di luar dua yang diizinkan | DeliveryNotActionable | belum dipetakan ke kode khusus |
| Paket milik kurir lain | 404 | PackagePolicy |
| GPS terdeteksi palsu | FakeGpsSuspected | aksi ditolak, permintaan tinjauan dibuat |
| Tanpa koordinat sama sekali | 200 | GPS best effort, pengiriman tetap berjalan |
| Paket sudah IN_DELIVERY | DeliveryNotActionable | mencegah keberangkatan ganda |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Pemeriksaan kondisi | condition_checks |
| Log status | status_logs ke CHECKED bila perlu, lalu ke IN_DELIVERY |
| Milestone | delivery_milestones DEPARTED dengan koordinat |
| Notifikasi | notifications template on_the_way untuk pembeli |
| Evidence lokasi | location_checks bila koordinat dikirim |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier deliveries |
| Controller | Courier/StartDeliveryController |
| Action | Actions/Courier/StartDelivery |
| FormRequest | Requests/Courier/StartDeliveryRequest.php |
| DTO | DTOs/Courier/StartDeliveryData.php |
| Support | ChecklistNormalizer, NotificationQueue, PackageStatusTransitioner |
| Service | Services/Gps/GpsValidator |
| Test | tests/Feature/CourierFlowTest.php test_courier_depart_flow, tests/Feature/CourierNativeScreensTest.php test_departing_from_the_app_posts_the_action_with_the_fix, tests/Feature/CourierGpsValidationTest.php test_a_mocked_fix_blocks_the_depart_and_opens_a_review |
| UI | app/NativeComponents/Courier/Delivery.php |

## 11. Skenario uji

1. Given checklist seluruhnya normal dan paket COURIER_ASSIGNED, When depart dipanggil, Then HTTP 200, paket IN_DELIVERY, satu condition check normal, dan milestone DEPARTED tercatat.
2. Given satu item checklist bernilai true, When depart dipanggil, Then HTTP 422 hasIssue true, tidak ada condition check baru, dan status tidak berubah.
3. Given jendela fix berakurasi nol, When depart dipanggil, Then aksi ditolak dan satu permintaan tinjauan PENDING dibuat.
4. Given paket tanpa koordinat, When depart dipanggil, Then pengiriman tetap berhasil tanpa baris location_checks.

## 12. Yang perlu dikonfirmasi

1. Suhu tidak wajib diisi, padahal ini pemeriksaan cold chain. Apakah suhu seharusnya wajib?
2. Checklist bernilai true hanya mengarahkan kurir ke laporan kendala; tidak ada larangan tegas berangkat bila kurir mengabaikan arahan. Apakah perlu penegakan tambahan di server?

