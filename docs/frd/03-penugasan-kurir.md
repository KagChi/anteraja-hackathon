# FRD Modul ASG — Penugasan Kurir dan Siklus Tugas

Modul ini menggerakkan paket dari status CONFIRMED ke lapangan: menugaskan kurir
menampilkan papan tugas, dan menangani terima atau tolak.

Aturan bisnis terkait: BR-002, BR-003, BR-004, BR-012, BR-020.

---

### FR-ASG-001 — Penugasan kurir

- Deskripsi: Sistem HARUS menugaskan satu kurir aktif pada paket berstatus CONFIRMED yang belum punya kurir, memindahkan paket ke COURIER_ASSIGNED, dan memberi tahu kurir tentang tugasnya.
- Aktor & Otorisasi: admin hub.
- Trigger: POST /api/admin/shipments/{tracking_number}/assign dari modal detail shipment.
- Precondition: paket berstatus CONFIRMED dan courier_id masih null.
- Input & Validasi: courier_id wajib integer dan harus merujuk baris staff berperan COURIER yang is_active true (AssignCourierRequest).
- Proses / Business Rules: transaksi dengan lockForUpdate pada baris paket (BR-004). Bila status bukan CONFIRMED atau courier_id sudah terisi, lempar PackageNotAssignable. Bila staf yang dipilih bukan kurir aktif, lempar PackageNotAssignable dengan pesan khusus. Transisi ke COURIER_ASSIGNED dijalankan dengan atribut courier_id dan courier_accepted_at null (BR-002 dan BR-003). Notifikasi task_assigned diantrekan untuk kurir berisi resi, jendela, instruksi penanganan, dan ringkasan item.
- Output / Response: HTTP 200 dengan message yang menyebut nama kurir dan resi, serta objek package yang sudah memuat courier dan statusRef.
- Error & Edge Cases: courier_id bukan kurir aktif atau bukan staf menghasilkan 422 dari FormRequest; paket berstatus CONFIRMED tetapi sudah punya kurir menghasilkan penolakan PackageNotAssignable; resi tidak dikenal menghasilkan 404.
- Side Effects: kolom courier_id dan courier_accepted_at paket berubah; baris status_logs baru; satu baris notifications QUEUED template task_assigned.
- Postcondition: paket muncul di papan tugas kurir tersebut sebagai tugas baru.
- Traceability: routes/api.php, Admin/ShipmentAssignmentController, Actions/Packages/AssignCourier, Requests/Admin/AssignCourierRequest, Support/NotificationQueue, tests/Feature/AdminShipmentTest.php test_admin_can_assign_courier, resources/js/components/CourierPickerDialog.tsx.
- Acceptance Criteria:
  - Given paket CONFIRMED tanpa kurir dan satu kurir aktif, When assign dipanggil, Then HTTP 200, paket berstatus COURIER_ASSIGNED, dan notifikasi task_assigned terantre.
  - Given paket COURIER_ASSIGNED yang sudah punya kurir, When assign dipanggil lagi, Then permintaan ditolak dan courier_id tidak berubah.
- Status: Live

---

### FR-ASG-002 — Papan tugas kurir

