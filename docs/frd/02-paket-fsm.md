# FRD Modul PKG — Paket dan FSM Status

Modul ini adalah jantung sistem: pembuatan paket, antrean kerja hub, dan penjagaan
state machine yang dipegang database.

Aturan bisnis terkait: BR-001, BR-002, BR-003, BR-005, BR-014.

---

### FR-PKG-001 — Membuat paket dari hub

- Deskripsi: Sistem HARUS membuat paket baru berstatus CREATED dan menyimpan item produknya. Konfirmasi jadwal tidak dibuat di sini: kurir ditugaskan lebih dulu, lalu kurir atau admin meminta konfirmasi tanggal terima (FR-PKG-009).
- Aktor & Otorisasi: admin hub melalui middleware staff.role:ADMIN.
- Trigger: POST /api/admin/shipments dari form admin/Shipment.
- Precondition: pembeli dipilih dari daftar atau dikirim sebagai data baru; minimal satu produk tersedia; jadwal tidak di masa lalu.
- Input & Validasi: buyer_id atau objek buyer (salah satu wajib, name, phone, address, area, address_type, latitude, longitude); items wajib array minimal satu, product_id harus ada di tabel products dan unik dalam satu permintaan, quantity 1 sampai 999; scheduled_date wajib format Y-m-d dan tidak sebelum hari ini Jakarta; window_start dan window_end format H:i dengan end setelah start; weight_kg 0,01 sampai 999,99; cooler_box_code maksimal 30; package_category maksimal 50; service_type REGULER atau INSTANT_FROZEN; koli_count minimal 1; handling_instructions maksimal 1000.
- Proses / Business Rules: bila buyer_id kosong, pembeli dicari berdasarkan nomor E.164 dan dibuat bila belum ada. Jadwal diparsing di zona Jakarta lalu disimpan UTC (BR-005). Paket dibuat dengan created_by admin id dan source ADMIN. Karena tracking_number adalah default PostgreSQL, model di-refresh agar nomornya terisi. Item dibuat satu per satu. Paket dibiarkan berstatus CREATED; tidak ada konfirmasi jadwal maupun notifikasi pada langkah ini.
- Output / Response: HTTP 201 dengan message yang memuat nomor resi dan objek package yang sudah memuat buyer, items.product, dan statusRef.
- Error & Edge Cases: tidak ada buyer_id dan buyer sekaligus menghasilkan 422; tanggal lampau menghasilkan 422 pada scheduled_date; window_end tidak setelah window_start menghasilkan 422; produk tidak ada menghasilkan 422; pembeli baru dengan nomor yang sudah ada akan memakai baris pembeli yang ada, bukan membuat duplikat.
- Side Effects: baris packages, package_items; baris status_logs INSERT CREATED.
- Postcondition: paket berstatus CREATED dan muncul di antrean shipment untuk ditugaskan ke kurir.
- Traceability: routes/api.php, Admin/ShipmentController@store, Actions/Packages/CreatePackage, Requests/Admin/StoreShipmentRequest, Support/PackageStatusTransitioner, Support/NotificationQueue, tests/Feature/AdminShipmentTest.php test_admin_can_create_new_shipment, resources/js/pages/admin/Shipment.tsx.
- Acceptance Criteria:
  - Given data pembeli baru, satu produk, dan jadwal besok, When shipment dibuat, Then HTTP 201 dengan resi berawalan AJ-FRZ- dan paket berstatus CREATED tanpa konfirmasi jadwal maupun notifikasi.
  - Given scheduled_date kemarin, When shipment dibuat, Then HTTP 422 pada errors.scheduled_date dan tidak ada baris packages baru.
- Status: Live

---

### FR-PKG-002 — Antrean shipment dengan filter

