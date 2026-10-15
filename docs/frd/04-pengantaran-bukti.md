# FRD Modul DEL — Pengantaran dan Bukti Serah Terima

Modul ini mencakup perjalanan lapangan kurir dari layar pengantaran sampai bukti
serah terima tersimpan, beserta riwayat dan profil kurir.

Aturan bisnis terkait: BR-002, BR-003, BR-009, BR-010, BR-011, BR-020, BR-022.

---

### FR-DEL-001 — Layar pengantaran kurir

- Deskripsi: Sistem HARUS menyajikan detail pengantaran satu paket: tujuan, kontak, tautan peta, instruksi penanganan, tahap perjalanan, tombol aksi yang boleh dipakai, daftar checklist, dan konteks validasi lokasi.
- Aktor & Otorisasi: kurir yang ditugaskan pada paket itu.
- Trigger: GET /api/courier/deliveries/{tracking_number} saat layar pengantaran dibuka.
- Precondition: paket berstatus salah satu dari COURIER_ASSIGNED, AWAITING_CONFIRMATION, CONFIRMED, CHECKED, IN_DELIVERY, ISSUE_REPORTED, RECEIVED, RETURNED, atau CLOSED.
- Input & Validasi: tracking_number pada path.
- Proses / Business Rules: status di luar daftar itu menghasilkan 404 dengan pesan "Paket tidak ditemukan." agar keberadaan paket tidak bocor. Bila status COURIER_ASSIGNED dan courier_accepted_at null, sistem mengembalikan 422 dengan redirect ke /courier/tasks. Nomor telepon pembeli dinormalkan ke E.164 untuk tautan tel. Tautan peta memakai koordinat pembeli bila ada, dan alamat bila tidak ada. Izin aksi dihitung dari status: canRequestConfirmation untuk COURIER_ASSIGNED yang belum dikonfirmasi penerima, canDepart untuk CONFIRMED atau CHECKED (dan COURIER_ASSIGNED yang konfirmasinya sudah READY), canArrive untuk IN_DELIVERY yang belum ARRIVED, canProof untuk IN_DELIVERY, canReportIssue untuk status sebelum serah terima dan belum ada kendala terbuka. Konteks GPS memuat tujuan, radius segmen, verdict terakhir, jarak, akurasi, alasan, serta status permintaan tinjauan.
- Output / Response: HTTP 200 dengan delivery berisi tracking, service, serviceType, recipient, phone, phoneHref, address, instruction, mapLabel, mapHref, status, statusLabel, stage, stageLabel, canRequestConfirmation, canDepart, canArrive, canProof, canReportIssue, hasOpenIssue, checklistItems, dan gps.
- Error & Edge Cases: paket milik kurir lain menghasilkan 404 lewat PackagePolicy. Paket yang belum diterima kurir menghasilkan 422 dengan redirect. Paket berstatus CREATED menghasilkan 404 karena di luar daftar status, sehingga paket yang belum ditugaskan tidak bocor ke aplikasi kurir.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Courier/DeliveryController@show, Services/Gps/GpsValidator::latestCheck, Services/Gps/GeofenceEvaluator, config/coldchain.php checklist_items, app/Policies/PackagePolicy.php, tests/Feature/CourierFlowTest.php test_courier_delivery_show_and_guards, resources/js/pwa/screens/courier/Delivery.tsx.
- Acceptance Criteria:
  - Given paket IN_DELIVERY setelah berangkat, When layar diminta, Then canProof true dan stage DEPARTED.
  - Given paket COURIER_ASSIGNED yang belum dikonfirmasi penerima, When layar diminta, Then canRequestConfirmation true dan canDepart false.
  - Given paket berstatus CREATED, When layar pengantaran diminta, Then HTTP 404 dan tidak ada data paket yang tersebar.
- Status: Live

---

### FR-DEL-002 — Mulai pengantaran dengan checklist

