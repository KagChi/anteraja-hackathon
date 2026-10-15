# FR-ASG-005 — Jarak dan ETA tujuan

| Properti | Nilai |
| --- | --- |
| ID | FR-ASG-005 |
| Modul | ASG — Penugasan Kurir dan Siklus Tugas |
| Judul | Jarak dan ETA tujuan |
| Status | Live |
| Berkas modul | docs/frd/03-penugasan-kurir.md |
| Aturan bisnis | BR-020 |
| Berkas kode | app/Http/Controllers/Courier/TaskDistanceController.php, app/Support/GeoDistance.php |

## 1. Ringkasan

Memberi kurir gambaran jarak dan waktu tempuh ke titik penerima memakai posisi
perangkat saat itu. Perhitungan memakai rumus haversine, bukan layanan peta pihak
ketiga, sehingga hasilnya jarak lurus.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir yang berhak melihat paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize view |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | pembukaan detail tugas pada papan tugas |
| Endpoint | GET /api/courier/tasks/{tracking_number}/distance |
| Nama route | api.courier.tasks.distance |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket ada dan boleh dilihat kurir |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| lat | numeric | required, antara -90 dan 90 |
| lng | numeric | required, antara -180 dan 180 |

## 6. Proses langkah demi langkah

1. PackagePolicy memastikan kurir berhak melihat paket.
2. Ambil pembeli; bila koordinatnya null, balas HTTP 204 tanpa body.
3. Hitung jarak memakai GeoDistance::haversine dari koordinat kurir ke koordinat pembeli.
4. Hitung ETA memakai GeoDistance::etaMinutes dari jarak itu.
5. Balas distance_km dan eta_minutes.

Penting: endpoint ini bukan aksi yang divalidasi, sehingga tidak membuat baris
location_checks dan tidak pernah memblokir kurir.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| distance_km | number | jarak lurus dalam kilometer |
| eta_minutes | integer | estimasi menit |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Koordinat di luar rentang | 422 | validasi between |
| lat atau lng kosong | 422 | required |
| Pembeli tanpa koordinat | 204 | tanpa body |
| Paket milik kurir lain | 404 | PackagePolicy |

## 9. Efek samping

Tidak ada penulisan dan tidak ada baris evidence lokasi.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier tasks |
| Controller | Courier/TaskDistanceController |
| Support | Support/GeoDistance |
| FormRequest | Requests/Courier/DistanceRequest.php |
| Test | tests/Feature/CourierFlowTest.php test_courier_task_distance_endpoint, tests/Unit/CourierUnitTest.php test_geodistance_haversine, test_geodistance_eta_minutes |
| UI | resources/js/pwa/screens/courier/Tasks.tsx yang mengisi placeholder jarak |

## 11. Skenario uji

1. Given pembeli dengan koordinat, When jarak diminta, Then HTTP 200 dengan distance_km dan eta_minutes terisi.
2. Given pembeli tanpa koordinat, When jarak diminta, Then HTTP 204 tanpa body dan layar tetap berjalan.
3. Given lat di luar rentang, When jarak diminta, Then HTTP 422.

## 12. Yang perlu dikonfirmasi

1. ETA memakai asumsi kecepatan tetap dari GeoDistance; apakah perlu memakai estimasi rute nyata dari penyedia peta?
2. Karena bukan aksi validasi, pengukuran jarak tidak tercatat sebagai bukti. Apakah hub perlu melihat jarak awal kurir ke tujuan?