- Deskripsi: Sistem HARUS menampilkan tugas milik kurir yang login, dipisah menjadi tugas baru (belum diterima) dan tugas aktif, lengkap dengan usia penugasan dan status waktu jendela.
- Aktor & Otorisasi: kurir, hanya tugas dengan courier_id sama dengan id kurir yang login.
- Trigger: GET /api/courier/tasks saat tab pertama aplikasi kurir dibuka.
- Precondition: token kurir valid.
- Input & Validasi: tanpa input.
- Proses / Business Rules: status yang disertakan adalah COURIER_ASSIGNED, CHECKED, IN_DELIVERY, dan ISSUE_REPORTED. Waktu penugasan diambil dari baris status_logs terakhir dengan to_status COURIER_ASSIGNED melalui subquery, bukan dari kolom paket. Urutan berdasarkan scheduled_at menaik dengan NULLS LAST. Status waktu jendela dihitung ScheduleTiming::forWindow terhadap scheduled_end_at sehingga bernilai ON_TIME, URGENT, atau LATE. Tugas dianggap baru bila status COURIER_ASSIGNED dan courier_accepted_at masih null. Setiap baris membawa id tampilan berformat KRR- diikuti id log penugasan.
- Output / Response: HTTP 200 dengan newTasks, activeTasks, dan courier (name dan status aktif atau nonaktif). Setiap tugas memuat tracking, service, serviceKey, window, windowStatus, windowStatusTone, recipient, phone, placeType, address, instruction, metrics (jumlah koli, kategori, berat), buyerCoordinates, status, statusLabel, stage, stageLabel, hasIssue, isNew.
- Error & Edge Cases: kurir tanpa tugas menerima dua array kosong. Koordinat pembeli null dikirim apa adanya sehingga layar tidak dapat menampilkan tombol rute berbasis koordinat. Paket yang sudah RECEIVED atau RETURNED tidak muncul lagi.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Courier/TaskController@index, Queries/CourierTaskQuery, Enums/ScheduleTiming, Support/RelativeTime, Support/DisplayTime, tests/Feature/CourierFlowTest.php test_courier_tasks_index_splits_new_and_active_tasks, resources/js/pwa/screens/courier/Tasks.tsx.
- Acceptance Criteria:
  - Given kurir dengan satu tugas COURIER_ASSIGNED yang belum diterima dan satu tugas IN_DELIVERY, When papan tugas diminta, Then newTasks memuat satu baris dan activeTasks memuat satu baris.
  - Given kurir lain membuka papan tugasnya, When daftar diminta, Then paket kurir pertama tidak muncul.
- Status: Live

---

### FR-ASG-003 — Menerima tugas

- Deskripsi: Sistem HARUS mencatat waktu kurir menerima tugas, dan HARUS bersifat idempoten bila kurir menekan tombol dua kali.
- Aktor & Otorisasi: kurir yang ditugaskan pada paket itu (Gate::authorize update dengan PackagePolicy).
- Trigger: POST /api/courier/tasks/{tracking_number}/accept.
- Precondition: paket berstatus COURIER_ASSIGNED dan courier_id sama dengan kurir yang login.
- Input & Validasi: tanpa body.
- Proses / Business Rules: transaksi dengan lockForUpdate. Bila courier_id bukan kurir ini atau status bukan COURIER_ASSIGNED, lempar TaskNotActionable::notAssignable. Bila courier_accepted_at sudah terisi, kembalikan paket tanpa perubahan. Bila belum, isi courier_accepted_at dengan waktu sekarang. Tidak ada transisi status pada langkah ini.
- Output / Response: HTTP 200 dengan message "Tugas berhasil diterima." dan objek package yang memuat statusRef.
- Error & Edge Cases: paket milik kurir lain menghasilkan 404 lewat PackagePolicy karena denials dikembalikan sebagai not found. Paket yang sudah IN_DELIVERY menghasilkan TaskNotActionable.
- Side Effects: kolom courier_accepted_at paket terisi. Papan tugas berpindah dari daftar tugas baru ke tugas aktif.
- Postcondition: kurir dapat membuka layar pengantaran; tanpa langkah ini layar pengantaran menolak dengan pesan agar tugas diterima lebih dulu.
- Traceability: routes/api.php, Courier/AcceptTaskController, Actions/Courier/AcceptTask, app/Policies/PackagePolicy.php, tests/Feature/CourierFlowTest.php test_courier_accept_task_flow, resources/js/pwa/screens/courier/Tasks.tsx.
- Acceptance Criteria:
  - Given tugas COURIER_ASSIGNED milik kurir, When accept dipanggil, Then HTTP 200 dan courier_accepted_at terisi.
  - Given tugas yang sudah diterima, When accept dipanggil lagi, Then HTTP 200 tanpa perubahan nilai courier_accepted_at.
- Status: Live

---

### FR-ASG-004 — Menolak tugas