- Deskripsi: Sistem HARUS mewajibkan checklist fisik bersih sebelum kurir berangkat, menyimpan condition check, memindahkan paket ke IN_DELIVERY, dan mencatat milestone keberangkatan.
- Aktor & Otorisasi: kurir pemilik paket.
- Trigger: POST /api/courier/deliveries/{tracking_number}/depart.
- Precondition: paket berstatus COURIER_ASSIGNED, CONFIRMED, atau CHECKED dan milik kurir ini. Bila masih COURIER_ASSIGNED, penerima harus sudah mengonfirmasi jadwal (confirmation READY).
- Input & Validasi: checklist opsional array yang dinormalkan ChecklistNormalizer ke empat kunci konfigurasi ditambah buyer_rejected, masing-masing berisi answer boolean dan detail maksimal 100 karakter; temperature_c opsional numerik antara -60 dan 40; latitude dan longitude opsional dengan longitude wajib bila latitude ada; device_timestamp opsional integer; gps_signals opsional array maksimal 60 fix dengan tiap fix berisi latitude, longitude, accuracy, dan timestamp.
- Proses / Business Rules: controller memeriksa lebih dulu apakah ada item checklist yang answer-nya true; bila ada, sistem mengembalikan 422 dengan redirect ke layar laporan kendala dan penanda hasIssue, tanpa transisi apa pun. GPS divalidasi dengan aksi DEPART dan enforceGeofence false sehingga radius tidak ditegakkan, tetapi detektor manipulasi tetap berjalan (BR-010 dan BR-011). Action kemudian memeriksa ulang checklist, membuat ConditionCheck dengan is_normal true dan catatan "Pemeriksaan sebelum berangkat normal", memastikan konfirmasi penerima sudah READY bila paket masih COURIER_ASSIGNED (kalau belum, DeliveryNotActionable), memindahkan paket dari COURIER_ASSIGNED atau CONFIRMED ke CHECKED bila perlu, memindahkan ke IN_DELIVERY, membuat milestone DEPARTED beserta koordinat, membuat PIN pengantaran 6 digit, menyimpan hash-nya beserta delivery_pin_sent_at, mengosongkan penghitung percobaan, lalu mengantrekan notifikasi on_the_way dan delivery_pin.
- Output / Response: HTTP 200 dengan message "Pengantaran telah dimulai." dan objek package yang memuat statusRef dan latestMilestone.
- Error & Edge Cases: checklist dengan satu jawaban true menghasilkan 422 tanpa perubahan data. Status di luar COURIER_ASSIGNED dan CHECKED menghasilkan DeliveryNotActionable::invalidState. GPS yang memblokir menghasilkan FakeGpsSuspected dan permintaan tinjauan dibuat.
- Side Effects: baris condition_checks baru; baris status_logs untuk CHECKED bila perlu dan untuk IN_DELIVERY; baris delivery_milestones DEPARTED berisi koordinat; kolom packages.delivery_pin_hash, delivery_pin_sent_at, dan delivery_pin_attempts; satu baris notifications QUEUED template on_the_way dan satu template delivery_pin untuk pembeli; baris location_checks bila koordinat dikirim.
- Postcondition: paket berada di jalan menuju penerima dan tidak dapat kembali ke CHECKED tanpa kendala.
- Traceability: routes/api.php, Courier/StartDeliveryController, Actions/Courier/StartDelivery, Requests/Courier/StartDeliveryRequest, DTOs/Courier/StartDeliveryData, Support/ChecklistNormalizer, Services/Gps/GpsValidator, tests/Feature/CourierFlowTest.php test_courier_depart_flow, resources/js/pwa/screens/courier/Delivery.tsx.
- Acceptance Criteria:
  - Given checklist seluruhnya normal dan paket COURIER_ASSIGNED, When depart dipanggil, Then HTTP 200, paket menjadi IN_DELIVERY, ada condition check normal, dan milestone DEPARTED tercatat.
  - Given satu item checklist bernilai true, When depart dipanggil, Then HTTP 422 dengan hasIssue true, tidak ada condition check baru, dan status paket tidak berubah.
- Status: Live

---

### FR-DEL-003 — Menandai tiba di lokasi

