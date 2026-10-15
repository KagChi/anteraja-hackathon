# FRD Modul GPS — Cold Chain dan Validasi Lokasi

Modul ini adalah satu pintu penilaian lokasi: detektor manipulasi sinyal, geofence
per segmen layanan, pencatatan bukti, dan alur tinjauan hub.

Aturan bisnis terkait: BR-004, BR-009, BR-010, BR-011, BR-022, BR-026.

---

### FR-GPS-001 — Deteksi manipulasi sinyal GPS

- Deskripsi: Sistem HARUS menilai jendela fix GPS yang dikirim kurir dan menetapkan tingkat CLEAN, SUSPECTED, atau BLOCKED berdasarkan tujuh pemeriksaan.
- Aktor & Otorisasi: sistem, dijalankan server saat aksi kurir membawa koordinat. Klien tidak pernah menentukan tingkat.
- Trigger: pemanggilan GpsValidator::validate dari depart, arrive, atau proof of delivery.
- Precondition: minimal satu fix tersedia; bila jendela fix kosong, penilaian dianggap CLEAN.
- Input & Validasi: gps_signals maksimal 60 fix; tiap fix berisi latitude antara -90 dan 90, longitude antara -180 dan 180, accuracy 0 sampai 10000, dan timestamp integer opsional; device_timestamp integer opsional.
- Proses / Business Rules: pemeriksaan kuat yang memblokir adalah akurasi di bawah 1 meter (ciri lokasi simulasi), koordinat beku (minimal 8 fix identik selama minimal 45 detik dengan akurasi maksimal 2 meter), dan perpindahan mustahil terhadap titik yang sudah tersimpan server (kecepatan di atas 200 km per jam dengan jeda minimal 60 detik dalam rentang 900 detik terakhir). Pemeriksaan lemah yang hanya menandai adalah jam perangkat melenceng lebih dari 900 detik, fix basi lebih dari 600 detik, lompatan posisi di atas 150 km per jam, dan variasi sinyal rendah (kurang dari 3 titik berbeda dalam 45 detik dengan akurasi maksimal 5 meter). Tingkat menjadi BLOCKED bila ada satu alasan ber-severity strong, SUSPECTED bila hanya ada alasan lemah, dan CLEAN bila tidak ada alasan.
- Output / Response: objek GpsAssessment berisi level, daftar reasons (code, label, detail, severity), accuracyM, ringkasan jendela fix, dan impliedSpeedKmh.
- Error & Edge Cases: tanpa koordinat sama sekali, validator mengembalikan null tanpa penilaian dan tanpa blokir (BR-011). Jendela fix kosong dianggap CLEAN, bukan SUSPECTED, supaya perangkat yang belum mengunci satelit tidak menghukum kurir.
- Side Effects: tidak langsung menulis; pencatatan dilakukan FR-GPS-003.
- Postcondition: tingkat hasil penilaian menentukan langkah berikutnya: boleh lanjut, ditandai, atau diblokir.
- Traceability: Services/Gps/FakeGpsDetector.php, Services/Gps/GpsValidator.php, DTOs/Courier/GpsSignalData, DTOs/Courier/GpsAssessment, Enums/GpsLevel, Requests/Courier/Concerns/ValidatesGpsSignals.php, tests/Feature/GpsDetectionTest.php, tests/Feature/CourierGpsValidationTest.php test_a_mocked_fix_blocks_the_depart_and_opens_a_review.
- Acceptance Criteria:
  - Given jendela fix normal saat berkendara, When kurir berangkat, Then tingkat CLEAN dan aksi lanjut tanpa permintaan tinjauan.
  - Given fix dengan akurasi 0,5 meter, When kurir berangkat, Then tingkat BLOCKED, aksi ditolak FakeGpsSuspected, dan satu permintaan tinjauan PENDING dibuat.
- Status: Live

---

### FR-GPS-002 — Penilaian geofence per segmen layanan