- Deskripsi: Sistem HARUS mengembalikan paket ke status CONFIRMED, melepas kurir dari paket, dan memberi tahu seluruh admin aktif beserta alasannya.
- Aktor & Otorisasi: kurir yang ditugaskan pada paket itu.
- Trigger: POST /api/courier/tasks/{tracking_number}/reject dari tombol tolak pada papan tugas.
- Precondition: paket berstatus COURIER_ASSIGNED, courier_id sama dengan kurir ini, dan courier_accepted_at masih null.
- Input & Validasi: reason opsional string maksimal 200 karakter.
- Proses / Business Rules: transaksi dengan lockForUpdate. Bila bukan paket kurir ini atau status bukan COURIER_ASSIGNED, TaskNotActionable::notAssignable. Bila tugas sudah diterima, TaskNotActionable::alreadyAccepted sehingga kurir tidak dapat menolak setelah menerima. Transisi ke CONFIRMED dengan alasan yang dikirim atau teks bawaan "Kurir menolak penugasan", lalu kolom courier_id dan courier_accepted_at dikosongkan. Notifikasi task_rejected diantrekan untuk setiap admin aktif.
- Output / Response: HTTP 200 dengan message "Tugas {resi} telah ditolak." dan objek package yang memuat statusRef.
- Error & Edge Cases: menolak setelah accept menghasilkan penolakan; alasan lebih dari 200 karakter menghasilkan 422; alasan kosong tetap diterima dan memakai teks bawaan.
- Side Effects: baris status_logs baru dari COURIER_ASSIGNED ke CONFIRMED; courier_id menjadi null; baris notifications QUEUED template task_rejected untuk tiap admin.
- Postcondition: paket kembali ke antrean hub siap ditugaskan ke kurir lain.
- Traceability: routes/api.php, Courier/RejectTaskController, Actions/Courier/RejectTask, Requests/Courier/RejectTaskRequest, Support/NotificationQueue::queueTaskRejected, tests/Feature/CourierFlowTest.php test_courier_reject_task_flow, resources/js/pwa/screens/courier/Tasks.tsx.
- Acceptance Criteria:
  - Given tugas belum diterima, When kurir menolak dengan alasan, Then HTTP 200, paket berstatus CONFIRMED, courier_id null, dan admin menerima notifikasi task_rejected.
  - Given tugas yang sudah diterima, When kurir menolak, Then permintaan ditolak TaskNotActionable dan status tidak berubah.
- Status: Live

---

### FR-ASG-005 — Jarak dan ETA tujuan

- Deskripsi: Sistem HARUS menghitung jarak lurus dan estimasi waktu tempuh dari posisi kurir ke titik pembeli.
- Aktor & Otorisasi: kurir yang berhak melihat paket (Gate view dengan PackagePolicy).
- Trigger: GET /api/courier/tasks/{tracking_number}/distance dengan koordinat saat ini.
- Precondition: paket ada dan kurir berhak melihatnya.
- Input & Validasi: lat wajib numerik antara -90 dan 90; lng wajib numerik antara -180 dan 180 (DistanceRequest).
- Proses / Business Rules: jarak dihitung GeoDistance::haversine dan ETA dihitung GeoDistance::etaMinutes dari jarak itu.
- Output / Response: HTTP 200 dengan distance_km dan eta_minutes. Bila pembeli tidak punya koordinat, sistem mengembalikan HTTP 204 tanpa body.
- Error & Edge Cases: koordinat di luar rentang menghasilkan 422. Paket tanpa koordinat tujuan menghasilkan 204, bukan error, dan layar kurir harus menanganinya sebagai ketiadaan data.
- Side Effects: tidak ada penulisan. Panggilan ini tidak membuat baris location_checks karena bukan aksi GPS yang divalidasi.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Courier/TaskDistanceController, Support/GeoDistance, Requests/Courier/DistanceRequest, tests/Feature/CourierFlowTest.php test_courier_task_distance_endpoint, tests/Unit/CourierUnitTest.php test_geodistance_haversine dan test_geodistance_eta_minutes, resources/js/pwa/screens/courier/Tasks.tsx.
- Acceptance Criteria:
  - Given pembeli dengan koordinat dan kurir berjarak beberapa kilometer, When jarak diminta, Then HTTP 200 dengan distance_km dan eta_minutes terisi.
  - Given pembeli tanpa koordinat, When jarak diminta, Then HTTP 204 tanpa body.
- Status: Live