- Deskripsi: Sistem HARUS mencatat milestone kedatangan kurir di lokasi tujuan beserta koordinat, tanpa menegakkan radius.
- Aktor & Otorisasi: kurir pemilik paket.
- Trigger: POST /api/courier/deliveries/{tracking_number}/arrive.
- Precondition: paket berstatus IN_DELIVERY dan milestone terakhir adalah DEPARTED.
- Input & Validasi: latitude dan longitude opsional dengan longitude wajib bila latitude ada; device_timestamp dan gps_signals seperti pada FR-DEL-002.
- Proses / Business Rules: GPS dinilai dengan aksi ARRIVE dan enforceGeofence false sehingga hanya jarak yang dicatat (BR-010). Action memeriksa bahwa milestone terakhir adalah DEPARTED; bila sudah ARRIVED, kembalikan paket tanpa perubahan sehingga aksi idempoten. Bila milestone terakhir bukan DEPARTED, lempar DeliveryNotActionable dengan pesan bahwa kurir belum memulai perjalanan. Milestone ARRIVED dibuat beserta koordinat dan waktu sekarang. Notifikasi courier_arrived diantrekan untuk pembeli.
- Output / Response: HTTP 200 dengan message "Status berhasil diperbarui: Tiba di lokasi." dan objek package yang memuat statusRef dan latestMilestone.
- Error & Edge Cases: menandai tiba dua kali tidak membuat milestone ganda. Menandai tiba sebelum berangkat menghasilkan DeliveryNotActionable. Paket bukan milik kurir menghasilkan 404.
- Side Effects: baris delivery_milestones ARRIVED; baris location_checks bila koordinat dikirim; satu baris notifications QUEUED template courier_arrived untuk pembeli.
- Postcondition: tahap pengantaran kurir menjadi ARRIVED dan tombol tersebut tidak ditawarkan lagi.
- Traceability: routes/api.php, Courier/MarkArrivedController, Actions/Courier/MarkArrived, Requests/Courier/MarkArrivedRequest, Enums/DeliveryMilestoneEnum, tests/Feature/CourierFlowTest.php test_courier_arrive_flow, resources/js/pwa/screens/courier/Delivery.tsx.
- Acceptance Criteria:
  - Given paket IN_DELIVERY dengan milestone DEPARTED, When arrive dipanggil dengan koordinat, Then HTTP 200, milestone ARRIVED tercatat dengan koordinat, dan pembeli menerima notifikasi courier_arrived.
  - Given paket IN_DELIVERY tanpa milestone DEPARTED, When arrive dipanggil, Then permintaan ditolak DeliveryNotActionable dan tidak ada milestone baru.
- Status: Live

---

### FR-DEL-004 — Form bukti serah terima

- Deskripsi: Sistem HARUS menyediakan data form bukti serah terima: resi, layanan, nama penerima, daftar hubungan penerima, dan placeholder catatan.
- Aktor & Otorisasi: kurir pemilik paket.
- Trigger: GET /api/courier/deliveries/{tracking_number}/proof.
- Precondition: paket berstatus IN_DELIVERY.
- Input & Validasi: tracking_number pada path.
- Proses / Business Rules: hubungan penerima yang ditawarkan mencakup SELF, FAMILY, COLLEAGUE, dan SECURITY yang diubah ke label Indonesia, disertai daftar label teks sebagai cadangan untuk layar lama.
- Output / Response: HTTP 200 dengan proofForm berisi tracking, service, recipient, relations (empat label), relationOptions (nilai dan label), dan notesPlaceholder.
- Error & Edge Cases: paket di luar status IN_DELIVERY mengembalikan 422 dengan redirect ke layar pengantaran paket itu. Paket milik kurir lain menghasilkan 404.
- Side Effects: tidak ada.
- Postcondition: kurir dapat mengunggah bukti.
- Traceability: routes/api.php, Courier/ProofController@create, Enums/RecipientRelation, tests/Feature/CourierFlowTest.php test_courier_proof_create_and_store_flow, resources/js/pwa/screens/courier/Proof.tsx.
- Acceptance Criteria:
  - Given paket IN_DELIVERY, When form bukti diminta, Then relationOptions memuat empat nilai enum.
  - Given paket berstatus COURIER_ASSIGNED, When form bukti diminta, Then HTTP 422 dengan redirect dan tidak ada form.
- Status: Live

---