- Deskripsi: Sistem HARUS menghitung jarak server ke titik penerima dan memutuskan apakah kurir berada di dalam radius segmen layanan paket tersebut.
- Aktor & Otorisasi: sistem.
- Trigger: pemanggilan GeofenceEvaluator::evaluate dari GpsValidator dan dari konteks layar pengantaran.
- Precondition: pembeli memiliki koordinat latitude dan longitude.
- Input & Validasi: koordinat pembeli numeric(9,6), koordinat kurir dari request, dan service_type paket.
- Proses / Business Rules: jarak dihitung haversine lalu dibulatkan ke meter. Radius diambil dari cache 60 detik berisi pemetaan service_type ke radius_m dari tabel geofence_policies, dengan nilai default REGULER 100 meter dan INSTANT_FROZEN 30 meter (BR-009). Bila pembeli tidak punya koordinat, hasilnya distance_m null, radius_m tetap terisi, dan inside null sehingga geofence tidak memblokir apa pun. Karena klien tidak pernah menjadi sumber kebenaran, nilai jarak yang ditampilkan aplikasi hanya bersifat informasi.
- Output / Response: array distance_m, radius_m, dan inside.
- Error & Edge Cases: koordinat pembeli null menghasilkan inside null, bukan false, sehingga tidak ada blokir palsu. Radius di bawah 10 atau di atas 1000 ditolak constraint ck_geofence_policies_radius.
- Side Effects: cache geofence.radii diisi atau dibaca.
- Postcondition: hasil inside dipakai bersama keputusan enforceGeofence untuk menentukan blokir.
- Traceability: Services/Gps/GeofenceEvaluator.php, app/Models/GeofencePolicy.php, Support/GeoDistance.php, database/migrations/0001_01_01_000028_create_gps_validation_tables.php, database/seeders/ReferenceDataSeeder.php, tests/Feature/GpsDetectionTest.php test_the_geofence_uses_the_service_radius.
- Acceptance Criteria:
  - Given paket INSTANT_FROZEN dan kurir berjarak 20 meter dari penerima, When geofence dinilai, Then inside true karena radius segmen itu 30 meter.
  - Given pembeli tanpa koordinat, When geofence dinilai, Then distance_m null dan inside null serta tidak ada blokir yang diberikan.
- Status: Live

---

### FR-GPS-003 — Pencatatan bukti penilaian lokasi

- Deskripsi: Sistem HARUS menulis satu baris bukti untuk setiap penilaian lokasi, apa pun hasilnya, termasuk yang ditolak.
- Aktor & Otorisasi: sistem.
- Trigger: setiap pemanggilan GpsValidator::validate yang menghasilkan penilaian.
- Precondition: koordinat kiriman tidak null.
- Input & Validasi: tidak ada input tambahan; data berasal dari hasil detektor dan geofence.
- Proses / Business Rules: baris location_checks menyimpan package_id, staff_id, action (DEPART, ARRIVE, POD), latitude, longitude, accuracy_m, level, reasons jsonb, distance_m, radius_m, inside_geofence, applied, dan override_id bila ada persetujuan. Kolom applied bernilai true hanya bila aksi benar-benar diizinkan. Baris ini adalah evidence yang tidak dapat diubah klien.
- Output / Response: objek LocationCheck, atau null bila tidak ada koordinat.
- Error & Edge Cases: aksi yang memblokir tetap menghasilkan baris dengan applied false sehingga pertanyaan "kenapa kurir tidak bisa menyelesaikan" dapat dijawab dari data.
- Side Effects: satu baris location_checks.
- Postcondition: konsol GPS dapat menampilkan penilaian bermasalah.
- Traceability: Services/Gps/GpsValidator.php, app/Models/LocationCheck.php, database/migrations/0001_01_01_000028_create_gps_validation_tables.php, tests/Feature/CourierGpsValidationTest.php, tests/Feature/AdminGpsConsoleTest.php test_the_gps_queue_lists_flagged_checks_and_open_requests.
- Acceptance Criteria:
  - Given aksi POD di luar radius, When kurir mengirim bukti, Then satu baris location_checks tersimpan dengan inside_geofence false dan applied false.
  - Given aksi depart tanpa koordinat, When kurir berangkat, Then tidak ada baris location_checks dan pengiriman tetap berjalan.
- Status: Live

---

### FR-GPS-004 — Permintaan tinjauan GPS oleh kurir