- Deskripsi: Sistem HARUS menampilkan daftar paket non-terminal (bukan CLOSED dan bukan RETURNED) dengan filter pencarian, status, kurir, jadwal relatif, tanggal eksplisit, sumber pesanan, dan ukuran halaman.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/shipments, juga dipakai halaman /admin/shipment.
- Precondition: tidak ada.
- Input & Validasi: q opsional maksimal 100 karakter; status opsional string; courier opsional string, nilai unassigned diartikan belum ditugaskan dan angka diartikan id kurir; schedule salah satu today, tomorrow, upcoming, all; date opsional format Y-m-d; per_page salah satu 10, 25, 50; source salah satu ADMIN, CLIENT_APP, all.
- Proses / Business Rules: tanggal eksplisit mengalahkan filter jadwal relatif sehingga drill-down dari laporan tidak menghasilkan irisan kosong. Pencarian memakai ILIKE dengan escape karakter persen, garis bawah, dan backslash pada resi, nama pembeli, area, dan nama kurir. Urutan deterministik berdasarkan tracking_number lalu id. Respons menyertakan daftar kurir aktif untuk pemilih dan statusOptions dari tabel package_statuses agar label status tidak diduplikasi frontend. Baris ISSUE_REPORTED menampilkan label "Kendala (kategori)" dan kolom availableAction dihitung dari Package::availableAdminActions.
- Output / Response: HTTP 200 dengan shipments, paginator (total, per_page, current_page, last_page, from, to), filters yang dipakai, couriers, statusOptions. Setiap baris memuat id, tracking, buyer, location, schedule, courier, courier_id, status, statusKey, tone, hasIssue, availableAction, source, sourceLabel, sourceBadge.
- Error & Edge Cases: per_page di luar daftar menghasilkan 422. q dengan karakter persen atau garis bawah tidak memperluas hasil karena di-escape. Halaman di luar rentang menghasilkan shipments kosong dengan paginator yang menandai halaman terakhir.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Admin/ShipmentController@index, Queries/ShipmentIndexQuery, DTOs/ShipmentFilters, Requests/Admin/IndexShipmentRequest, Support/DisplayTime, tests/Feature/AdminShipmentTest.php (index, filter, dan pencarian aman), resources/js/pages/admin/Shipment.tsx.
- Acceptance Criteria:
  - Given paket berstatus CLOSED dan RETURNED, When antrean diminta, Then kedua paket itu tidak muncul.
  - Given query pencarian berisi tanda persen, When antrean diminta, Then hasil hanya memuat baris yang benar-benar mengandung teks itu dan bukan seluruh tabel.
- Status: Live

---

### FR-PKG-003 — Data master form pembuatan paket

- Deskripsi: Sistem HARUS menyediakan daftar produk, daftar pembeli terakhir, dan daftar kurir aktif untuk mengisi form pembuatan paket.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/shipments/create.
- Precondition: tidak ada.
- Input & Validasi: tanpa input.
- Proses / Business Rules: produk diambil seluruhnya diurut nama; pembeli dibatasi 50 baris terurut nama; kurir diambil dari staff berperan COURIER dan is_active true dengan kolom id, name, phone.
- Output / Response: HTTP 200 dengan products, buyers, couriers.
- Error & Edge Cases: basis data kosong mengembalikan tiga array kosong, bukan error.
- Side Effects: tidak ada.
- Postcondition: form siap dipakai.
- Traceability: routes/api.php, Admin/ShipmentController@create, tests/Feature/AdminConsoleWiringTest.php test_the_create_master_data_includes_the_courier_roster, resources/js/pages/admin/Shipment.tsx.
- Acceptance Criteria:
  - Given satu kurir aktif dan satu kurir nonaktif, When data master diminta, Then couriers hanya memuat yang aktif.
  - Given database kosong, When data master diminta, Then HTTP 200 dengan tiga array kosong.
- Status: Live

---

### FR-PKG-004 — Detail paket dan timeline

