# FR-GPS-002 — Penilaian geofence per segmen layanan

| Properti | Nilai |
| --- | --- |
| ID | FR-GPS-002 |
| Modul | GPS — Cold Chain dan Validasi Lokasi |
| Judul | Penilaian geofence per segmen layanan |
| Status | Live |
| Berkas modul | docs/frd/05-coldchain-gps.md |
| Aturan bisnis | BR-009, BR-011 |
| Berkas kode | app/Services/Gps/GeofenceEvaluator.php |

## 1. Ringkasan

Menentukan apakah posisi kurir berada di dalam radius yang ditoleransi hub untuk
segmen layanan paket itu. Nilai ini dipakai bersama keputusan enforceGeofence
sehingga hanya aksi tertentu yang bisa diblokir.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | sistem |
| Pemakai | GpsValidator untuk verdict, DeliveryController untuk konteks layar |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Method | GeofenceEvaluator::evaluate dan radiusFor |
| Titik | setiap aksi kurir yang membawa koordinat, dan pembukaan layar pengantaran |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada; pembeli tanpa koordinat tetap ditangani |
| Postcondition | array distance_m, radius_m, inside |

## 5. Input dan validasi

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| buyer | Buyer atau null | sumber koordinat tujuan |
| latitude, longitude | float | posisi kurir |
| serviceType | ServiceType atau null | penentu radius, null jatuh ke REGULER |

## 6. Proses langkah demi langkah

1. Ambil radius dari cache geofence.radii dengan masa hidup 60 detik; bila cache kosong, baca seluruh baris geofence_policies.
2. Bila pembeli null atau koordinatnya tidak lengkap, kembalikan distance_m null, radius_m terisi, dan inside null.
3. Hitung jarak haversine dari posisi kurir ke koordinat pembeli, kalikan 1000, dan bulatkan ke meter.
4. inside bernilai true bila jarak kurang dari atau sama dengan radius.
5. Kembalikan ketiga nilai.

Radius bawaan bila baris kebijakan tidak ada: REGULER 100 meter, INSTANT_FROZEN 30 meter.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| distance_m | integer atau null | jarak dalam meter |
| radius_m | integer | radius segmen |
| inside | boolean atau null | null bila tidak ada tujuan untuk dibandingkan |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Pembeli tanpa koordinat | inside null sehingga TIDAK ADA blokir palsu |
| service_type null | memakai radius REGULER |
| Radius diubah admin | cache dibuang oleh GeofenceController sehingga berlaku segera |
| Radius di luar batas 10 sampai 1000 | ditolak constraint database |

## 9. Efek samping

Mengisi atau membaca cache geofence.radii.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Service | Services/Gps/GeofenceEvaluator.php |
| Model | app/Models/GeofencePolicy.php termasuk konstanta SEGMENTS dan DEFAULT_RADIUS_M |
| Support | Support/GeoDistance::haversine |
| Migrasi | 0001_01_01_000028_create_gps_validation_tables.php |
| Seed | database/seeders/ReferenceDataSeeder.php bagian geofence |
| Test | tests/Feature/GpsDetectionTest.php test_the_geofence_uses_the_service_radius, tests/Feature/CourierGpsValidationTest.php test_the_delivery_screen_reports_the_geofence_context |

## 11. Skenario uji

1. Given paket INSTANT_FROZEN dan jarak 20 meter, When dinilai, Then inside true karena radius 30 meter.
2. Given paket REGULER dan jarak 150 meter, When dinilai, Then inside false.
3. Given pembeli tanpa koordinat, When dinilai, Then distance_m null dan inside null.

## 12. Yang perlu dikonfirmasi

1. Jarak memakai garis lurus, bukan rute jalan. Apakah toleransi radius sudah memperhitungkan hal itu?
2. Cache 60 detik berarti perubahan radius dapat terlambat satu menit pada proses lain di cluster. Apakah itu dapat diterima?