### FR-DEL-005 — Menyimpan bukti serah terima

- Deskripsi: Sistem HARUS menyimpan bukti serah terima beserta foto, memvalidasi posisi kurir terhadap radius tujuan, dan memindahkan paket ke RECEIVED.
- Aktor & Otorisasi: kurir pemilik paket.
- Trigger: POST /api/courier/deliveries/{tracking_number}/proof, multipart dengan foto.
- Precondition: paket berstatus IN_DELIVERY dan milik kurir ini.
- Input & Validasi: received_by wajib 2 sampai 120 karakter; recipient_relation wajib nilai RecipientRelation yang sah; pin wajib 6 digit (kode yang dikirim ke penerima saat kurir berangkat); photo wajib gambar bertipe jpeg, jpg, png, atau webp dengan ukuran maksimal 5120 KB; receipt_note opsional maksimal 300 karakter; latitude, longitude, device_timestamp, dan gps_signals seperti pada FR-DEL-002.
- Proses / Business Rules: status diperiksa lebih dulu; bila bukan IN_DELIVERY, kembalikan 422 dengan redirect. PIN diperiksa terhadap hash paket: bila hash belum ada atau PIN tidak cocok, balas 422 tanpa menyimpan foto dan tanpa baris deliveries. GPS dinilai dengan aksi POD dan enforceGeofence true sehingga radius ditegakkan di titik serah terima (BR-009 dan BR-010). Foto disimpan MediaStorage dengan pola folder per bulan dan ekstensi jpg. Action membuat baris deliveries dengan received_by, recipient_relation, photo_url, receipt_note, dan delivered_at; memastikan milestone terakhir adalah ARRIVED, bila belum maka dibuat; memindahkan paket ke RECEIVED dengan aktor kurir; mengosongkan delivery_pin_hash sehingga kode tidak dapat dipakai ulang; dan mengantrekan notifikasi delivered. Bila terjadi kegagalan, foto yang sudah tersimpan dihapus. Bila paket sudah RECEIVED atau sudah punya baris delivery, foto yang baru dihapus dan respons sukses dikembalikan tanpa duplikasi.
- Output / Response: HTTP 200 dengan message "Pengiriman {resi} selesai." dan objek package yang memuat statusRef dan delivery.
- Error & Edge Cases: di luar radius menghasilkan OutsideGeofence dan paket tidak berubah. PIN salah atau belum tersedia menghasilkan 422 dan tidak ada baris deliveries. Foto lebih dari 5 MB atau format lain menghasilkan 422. GPS yang terdeteksi palsu menghasilkan FakeGpsSuspected. Paket yang sudah punya bukti menghasilkan sukses tanpa baris baru.
- Side Effects: file foto pada disk lokal; baris deliveries; baris delivery_milestones ARRIVED bila belum ada; kolom packages.delivery_pin_hash dikosongkan; baris status_logs IN_DELIVERY ke RECEIVED dengan deskripsi serah terima; satu baris notifications QUEUED template delivered.
- Postcondition: paket berstatus RECEIVED dan tidak dapat menerima aksi pengantaran lagi.
- Traceability: routes/api.php, Courier/ProofController@store, Actions/Courier/ConfirmDelivery, Requests/Courier/ConfirmDeliveryRequest, DTOs/Courier/ConfirmDeliveryData, Support/MediaStorage, Services/Gps/GpsValidator, tests/Feature/CourierFlowTest.php test_courier_proof_create_and_store_flow, tests/Feature/CourierGpsValidationTest.php, tests/Feature/AdminConsoleE2ETest.php test_the_delivery_journey_completes_end_to_end, resources/js/pwa/screens/courier/Proof.tsx.
- Acceptance Criteria:
  - Given paket IN_DELIVERY dan kurir berada dalam radius, When bukti dikirim lengkap dengan PIN yang benar, Then HTTP 200, barang berstatus RECEIVED, baris deliveries memuat foto, dan notifikasi delivered terantre.
  - Given kurir berada di luar radius, When bukti dikirim, Then permintaan ditolak OutsideGeofence, tidak ada baris deliveries, dan foto dibersihkan dari disk.
  - Given PIN salah, When bukti dikirim, Then HTTP 422 dan tidak ada baris deliveries.