- Deskripsi: Sistem HARUS menyajikan satu paket lengkap dengan timeline, tautan klien, label jadwal dan status, serta URL foto bukti pengantaran dan foto kendala.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/shipments/{tracking_number} saat admin membuka modal detail.
- Precondition: paket dengan resi tersebut ada.
- Input & Validasi: tracking_number pada path.
- Proses / Business Rules: relasi yang di-eager load adalah buyer, courier, statusRef, items.product, delivery, conditionChecks beserta issueReport dan decidedByStaff, notifications, dan statusLogs beserta changedByStaff agar tidak terjadi N+1. Timeline dibangun Support/PackageTimelineBuilder. Label status diganti "Kendala (kategori)" bila status ISSUE_REPORTED dan ada laporan kendala. Tautan klien dibuat Support/Client/ClientLinkService dan memakai config app.url sehingga tidak mewarisi host pemanggil. Foto bukti dan foto kendala diubah menjadi URL sementara melalui MediaStorage, yang jatuh ke route pratinjau bila disk tidak mendukung URL sementara.
- Output / Response: HTTP 200 dengan package, timeline, client_url, scheduleLabel, scheduledDate, statusLabel, tone, proofPhotoUrl, issuePhotoUrl.
- Error & Edge Cases: resi tidak dikenal menghasilkan 404 dari firstOrFail. Paket tanpa bukti pengantaran mengembalikan proofPhotoUrl null, bukan string kosong.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Admin/ShipmentController@show, Support/PackageTimelineBuilder, Services/Client/ClientLinkService, Support/MediaStorage, Support/DisplayTime, tests/Feature/AdminConsoleWiringTest.php test_the_shipment_detail_carries_the_proof_photos_and_labels, resources/js/components/ShipmentDetailModal.tsx.
- Acceptance Criteria:
  - Given paket yang sudah punya bukti pengantaran, When detail diminta, Then proofPhotoUrl terisi dan berisi rute pratinjau atau URL sah.
  - Given resi yang tidak ada, When detail diminta, Then HTTP 404.
- Status: Live

---

### FR-PKG-005 — Penjadwalan ulang oleh admin

- Deskripsi: Sistem HARUS menetapkan jendela pengantaran baru untuk paket yang meminta jadwal ulang, membuat konfirmasi PENDING baru, memindahkan paket kembali ke AWAITING_CONFIRMATION, dan memberi tahu pembeli.
- Aktor & Otorisasi: admin hub.
- Trigger: POST /api/admin/shipments/{tracking_number}/reschedule.
- Precondition: paket berstatus RESCHEDULE_REQUESTED.
- Input & Validasi: scheduled_date wajib format Y-m-d dan tidak sebelum hari ini; scheduled_time_window wajib string berbentuk dua jam dipisah tanda hubung, misalnya "09:00-11:00".
- Proses / Business Rules: dilakukan di dalam transaksi dengan lockForUpdate pada baris paket (BR-004). Bila status bukan RESCHEDULE_REQUESTED, lempar InvalidArgumentException. Jadwal baru diparsing di zona Jakarta dan disimpan UTC. Konfirmasi baru dibuat dengan response PENDING dan requested_at sekarang. Transisi ke AWAITING_CONFIRMATION dijalankan dengan aktor admin (BR-002 dan BR-003). Notifikasi template schedule_rescheduled diantrekan untuk pembeli.
- Output / Response: HTTP 200 dengan message yang memuat resi dan objek package yang sudah memuat buyer dan statusRef.
- Error & Edge Cases: status bukan RESCHEDULE_REQUESTED menghasilkan error 500 InvalidArgumentException saat ini, karena controller tidak memetakannya ke 422. Ini catatan temuan, bukan perilaku yang diinginkan. Tanggal lampau dan format jam salah menghasilkan 422 dari FormRequest.
- Side Effects: kolom scheduled_at dan scheduled_end_at paket berubah; satu baris schedule_confirmations PENDING baru; baris status_logs baru; satu baris notifications QUEUED berisi template schedule_rescheduled dan window.
- Postcondition: paket menunggu konfirmasi pembeli atas jadwal baru.
- Traceability: routes/api.php, Admin/ShipmentRescheduleController, Actions/Admin/RescheduleShipment, Requests/Admin/RescheduleShipmentRequest, Support/DisplayTime, Support/NotificationQueue, tests/Feature/ClientFlowTest.php test_admin_reschedule_shipment_action, resources/js/pages/admin/Shipment.tsx.
- Acceptance Criteria:
  - Given paket RESCHEDULE_REQUESTED, When admin mengirim jadwal baru yang sah, Then HTTP 200, paket menjadi AWAITING_CONFIRMATION, dan ada satu konfirmasi PENDING baru.
  - Given paket berstatus CONFIRMED, When reschedule dipanggil, Then permintaan gagal dan tidak ada baris schedule_confirmations tambahan.
- Status: Live

---

### FR-PKG-006 — Penjagaan FSM oleh database

