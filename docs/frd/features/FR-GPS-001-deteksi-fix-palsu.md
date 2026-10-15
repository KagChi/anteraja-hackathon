# FR-GPS-001 — Deteksi manipulasi sinyal GPS

| Properti | Nilai |
| --- | --- |
| ID | FR-GPS-001 |
| Modul | GPS — Cold Chain dan Validasi Lokasi |
| Judul | Deteksi manipulasi sinyal GPS |
| Status | Live |
| Berkas modul | docs/frd/05-coldchain-gps.md |
| Aturan bisnis | BR-010, BR-011 |
| Berkas kode | app/Services/Gps/FakeGpsDetector.php |

## 1. Ringkasan

Aplikasi kurir mengirim jendela fix GPS bersama aksi. Server menilai jendela itu
memakai tujuh pemeriksaan dan menetapkan satu dari tiga tingkat kepercayaan.
Klien tidak pernah menentukan tingkat ini.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | sistem |
| Titik masuk | GpsValidator::validate dipanggil dari depart, arrive, dan proof |
| Klien | hanya menyediakan data, tidak menilai |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | POST depart, POST arrive, POST proof |
| Pemanggil | Services/Gps/GpsValidator.php method validate |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | minimal ada koordinat utama; jendela fix boleh kosong |
| Postcondition | objek GpsAssessment berisi level, reasons, accuracyM, ringkasan jendela, dan impliedSpeedKmh |

## 5. Input dan validasi

| Sumber | Aturan |
| --- | --- |
| gps_signals | maksimal 60 fix, tiap fix berisi latitude, longitude, accuracy 0 sampai 10000, timestamp integer opsional |
| device_timestamp | integer opsional, dibandingkan jam server |
| latitude dan longitude utama | nullable, longitude wajib bila latitude ada |

## 6. Proses langkah demi langkah

Pemeriksaan kuat (menghasilkan BLOCKED):

1. accuracy_invalid: akurasi fix terakhir di bawah 1 meter, ciri lokasi simulasi.
2. frozen_fix: minimal 8 fix, rentang waktu minimal 45 detik, hanya satu titik berbeda, dan akurasi terakhir maksimal 2 meter.
3. impossible_travel: dibandingkan titik yang sudah tersimpan server pada 900 detik terakhir; kecepatan di atas 200 km per jam dengan jeda minimal 60 detik.

Pemeriksaan lemah (hanya menandai SUSPECTED):

4. client_teleport: dua fix berurutan dengan jeda minimal 5 detik, jarak minimal 200 meter, dan kecepatan di atas 150 km per jam.
5. stale_fix: fix terakhir berumur lebih dari 600 detik.
6. clock_skew: selisih device_timestamp dan jam server lebih dari 900 detik.
7. low_diversity: rentang minimal 45 detik dengan kurang dari 3 titik berbeda, akurasi maksimal 5 meter, dan bukan kasus frozen_fix.

Penentuan tingkat: BLOCKED bila ada satu alasan ber-severity strong; SUSPECTED bila
hanya ada alasan lemah; CLEAN bila tidak ada alasan sama sekali. Jendela fix yang
kosong langsung dianggap CLEAN.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| level | string | CLEAN, SUSPECTED, atau BLOCKED |
| reasons | array | tiap alasan berisi code, label, detail, severity |
| accuracyM | float atau null | akurasi fix terakhir |
| fixWindow | objek atau null | count, span_seconds, distinct_points |
| impliedSpeedKmh | float atau null | kecepatan dari pemeriksaan perpindahan mustahil |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Jendela fix kosong | CLEAN, bukan SUSPECTED, agar perangkat tanpa kunci satelit tidak dihukum |
| Kurang dari 8 fix | frozen_fix tidak aktif |
| Fix tanpa timestamp | pemeriksaan rentang waktu memakai 0 detik sehingga frozen_fix dan teleport tidak aktif |
| Akurasi null | pemeriksaan yang membutuhkan akurasi dilewati |
| Titik tanpa riwayat location_checks | impossible_travel tidak aktif |

## 9. Efek samping

Tidak menulis apa pun; pencatatan ada di FR-GPS-003.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Service | Services/Gps/FakeGpsDetector.php |
| Validator | Services/Gps/GpsValidator.php |
| DTO | DTOs/Courier/GpsSignalData.php, DTOs/Courier/GpsAssessment.php, DTOs/Courier/GpsFixData.php |
| Enum | Enums/GpsLevel.php |
| FormRequest | Requests/Courier/Concerns/ValidatesGpsSignals.php |
| Test | tests/Feature/GpsDetectionTest.php (6 test), tests/Feature/CourierGpsValidationTest.php test_a_mocked_fix_blocks_the_depart_and_opens_a_review |

## 11. Skenario uji

1. Given jendela fix normal saat berkendara, When dinilai, Then level CLEAN.
2. Given fix dengan akurasi 0,5 meter, When dinilai, Then level BLOCKED dengan alasan accuracy_invalid.
3. Given delapan fix identik selama 45 detik dengan akurasi 1 meter, When dinilai, Then level BLOCKED dengan alasan frozen_fix.
4. Given dua fix berjeda 5 detik dengan jarak 1 km, When dinilai, Then level BLOCKED dengan alasan impossible_travel.

## 12. Yang perlu dikonfirmasi

1. Ambang batas ini berasal dari prototipe capstone. Apakah sudah divalidasi dengan data lapangan nyata?
2. Apakah ambang sebaiknya dapat diatur dari konsol, seperti radius geofence?