- Status: Live

---

### FR-DEL-006 — Riwayat pengantaran kurir

- Deskripsi: Sistem HARUS menampilkan riwayat paket terminal milik kurir beserta ringkasan jumlah selesai hari ini.
- Aktor & Otorisasi: kurir, hanya paket dengan courier_id miliknya.
- Trigger: GET /api/courier/history.
- Precondition: token kurir valid.
- Input & Validasi: q opsional; status opsional; page minimal 1 (dibaca dari query, bukan FormRequest).
- Proses / Business Rules: memakai Queries/HistoryQuery dengan filter courierId sehingga kurir lain tidak terlihat. Status yang masuk hanya RECEIVED, RETURNED, dan CLOSED. Waktu selesai diambil sebagai COALESCE deliveries.delivered_at atau MAX status_logs.changed_at untuk RETURNED. Label status disesuaikan menjadi "Terkirim" untuk RECEIVED dan "Dikembalikan" untuk RETURNED. Ringkasan menghitung paket RECEIVED dengan updated_at hari ini dan mengirim label tepat waktu 97 persen sebagai konstanta.
- Output / Response: HTTP 200 dengan history, summary (completedToday, completedCount, onTimeRate), dan pagination (currentPage, lastPage, perPage, total).
- Error & Edge Cases: kurir tanpa riwayat menerima daftar kosong dengan summary berisi nol. Paket yang belum terminal tidak muncul di sini.
- Side Effects: tidak ada.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Courier/HistoryController@index, Queries/HistoryQuery, DTOs/HistoryFilters, Support/DisplayTime, tests/Feature/CourierFlowTest.php test_courier_history_and_profile, resources/js/pwa/screens/courier/History.tsx.
- Acceptance Criteria:
  - Given kurir dengan dua paket RECEIVED hari ini, When riwayat diminta, Then daftar memuat dua baris berlabel Terkirim dan completedCount bernilai 2.
  - Given kurir tanpa paket terminal, When riwayat diminta, Then history kosong dan summary berisi nol, bukan error.
- Status: Live

---

### FR-DEL-007 — Profil kurir

- Deskripsi: Sistem HARUS menampilkan identitas kurir dan statistik hariannya. Sebagian nilai masih berupa konstanta.
- Aktor & Otorisasi: kurir terautentikasi.
- Trigger: GET /api/courier/profile saat tab profil dibuka.
- Precondition: token kurir valid.
- Input & Validasi: tanpa input.
- Proses / Business Rules: nama, status aktif, dan nomor telepon diambil dari baris staff. Nomor kurir dibentuk sebagai KRR- diikuti id staf. Hub diambil dari config coldchain.hub_name. Kendaraan, tingkat tepat waktu 97 persen, dan rating 4.8 adalah konstanta di controller. Jumlah selesai hari ini dihitung dari paket RECEIVED milik kurir yang updated_at-nya hari ini.
- Output / Response: HTTP 200 dengan profile berisi name, status, id, phone, hub, vehicle, dan stats (tiga kartu: selesai hari ini, tepat waktu, rating).
- Error & Edge Cases: kurir tanpa pengantaran hari ini menerima nilai nol pada kartu pertama. Nilai tepat waktu dan rating tidak berubah karena bukan hasil hitung.
- Side Effects: tidak ada.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Courier/ProfileController@show, config/coldchain.php hub_name, tests/Feature/CourierFlowTest.php test_courier_history_and_profile, tests/Feature/PwaShellTest.php test_the_profile_tab_shows_identity_and_assignment, resources/js/pwa/screens/courier/Profile.tsx.
- Acceptance Criteria:
  - Given kurir dengan dua paket terkirim hari ini, When profil diminta, Then kartu "Selesai hari ini" bernilai 2 dan hub memakai nilai konfigurasi.
  - Given kurir tanpa pengantaran hari ini, When profil diminta, Then kartu pertama bernilai 0 dan permintaan tetap HTTP 200.
- Status: Partial (nilai kendaraan, tepat waktu, dan rating masih konstanta di controller, bukan data)