- Deskripsi: Sistem HARUS menolak setiap perubahan kolom status paket yang pasangan from dan to-nya tidak terdaftar pada tabel package_status_transitions, dan HARUS memetakan penolakan itu menjadi pesan kesalahan yang dipahami aplikasi.
- Aktor & Otorisasi: sistem. Tidak dapat dilewati oleh route atau peran mana pun.
- Trigger: setiap UPDATE pada kolom status packages.
- Precondition: tidak ada.
- Input & Validasi: tidak ada input pengguna; yang diperiksa adalah pasangan status lama dan status baru.
- Proses / Business Rules: trigger BEFORE UPDATE trg_packages_validate_status menjalankan fungsi packages_validate_status; bila pasangan tidak ada di matriks, fungsi mengangkat exception dengan ERRCODE check_violation dan pesan "Invalid package status transition". PackageStatusTransitioner menangkap QueryException dengan kode SQLSTATE 23514 atau pesan tersebut dan melempar InvalidStatusTransition beserta status asal dan tujuan. Transisi dijalankan di dalam transaksi bila belum ada transaksi aktif.
- Output / Response: tidak ada respons HTTP langsung; pemanggil menerima InvalidStatusTransition.
- Error & Edge Cases: update status langsung tanpa transitioner dan di luar matriks selalu gagal, termasuk bila dilakukan dari psql. Pasangan dari dan to yang sama tidak diizinkan karena constraint ck_transitions_not_self melarang transisi ke diri sendiri.
- Side Effects: tidak ada baris status_logs baru bila transisi ditolak.
- Postcondition: status paket tidak berubah bila transisi tidak sah.
- Traceability: database/migrations/0001_01_01_000004_create_reference_tables.php, database/migrations/0001_01_01_000016_add_package_status_guard.php, database/seeders/ReferenceDataSeeder.php, Support/PackageStatusTransitioner.php, app/Exceptions/InvalidStatusTransition.php, tests/Feature/AdminConsoleE2ETest.php.
- Acceptance Criteria:
  - Given paket berstatus CREATED, When aplikasi mencoba memindahkannya langsung ke RECEIVED, Then transisi ditolak dan status tetap CREATED.
  - Given paket berstatus CONFIRMED, When AssignCourier memindahkannya ke COURIER_ASSIGNED, Then transisi berhasil karena pasangan itu ada di matriks.
- Status: Live

---

### FR-PKG-007 — Atribusi aktor pada log status

- Deskripsi: Sistem HARUS mencatat siapa yang memicu setiap perubahan status, dan HARUS membedakan aksi staf dari aksi pembeli atau sistem.
- Aktor & Otorisasi: sistem. Nilai aktor berasal dari parameter actor pada PackageStatusTransitioner.
- Trigger: setiap pemanggilan PackageStatusTransitioner::transition.
- Precondition: tidak ada.
- Input & Validasi: parameter actor bertipe Staff atau null.
- Proses / Business Rules: sebelum menyimpan, aplikasi menjalankan SELECT set_config dengan nama app.staff_id; bila actor null, nilai dikosongkan. Trigger AFTER INSERT OR UPDATE trg_packages_log_status membaca current_setting app.staff_id dan menuliskannya ke status_logs.changed_by (BR-003). Konsekuensinya aksi pembeli seperti konfirmasi jadwal dan pengajuan jadwal ulang menghasilkan changed_by null, dan aktornya harus dibaca dari tabel lain.
- Output / Response: tidak ada respons HTTP; hasilnya baris status_logs baru dengan from_status, to_status, changed_by, changed_at.
- Error & Edge Cases: nilai app.staff_id yang bukan angka akan membuat cast bigint gagal; karena itu aktor selalu dikirim sebagai string id atau string kosong. Aksi yang menggulung transaksi tidak meninggalkan baris log karena trigger berjalan di transaksi yang sama.
- Side Effects: satu baris status_logs per perubahan status, termasuk baris pertama saat INSERT dengan from_status null.
- Postcondition: riwayat status paket lengkap dan dapat diaudit.
- Traceability: Support/PackageStatusTransitioner.php, database/migrations/0001_01_01_000015_create_status_logs.php, database/migrations/0001_01_01_000016_add_package_status_guard.php, app/Models/StatusLog.php, tests/Feature/ClientFlowTest.php test_schedule_confirm_transitions_status_with_null_actor.
- Acceptance Criteria:
  - Given admin menugaskan kurir, When transisi COURIER_ASSIGNED tersimpan, Then baris status_logs terbaru memiliki changed_by sama dengan id admin itu.
  - Given pembeli mengonfirmasi jadwal, When transisi CONFIRMED tersimpan, Then baris status_logs terbaru memiliki changed_by null.
