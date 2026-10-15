# FR-GPS-005 — Konsol validasi GPS admin

| Properti | Nilai |
| --- | --- |
| ID | FR-GPS-005 |
| Modul | GPS — Cold Chain dan Validasi Lokasi |
| Judul | Konsol validasi GPS admin |
| Status | Live |
| Berkas modul | docs/frd/05-coldchain-gps.md |
| Berkas kode | app/Http/Controllers/Admin/GpsCheckController.php |

## 1. Ringkasan

Menampilkan dua hal kepada hub: penilaian lokasi yang ditolak atau ditandai, dan
permintaan tinjauan dari kurir, lengkap dengan ringkasan harian.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | halaman /admin/gps |
| Endpoint | GET /api/admin/gps-checks |
| Nama route | api.admin.gps-checks.index |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Perilaku |
| --- | --- | --- |
| status | string | nilai resolved mengaktifkan tab selesai; nilai lain dianggap pending |
| level | string | hanya BLOCKED atau SUSPECTED yang dipakai sebagai filter tambahan |

## 6. Proses langkah demi langkah

1. Ambil location_checks dengan syarat level bukan CLEAN ATAU inside_geofence false, eager load package.buyer, courier, dan override, urut id menurun, batas 50 baris.
2. Terapkan filter level bila nilainya salah satu dari dua yang dikenali.
3. Bentuk daftar checks dengan tone ditentukan dari level, kecuali kasus di luar radius berlevel CLEAN yang diberi tone danger.
4. Ambil gps_lock_requests: tab pending mengambil status PENDING, tab resolved mengambil APPROVED dan REJECTED, urut id menurun, batas 50 baris.
5. Hitung ringkasan: jumlah permintaan PENDING, jumlah BLOCKED hari ini, jumlah SUSPECTED hari ini, jumlah penilaian di luar radius hari ini.
6. Sertakan radius per segmen dari evaluator.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| tab | string | pending atau resolved |
| checks[].id, tracking, buyer, courier | campuran | identitas penilaian |
| checks[].action, action_label | string | aksi yang dinilai |
| checks[].level, level_label, tone | string | hasil penilaian |
| checks[].reasons | array | alasan dari detektor |
| checks[].distance_m, radius_m, inside | campuran | konteks geofence |
| checks[].accuracy_m | number atau null | akurasi |
| checks[].applied | boolean | apakah aksi diizinkan |
| checks[].override_status | string atau null | status permintaan yang membuka |
| checks[].checked_at | string | ISO8601 |
| locks[].id, tracking, buyer, courier | campuran | identitas permintaan |
| locks[].action, action_label | string | aksi yang diminta |
| locks[].status, status_label, tone | string | keadaan permintaan |
| locks[].reasons, decision_reason | array dan string | alasan dan keputusan |
| locks[].decided_by, decided_at | campuran | pemutus dan waktunya |
| summary | objek | pending_requests, blocked_today, suspect_today, outside_radius_today |
| radius | objek | radius per segmen layanan |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Tanpa data | ketiga daftar kosong dan ringkasan nol |
| level tidak dikenali | dianggap tidak ada filter, bukan error |
| Lebih dari 50 baris | hanya 50 terbaru yang dikirim, tanpa paginasi |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin |
| Controller | Admin/GpsCheckController@index |
| Service | Services/Gps/GeofenceEvaluator |
| Model | LocationCheck, GpsLockRequest |
| Test | tests/Feature/AdminGpsConsoleTest.php test_the_gps_console_screens_render_for_an_admin, test_the_gps_queue_lists_flagged_checks_and_open_requests |
| UI | resources/js/pages/admin/GpsChecks.tsx |

## 11. Skenario uji

1. Given satu penilaian BLOCKED dan satu permintaan PENDING, When konsol diminta, Then checks memuat baris BLOCKED dan locks memuat permintaan PENDING.
2. Given permintaan yang sudah APPROVED, When tab pending diminta, Then permintaan itu tidak muncul.
3. Given penilaian berlevel CLEAN tetapi di luar radius, When konsol diminta, Then baris itu muncul dengan tone danger.

## 12. Yang perlu dikonfirmasi

1. Batas 50 baris tanpa paginasi dapat menyembunyikan insiden lama. Apakah perlu paginasi?
2. Penilaian berlevel SUSPECTED yang diizinkan juga masuk daftar ini; apakah perlu pemisahan antara "perlu ditinjau" dan "sekadar tercatat"?

