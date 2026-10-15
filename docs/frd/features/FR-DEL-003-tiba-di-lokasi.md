# FR-DEL-003 — Menandai tiba di lokasi

| Properti | Nilai |
| --- | --- |
| ID | FR-DEL-003 |
| Modul | DEL — Pengantaran dan Bukti Serah Terima |
| Judul | Menandai tiba di lokasi |
| Status | Live |
| Berkas modul | docs/frd/04-pengantaran-bukti.md |
| Aturan bisnis | BR-004, BR-010, BR-011 |
| Berkas kode | app/Actions/Courier/MarkArrived.php |

## 1. Ringkasan

Merekam bahwa kurir sudah sampai di alamat tujuan. Radius sengaja tidak ditegakkan
di titik ini karena kurir sering harus berjalan kaki dari tempat kendaraan
berhenti; yang dicatat hanya jarak sebagai bukti pendukung.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize update |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tombol tiba di lokasi pada layar pengantaran |
| Endpoint | POST /api/courier/deliveries/{tracking_number}/arrive |
| Nama route | api.courier.deliveries.arrive |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus IN_DELIVERY dengan milestone terakhir DEPARTED |
| Postcondition | milestone ARRIVED tercatat dengan koordinat dan tahap pengantaran menjadi ARRIVED |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| latitude | numeric | nullable, antara -90 dan 90 |
| longitude | numeric | nullable, antara -180 dan 180, required_with latitude |
| device_timestamp | integer | nullable |
| gps_signals | array | nullable, maksimal 60 fix |

## 6. Proses langkah demi langkah

1. Policy memastikan kepemilikan paket.
2. Validasi GPS dijalankan dengan aksi ARRIVE dan enforceGeofence false, sehingga hanya jarak yang dicatat.
3. Action membuka transaksi dan mengunci baris paket.
4. Bila courier_id bukan kurir ini, lempar DeliveryNotActionable.
5. Bila status bukan IN_DELIVERY, lempar DeliveryNotActionable dengan pesan paket tidak dalam proses pengantaran.
6. Ambil milestone terakhir. Bila sudah ARRIVED, kembalikan paket tanpa perubahan (idempoten). Bila bukan DEPARTED, lempar DeliveryNotActionable dengan pesan kurir belum memulai perjalanan.
7. Buat DeliveryMilestone ARRIVED beserta courier_id, koordinat, dan waktu sekarang.
8. Antrekan notifikasi courier_arrived ke pembeli berisi nama kurir.
9. Kembalikan paket.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Status berhasil diperbarui: Tiba di lokasi." |
| package | objek | fresh dengan statusRef dan latestMilestone |
| package.status | string | tetap IN_DELIVERY |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Menandai tiba dua kali | 200 | idempoten, milestone tidak bertambah |
| Tiba sebelum berangkat | DeliveryNotActionable | milestone terakhir bukan DEPARTED |
| Status bukan IN_DELIVERY | DeliveryNotActionable | - |
| Paket milik kurir lain | 404 | PackagePolicy |
| Tanpa koordinat | 200 | milestone tetap dibuat tanpa koordinat |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Milestone | delivery_milestones ARRIVED |
| Notifikasi | notifications template courier_arrived |
| Evidence lokasi | location_checks bila koordinat dikirim |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier deliveries |
| Controller | Courier/MarkArrivedController |
| Action | Actions/Courier/MarkArrived |
| FormRequest | Requests/Courier/MarkArrivedRequest.php |
| Enum | Enums/DeliveryMilestoneEnum |
| Test | tests/Feature/CourierFlowTest.php test_courier_arrive_flow, tests/Feature/PwaShellTest.php test_arriving_at_the_address_is_recorded, test_arriving_ships_the_collected_fix_window |
| UI | resources/js/pwa/screens/courier/Delivery.tsx |

## 11. Skenario uji

1. Given paket IN_DELIVERY dengan milestone DEPARTED, When arrive dikirim dengan koordinat, Then HTTP 200, milestone ARRIVED memuat koordinat, dan pembeli menerima notifikasi courier_arrived.
2. Given arrive dikirim dua kali, When permintaan kedua diproses, Then HTTP 200 tanpa milestone baru.
3. Given paket IN_DELIVERY tanpa milestone DEPARTED, When arrive dikirim, Then permintaan ditolak dan tidak ada milestone baru.

## 12. Yang perlu dikonfirmasi

1. Karena radius tidak ditegakkan, kurir dapat menandai tiba dari lokasi mana pun. Apakah jarak yang dicatat sudah cukup sebagai bukti?
2. Apakah perlu pengingat otomatis bila kurir sudah ARRIVED tetapi belum mengirim bukti dalam rentang waktu tertentu?