- Status: Live

---

### FR-PKG-008 — Transisi ke CLOSED

- Deskripsi: [TIDAK TERVERIFIKASI] Sistem seharusnya menutup paket terminal (RECEIVED dan RETURNED) menjadi CLOSED dan mengisi kolom closed_at. Tidak ditemukan pemanggil transisi ini maupun penulis kolom closed_at di seluruh app/.
- Aktor & Otorisasi: [TIDAK TERVERIFIKASI] tidak ada route, Action, maupun scheduler yang melakukannya.
- Trigger: [TIDAK TERVERIFIKASI]. Matriks database mengizinkan RECEIVED ke CLOSED dan RETURNED ke CLOSED, sehingga pemicunya bisa berupa aksi admin, job harian, atau proses luar sistem.
- Precondition: [TIDAK TERVERIFIKASI].
- Input & Validasi: [TIDAK TERVERIFIKASI].
- Proses / Business Rules: paket tanpa status CLOSED tetap terhitung pada beberapa query. Perlu dicatat bahwa Queries/ClientOrderListQuery dan BuyerProfileResource sudah memperhitungkan CLOSED sebagai status selesai, dan Services/Client/ReplacementEligibility menerima CLOSED sebagai status yang layak untuk penggantian, jadi nilainya sudah diantisipasi di kode baca meski tidak ada penulisnya.
- Output / Response: [TIDAK TERVERIFIKASI].
- Error & Edge Cases: karena tidak ada penulis, paket selesai berhenti di RECEIVED atau RETURNED. Arsip paket tidak pernah benar-benar tertutup, dan kolom closed_at tidak pernah berisi nilai.
- Side Effects: tidak ada.
- Postcondition: [TIDAK TERVERIFIKASI].
- Traceability: matriks transisi di database/seeders/ReferenceDataSeeder.php dan migrasi 0001_01_01_000020; pencarian pemanggil transitioner di app/Actions dan app/Support; kolom closed_at pada migrasi 0001_01_01_000008 dan Model Package.
- Acceptance Criteria:
  - Given paket berstatus RECEIVED, When tidak ada proses apa pun berjalan, Then status tetap RECEIVED selamanya dan closed_at null (perilaku saat ini).
  - Given keputusan stakeholder menetapkan pemicunya, When mekanisme itu dibuat, Then transisi ke CLOSED harus tetap melalui PackageStatusTransitioner dan mengisi closed_at.
- Status: [TIDAK TERVERIFIKASI]

---

### FR-PKG-009 — Meminta konfirmasi jadwal penerimaan