- Deskripsi: Sistem HARUS menyediakan jalan keluar bagi kurir yang diblokir validasi lokasi, dengan satu permintaan terbuka per paket dan aksi.
- Aktor & Otorisasi: kurir pemilik paket.
- Trigger: POST /api/courier/deliveries/{tracking_number}/gps-review, dan juga dibuat otomatis oleh validator saat memblokir.
- Precondition: ada penilaian terakhir untuk paket dan aksi tersebut.
- Input & Validasi: action wajib salah satu Enum GpsAction (DEPART, ARRIVE, POD); note opsional maksimal 300 karakter.
- Proses / Business Rules: permintaan dibuat dengan level BLOCKED, alasan dari penilaian terakhir ditambah alasan outside_geofence bila posisi di luar radius, dan status PENDING. Bila sudah ada permintaan PENDING untuk paket dan aksi yang sama, permintaan itu yang dikembalikan sehingga aksi bersifat idempoten. Index unik uq_gps_lock_requests_open menjaga hal yang sama di level database.
- Output / Response: HTTP 201 dengan message "Permintaan tinjauan GPS dikirim ke hub." dan data berisi id, action, action_label, status, status_label, reasons, created_at.
- Error & Edge Cases: action di luar enum menghasilkan 422. Paket milik kurir lain menghasilkan 404. Tanpa riwayat penilaian, permintaan tetap dibuat dengan level SUSPECTED bawaan dan daftar alasan kosong.
- Side Effects: satu baris gps_lock_requests PENDING, atau tidak ada baris baru bila sudah ada yang terbuka.
- Postcondition: kurir menunggu putusan hub; selama PENDING, aksi yang sama masih diblokir.
- Traceability: routes/api.php, Courier/GpsReviewController, Services/Gps/GpsValidator::requestReview dan openLockRequest, Enums/GpsLockStatus, tests/Feature/CourierGpsValidationTest.php test_a_courier_can_ask_the_hub_to_review_and_the_request_is_idempotent, resources/js/pwa/screens/courier/Delivery.tsx.
- Acceptance Criteria:
  - Given kurir yang baru diblokir pada aksi POD, When minta tinjauan dikirim, Then HTTP 201 dengan satu baris gps_lock_requests berstatus PENDING.
  - Given permintaan PENDING yang sama dikirim dua kali, When permintaan kedua dikirim, Then hanya satu baris PENDING yang ada di database.
- Status: Live

---

### FR-GPS-005 — Konsol validasi GPS admin

- Deskripsi: Sistem HARUS menampilkan penilaian lokasi yang ditolak atau ditandai, antrean permintaan tinjauan, dan ringkasan harian bagi hub.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/gps-checks dengan parameter status dan level.
- Precondition: tidak ada.
- Input & Validasi: status bernilai resolved mengaktifkan tab riwayat, selain itu dianggap pending; level opsional hanya menerima BLOCKED atau SUSPECTED.
- Proses / Business Rules: daftar checks memuat baris location_checks dengan level bukan CLEAN atau inside_geofence false, dibatasi 50 baris terbaru, dan disaring lagi bila level dikirim. Daftar locks memuat permintaan PENDING pada tab pending dan APPROVED atau REJECTED pada tab resolved, juga dibatasi 50. Ringkasan menghitung permintaan PENDING, jumlah BLOCKED hari ini, jumlah SUSPECTED hari ini, dan jumlah penilaian di luar radius hari ini. Nilai tone baris ditentukan dari level, kecuali kasus di luar radius dengan level CLEAN yang diberi tone danger.
- Output / Response: HTTP 200 dengan tab, checks, locks, summary, dan radius per segmen.
- Error & Edge Cases: tanpa data, ketiga daftar kosong dan ringkasan berisi nol. Level di luar dua nilai yang diizinkan diabaikan sebagai filter, bukan error.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Admin/GpsCheckController@index, Services/Gps/GeofenceEvaluator, app/Models/LocationCheck.php, app/Models/GpsLockRequest.php, tests/Feature/AdminGpsConsoleTest.php test_the_gps_console_screens_render_for_an_admin dan test_the_gps_queue_lists_flagged_checks_and_open_requests, resources/js/pages/admin/GpsChecks.tsx.
- Acceptance Criteria:
  - Given satu penilaian BLOCKED dan satu permintaan PENDING, When konsol diminta, Then checks memuat baris BLOCKED dan locks memuat permintaan PENDING.
  - Given permintaan yang sudah diputuskan, When tab pending diminta, Then permintaan itu tidak muncul di daftar pending.
- Status: Live

---

### FR-GPS-006 — Putusan tinjauan GPS