- Deskripsi: Sistem HARUS mengirim permintaan konfirmasi tanggal terima ke penerima setelah kurir ditugaskan, atas permintaan kurir atau admin. Inilah yang memindahkan paket dari COURIER_ASSIGNED ke AWAITING_CONFIRMATION.
- Aktor & Otorisasi: kurir pemilik paket (lewat aplikasi kurir) atau admin hub (lewat console). Kurir tidak memakai WhatsApp.
- Trigger: POST /api/courier/deliveries/{tracking_number}/request-confirmation atau POST /api/admin/shipments/{tracking_number}/request-confirmation.
- Precondition: paket berstatus COURIER_ASSIGNED; untuk jalur kurir, paket ditugaskan ke kurir itu.
- Input & Validasi: tracking_number pada path. Tidak ada body.
- Proses / Business Rules: action membuka transaksi dan mengunci baris paket. Bila aktor kurir dan courier_id bukan kurir itu, lempar ScheduleNotActionable. Bila status bukan COURIER_ASSIGNED, lempar ScheduleNotActionable. Bila sudah ada konfirmasi PENDING, kembalikan paket apa adanya (idempoten). Selain itu buat ScheduleConfirmation PENDING berisi proposed_scheduled_at dari scheduled_at paket dan requested_at sekarang, pindahkan paket ke AWAITING_CONFIRMATION dengan aktor, lalu antrekan notifikasi template schedule_request untuk penerima (recipient_type BUYER) berisi tracking_number, scheduled_at, dan scheduled_end_at.
- Output / Response: HTTP 200 dengan message "Permintaan konfirmasi jadwal resi {resi} dikirim ke penerima." dan objek package.
- Error & Edge Cases: status di luar COURIER_ASSIGNED menghasilkan 422 ScheduleNotActionable ("Konfirmasi jadwal dapat diminta setelah kurir ditugaskan."). Kurir yang bukan pemilik paket menghasilkan 422/404. Permintaan ganda tidak membuat konfirmasi kedua.
- Side Effects: baris schedule_confirmations PENDING; baris status_logs COURIER_ASSIGNED ke AWAITING_CONFIRMATION dengan aktor; satu baris notifications QUEUED template schedule_request.
- Postcondition: paket berstatus AWAITING_CONFIRMATION dan penerima menerima permintaan konfirmasi.
- Traceability: routes/api.php, Courier/RequestConfirmationController, Admin/ShipmentScheduleConfirmationController, Actions/Packages/RequestScheduleConfirmation, Enums/ConfirmationResponse, Models/ScheduleConfirmation, Support/NotificationQueue, Support/PackageStatusTransitioner.
- Acceptance Criteria:
  - Given paket COURIER_ASSIGNED milik kurir, When kurir meminta konfirmasi, Then HTTP 200, paket menjadi AWAITING_CONFIRMATION, dan satu konfirmasi PENDING serta notifikasi schedule_request dibuat.
  - Given paket CREATED, When konfirmasi diminta, Then permintaan ditolak ScheduleNotActionable dan tidak ada baris schedule_confirmations.
- Status: Live

---

### FR-PKG-010 — Penugasan kurir sebelum konfirmasi

- Deskripsi: Sistem HARUS mengizinkan admin menugaskan kurir pada paket yang masih CREATED, sehingga urutannya menjadi: buat paket, tugaskan kurir, baru minta konfirmasi jadwal.
- Aktor & Otorisasi: admin hub.
- Trigger: POST /api/admin/shipments/{tracking_number}/assign.
- Precondition: paket berstatus CREATED, CONFIRMED, COURIER_ASSIGNED, CHECKED, atau ISSUE_REPORTED.
- Input & Validasi: courier_id wajib, ada di tabel staff, dan staf itu kurir aktif.
- Proses / Business Rules: action mengunci baris paket, memeriksa status ada di daftar yang dapat ditugaskan, dan memastikan staf adalah kurir. Paket CREATED atau CONFIRMED dipindahkan ke COURIER_ASSIGNED sambil mengisi courier_id dan mengosongkan courier_accepted_at; paket yang lebih jauh (CHECKED, ISSUE_REPORTED) hanya berganti courier_id tanpa transisi. Penugasan tidak lagi mengantrekan notifikasi WhatsApp ke kurir: kurir melihat tugas di papan tugas aplikasi.
- Output / Response: HTTP 200 dengan message penugasan dan objek package.
- Error & Edge Cases: status di luar daftar menghasilkan PackageNotAssignable; staf bukan kurir aktif menghasilkan PackageNotAssignable. Menugaskan kurir yang sama saat paket masih CREATED/CONFIRMED menjalankan transisi (mengosongkan penerimaan sebelumnya).
- Side Effects: kolom courier_id dan courier_accepted_at; baris status_logs CREATED ke COURIER_ASSIGNED dengan aktor admin.
- Postcondition: paket berstatus COURIER_ASSIGNED dan siap dimintakan konfirmasi jadwal.
- Traceability: routes/api.php, Admin/ShipmentAssignmentController, Actions/Packages/AssignCourier, database/migrations/0001_01_01_000032_add_assign_confirm_transitions.php, database/seeders/ReferenceDataSeeder.php.
- Acceptance Criteria:
  - Given paket CREATED tanpa kurir, When admin menugaskan kurir, Then HTTP 200 dan paket menjadi COURIER_ASSIGNED.
  - Given paket CREATED, When antrean diminta, Then baris paket memuat availableAction assign_courier.
- Status: Live