- Deskripsi: Sistem HARUS mengizinkan hub menyetujui atau menolak permintaan tinjauan dengan alasan tertulis, dan persetujuan itu yang membuka retry kurir.
- Aktor & Otorisasi: admin hub.
- Trigger: POST /api/admin/gps-checks/{id}/decision.
- Precondition: baris gps_lock_requests berstatus PENDING.
- Input & Validasi: decision wajib APPROVED atau REJECTED; decision_reason wajib 10 sampai 1000 karakter dengan pesan khusus "Alasan keputusan minimal 10 karakter.".
- Proses / Business Rules: bila status bukan PENDING, kembalikan 422 dengan code GPS_LOCK_ALREADY_DECIDED sehingga keputusan tidak dapat ditimpa (BR-026). Kolom status, decided_by, decision_reason, dan decided_at diisi bersamaan, sesuai constraint ck_gps_lock_requests_decision yang mewajibkan decided_at null hanya saat status PENDING.
- Output / Response: HTTP 200 dengan message yang menyebut resi paket dan hasil keputusan, serta lock berisi id, status, status_label, decision_reason, decided_at.
- Error & Edge Cases: alasan kurang dari 10 karakter menghasilkan 422. Permintaan yang sudah diputuskan menghasilkan 422 dengan code khusus. Id yang tidak ada menghasilkan 404.
- Side Effects: baris gps_lock_requests berubah status dan menyimpan alasan. Setelah APPROVED, validator pada percobaan berikutnya menemukan override sehingga aksi yang sama diizinkan dan location_checks baru mencatat override_id.
- Postcondition: kurir dapat mengulang aksi yang sebelumnya diblokir, dan insiden awalnya tetap tercatat.
- Traceability: routes/api.php, Admin/GpsCheckController@decide, Requests/Admin/GpsDecisionRequest, Services/Gps/GpsValidator::approvedOverride, Enums/GpsLockStatus, tests/Feature/AdminGpsConsoleTest.php test_approving_a_request_unlocks_the_shipment dan test_a_decision_needs_a_reason, tests/Feature/CourierGpsValidationTest.php test_the_hub_approval_unlocks_the_retry, resources/js/pages/admin/GpsChecks.tsx.
- Acceptance Criteria:
  - Given permintaan PENDING, When admin menyetujui dengan alasan sah, Then HTTP 200, status APPROVED, dan kurir dapat mengulang aksi itu.
  - Given permintaan yang sudah APPROVED, When admin memutuskan lagi, Then HTTP 422 GPS_LOCK_ALREADY_DECIDED dan keputusan lama tidak berubah.
- Status: Live

---

### FR-GPS-007 — Pengaturan radius geofence

- Deskripsi: Sistem HARUS menampilkan dan mengubah radius toleransi per segmen layanan, dengan batas nilai per segmen dan pencatatan siapa yang mengubahnya.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/geofence dan PUT /api/admin/geofence dari halaman /admin/geofence.
- Precondition: tidak ada untuk baca; untuk tulis, minimal satu segmen dikirim.
- Input & Validasi: policies wajib array minimal satu; policies.INSTANT_FROZEN wajib integer antara 10 dan 100; policies.REGULER wajib integer antara 25 dan 500.
- Proses / Business Rules: hanya segmen yang dikirim yang diubah, memakai updateOrCreate dan mengisi updated_by dengan id admin. Setelah penyimpanan, cache radius dibuang agar berlaku segera, bukan menunggu TTL 60 detik. Respons memuat daftar segmen beserta label, SLA, deskripsi, batas minimum dan maksimum, langkah, radius saat ini, radius bawaan, dan waktu perubahan terakhir.
- Output / Response: HTTP 200 dengan message "Radius GPS berhasil diperbarui." dan segments serta updated_at.
- Error & Edge Cases: nilai di luar batas segmen menghasilkan 422 (test khusus memverifikasi ini). Nilai yang masih diizinkan constraint database tetapi di luar batas UI, misalnya 10 untuk REGULER, tetap ditolak oleh FormRequest.
- Side Effects: baris geofence_policies diperbarui; cache geofence.radii dibuang.
- Postcondition: penilaian lokasi berikutnya memakai radius baru untuk segmen tersebut.
- Traceability: routes/api.php, Admin/GeofenceController@index dan update, Requests/Admin/UpdateGeofenceRequest, app/Models/GeofencePolicy.php, Services/Gps/GeofenceEvaluator::forget, tests/Feature/AdminGpsConsoleTest.php test_the_console_lists_the_radius_per_segment dan test_a_radius_outside_the_segment_bounds_is_rejected, resources/js/pages/admin/Geofence.tsx.
- Acceptance Criteria:
  - Given radius REGULER saat ini 100 meter, When admin menyetel 150, Then HTTP 200 dan permintaan berikutnya membaca 150 karena cache dibuang.
  - Given nilai 10 untuk REGULER yang di bawah batas UI, When disimpan, Then HTTP 422 dan nilai lama tidak berubah.
- Status: Live

