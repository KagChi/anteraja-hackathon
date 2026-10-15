# FRD Modul TRACK — Pelacakan Publik dan Tautan Tamu

Modul ini melayani dua pemakai tanpa akun: pengunjung landing page yang mengetik
nomor resi, dan pelanggan yang membuka tautan token dari notifikasi WhatsApp.

Aturan bisnis terkait: BR-005, BR-014, BR-017, BR-018, BR-019, BR-024, BR-025.

---

### FR-TRACK-001 — Pelacakan publik berdasarkan resi

- Deskripsi: Sistem HARUS menjawab pertanyaan "di mana paket saya" dengan status, jendela pengantaran, kota tujuan, dan timeline berlabel tetap, TANPA mengungkap identitas penerima.
- Aktor & Otorisasi: publik, middleware force.json, client.headers, dan throttle track-resi 20 permintaan per menit per IP.
- Trigger: GET /api/track/{resi} dari panel lacak di landing page.
- Precondition: tidak ada.
- Input & Validasi: resi pada path harus cocok pola 4 sampai 32 karakter alfanumerik atau tanda hubung; resi dipangkas dan diubah ke huruf besar sebelum dicari.
- Proses / Business Rules: paket diambil bersama buyer, statusRef, statusLogs, dan deliveryMilestones. Timeline disusun dari dua sumber: setiap baris status_logs diterjemahkan memakai tabel label tetap berisi sebelas status, dan setiap milestone DEPARTED atau ARRIVED menjadi entri "Kurir berangkat" atau "Kurir tiba di lokasi". Keduanya digabung dan diurutkan menaik berdasarkan waktu; entri terakhir ditandai is_accent true. Kota tujuan diambil dari pembeli.area dengan mengambil bagian setelah koma terakhir, sehingga "Tebet, Jakarta Selatan" menjadi "Jakarta Selatan" (BR-019). Label suhu selalu dikirim sebagai teks tetap "Beku -18°C". Tanggal kejadian terakhir dipilih dari statusLogs bila ada, bukan dari updated_at.
- Output / Response: HTTP 200 dengan data berisi resi, status (code, label, tone), service, category, cold_chain, destination_city, schedule_label, updated_label, dan timeline berisi label, time, detail, is_accent.
- Error & Edge Cases: resi tidak dikenal menghasilkan 404 TRACKING_NOT_FOUND dengan pesan agar pengunjung memeriksa kembali nomornya. Paket tanpa status log menghasilkan timeline kosong dan updated_label "-". Pencarian dengan huruf kecil tetap ditemukan karena resi dinormalkan.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Api/Tracking/TrackController, Support/DisplayTime, tests/Feature/PublicTrackingTest.php (lima test termasuk yang membuktikan identitas tidak bocor dan tujuan hanya sampai tingkat kabupaten), resources/js/components/site/TrackResiCard.tsx.
- Acceptance Criteria:
  - Given resi yang ada dengan tiga status log, When dilacak, Then HTTP 200 dengan timeline tiga entri berurutan menaik dan entri terakhir ditandai accent.
  - Given resi yang tidak ada, When dilacak, Then HTTP 404 TRACKING_NOT_FOUND tanpa mengembalikan data paket apa pun.
- Status: Live

---

### FR-TRACK-002 — Resolusi tautan tamu berbatas waktu

- Deskripsi: Sistem HARUS menerima tautan token sebagai bukti akses ke satu paket, dengan batas percobaan gagal dan batas umur tautan untuk paket terminal.
- Aktor & Otorisasi: guest; middleware client.package memanggil ClientAccessService sebelum controller berjalan.
- Trigger: permintaan apa pun ke prefix /api/guest/{token} atau /api/client/{token}.
- Precondition: tidak ada.
- Input & Validasi: token pada path harus cocok pola hex 32 sampai 128 karakter.
- Proses / Business Rules: pencarian gagal dibatasi 20 kali per IP per menit; bila limit tercapai, responsnya HttpException 429 dengan pesan sisa waktu tunggu. Format token yang tidak sah atau token yang tidak ada dicatat sebagai kegagalan (hit) dan menghasilkan ClientLinkInvalid. Paket yang ditemukan dimuat bersama buyer, courier, dan items.product. Untuk paket berstatus RECEIVED, RETURNED, atau CLOSED, tautan dianggap kedaluwarsa bila updated_at-nya lebih lama dari config coldchain.client.link_ttl_days, yang bernilai 30 hari; kondisi itu menghasilkan ClientLinkExpired. Percobaan yang berhasil membersihkan penghitung kegagalan untuk IP tersebut. Paket yang berhasil diresolusi disimpan sebagai atribut client_package dan juga menggantikan parameter route token dan package dengan objek paket, sehingga controller menerima model, bukan string.
- Output / Response: instance Package pada atribut request.
- Error & Edge Cases: percobaan ke-21 dalam satu menit ditolak 429 walau tokennya benar. Paket terminal yang tautannya lebih tua dari 30 hari ditolak meski token masih cocok. Paket aktif tidak pernah kedaluwarsa, berapa pun umurnya.
- Side Effects: penghitung rate limit per IP.
- Postcondition: controller dapat membaca paket dari atribut client_package.
- Traceability: Services/Client/ClientAccessService.php, Middleware/ResolveClientPackage.php, app/Exceptions/ClientLinkInvalid.php, app/Exceptions/ClientLinkExpired.php, config/coldchain.php client.link_ttl_days, tests/Unit/ClientUnitTest.php (enam test tentang token, rate limit, dan kedaluwarsa), tests/Feature/ClientFlowTest.php.
- Acceptance Criteria:
  - Given token sah untuk paket aktif, When tautan dibuka, Then paket tersedia dan permintaan diteruskan ke controller.
  - Given dua puluh percobaan token tidak sah dalam satu menit, When percobaan kedua puluh satu dikirim, Then HTTP 429 dan bukan 404.
- Status: Live

---

### FR-TRACK-003 — Pemilihan layar otomatis

- Deskripsi: Sistem HARUS menentukan layar yang tepat bagi pemegang tautan: jadwal, kendala, atau tracking, sehingga pelanggan tidak perlu memilih sendiri.
- Aktor & Otorisasi: guest pemegang tautan dan pemilik paket terautentikasi memakai logika yang sama.
- Trigger: GET /api/guest/{token} (jalur guest) dan GET /api/client/{token} (jalur legacy), keduanya melalui Client/EntryController atau Api/Client/LinkController.
- Precondition: paket sudah diresolusi.
- Input & Validasi: tanpa input.
- Proses / Business Rules: status AWAITING_CONFIRMATION menghasilkan layar schedule. Status ISSUE_REPORTED atau RETURNED yang memiliki laporan kendala menghasilkan layar issue. Selainnya menghasilkan layar tracking. OrderPresenter::resolveActionRequired memakai resolver yang sama dan menambahkan langkah penggantian: untuk RETURNED atau CLOSED, bila ReplacementEligibility menyatakan layak, aksi yang tertunda adalah request_replacement; untuk IN_DELIVERY, aksinya adalah track.
- Output / Response: HTTP 200 dengan next_screen dan order (OrderSummaryResource) pada jalur guest, atau screen dan redirect_url pada jalur legacy.
- Error & Edge Cases: paket RETURNED tanpa laporan kendala tidak diarahkan ke layar kendala melainkan ke tracking. Paket RETURNED dengan keputusan bukan RETURN dan paket anak sudah ada tidak menawarkan penggantian.
- Side Effects: tidak ada penulisan.
- Postcondition: pelanggan melihat layar yang meminta tindakan nyata, bukan layar kosong.
- Traceability: Services/Client/ClientScreenResolver.php, Services/Client/OrderPresenter.php, Enums/Client/ClientScreen.php, Client/EntryController, Api/Client/LinkController, tests/Unit/ClientUnitTest.php test_screen_resolver_maps_statuses_correctly, tests/Feature/ClientFlowTest.php test_entry_resolves_appropriate_screen_and_redirect_url, tests/Feature/WhatsappLookupTest.php test_it_maps_every_status_to_the_pending_step.
- Acceptance Criteria:
  - Given paket AWAITING_CONFIRMATION, When tautan dibuka, Then next_screen bernilai schedule.
  - Given paket ISSUE_REPORTED tanpa laporan kendala, When tautan dibuka, Then next_screen bernilai tracking dan bukan issue.
- Status: Live

---

### FR-TRACK-004 — Data tracking pelanggan

- Deskripsi: Sistem HARUS menyajikan layar tracking pelanggan: status, perkiraan kedatangan, kartu cold chain, timeline, dan tombol menghubungi kurir yang dibuka hanya saat pengantaran berjalan.
- Aktor & Otorisasi: pembeli pemilik paket (client.owned) atau guest pemegang tautan.
- Trigger: GET /api/client/orders/{tracking_number}/tracking, GET /api/guest/{token}/tracking, dan GET /api/client/{token}/tracking.
- Precondition: paket tersedia dan boleh diakses.
- Proses / Business Rules: nomor kurir hanya dikirim bila status IN_DELIVERY dan kurir ada. Kartu cold chain DISEMBUNYIKAN untuk status CREATED, AWAITING_CONFIRMATION, RESCHEDULE_REQUESTED, dan CONFIRMED, sehingga suhu baru ditampilkan setelah kurir ditugaskan. Banner kendala muncul untuk status ISSUE_REPORTED dan RETURNED beserta tautan ke layar kendala. Banner jadwal ulang muncul untuk status RESCHEDULE_REQUESTED. Perkiraan kedatangan disusun dari jadwal dalam zona Jakarta sebagai tanggal berbahasa Indonesia dan jendela jam. Ringkasan produk menyebut nama produk pertama ditambah jumlah lainnya, serta koli dan berat dalam gram.
- Output / Response: HTTP 200 dengan data view berisi tracking_number, status, statusLabel, statusTone, estimatedDelivery, buyerName, buyerArea, courierName, coolerBoxCode, coldChain, timeline, canContactCourier, courierPhone, issueBanner, rescheduleBanner, productName, productMeta.
- Error & Edge Cases: paket tanpa jadwal menampilkan estimatedDelivery kosong. Paket tanpa kurir tidak menampilkan tombol kontak. Status pra-kurir menampilkan coldChain null sehingga layar harus menangani ketiadaan kartu itu.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: Services/Client/TrackingViewService.php, ColdChainSummaryService.php, ClientTimelineBuilder.php, Enums/Client/ColdChainState.php, tests/Feature/ClientFlowTest.php test_tracking_returns_formatted_data_and_client_status_labels, tests/Unit/ClientUnitTest.php test_cold_chain_summary_evaluates_measured_and_target_states, tests/Feature/PwaShellTest.php test_tracking_shows_the_cold_chain_reading_and_the_journey, resources/js/pages/client/Tracking.tsx, resources/js/pwa/screens/client/Tracking.tsx.
- Acceptance Criteria:
  - Given paket IN_DELIVERY dengan kurir dan suhu terukur optimal, When layar tracking diminta, Then canContactCourier true dan kartu cold chain berisi suhu serta state Terjaga.
  - Given paket CONFIRMED yang belum punya kurir, When layar tracking diminta, Then coldChain null dan canContactCourier false.
- Status: Live

---

### FR-TRACK-005 — Data jadwal pelanggan

- Deskripsi: Sistem HARUS menyajikan layar jadwal: kargo, total berat, jendela yang diusulkan, catatan penanganan, dan slot yang dapat dipilih.
- Aktor & Otorisasi: pembeli pemilik paket atau guest pemegang tautan.
- Trigger: GET /api/client/orders/{tracking_number}/schedule, GET /api/guest/{token}/schedule, GET /api/client/{token}/schedule.
- Precondition: paket tersedia dan boleh diakses.
- Proses / Business Rules: jendela ditampilkan dalam zona Jakarta dengan format tanggal berbahasa Indonesia dan jam dalam bentuk H.i, memakai scheduled_end_at bila ada atau dua jam setelah start bila tidak ada. Setiap item menjadi satu baris kargo berisi kategori, nama produk, dan label jumlah. Catatan penanganan diambil dari handling_notes produk, digabung dengan pemisah titik koma, atau memakai catatan bawaan bila kosong. Lencana kurir hanya muncul bila kurir sudah ditugaskan. Priority cold ditandai bila service type INSTANT_FROZEN. Penanda can_action hanya true bila status AWAITING_CONFIRMATION DAN ada konfirmasi PENDING, sehingga tombol konfirmasi tidak muncul pada paket yang sudah dijawab. Daftar slot berasal dari config coldchain.client.slots berisi lima rentang dua jam.
- Output / Response: HTTP 200 dengan data view berisi trackingNumber, status, statusLabel, buyerName, buyerAddress, cargoItems, totalWeightKg, coldGuaranteedBadge, handlingNote, scheduledDelivery, hasColdPriority, courierName, courierBadge, canAction, availableSlots.
- Error & Edge Cases: paket tanpa jadwal menampilkan teks "Belum ditentukan". Paket yang sudah CONFIRMED menampilkan canAction false sehingga layar tidak menawarkan aksi.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: Services/Client/ScheduleViewService.php, DTOs/Client/ScheduleView.php dan CargoLine.php, config/coldchain.php client.slots, tests/Feature/ClientFlowTest.php test_schedule_show_returns_cargo_items_and_weight, resources/js/pages/client/Schedule.tsx, resources/js/pwa/screens/client/Schedule.tsx.
- Acceptance Criteria:
  - Given paket AWAITING_CONFIRMATION dengan konfirmasi PENDING, When layar jadwal diminta, Then canAction true dan availableSlots memuat lima rentang.
  - Given paket yang sudah CONFIRMED, When layar jadwal diminta, Then canAction false sehingga tombol konfirmasi tidak ditawarkan lagi.
- Status: Live

---

### FR-TRACK-006 — Konfirmasi jadwal

- Deskripsi: Sistem HARUS memindahkan paket dari AWAITING_CONFIRMATION ke CONFIRMED saat pembeli menyetujui jadwal, dan HARUS tahan terhadap penekanan tombol berulang.
- Aktor & Otorisasi: pembeli pemilik paket atau guest pemegang tautan.
- Trigger: POST /api/client/orders/{tracking_number}/schedule/confirm, POST /api/guest/{token}/schedule/confirm, dan POST /api/client/{token}/schedule/confirm.
- Precondition: paket berstatus AWAITING_CONFIRMATION dan ada konfirmasi PENDING.
- Input & Validasi: tanpa body.
- Proses / Business Rules: transaksi dengan lockForUpdate pada baris paket (BR-004). Bila status sudah CONFIRMED dan konfirmasi terakhir berespons READY, sistem mengembalikan paket apa adanya sebagai idempotensi (BR-025). Bila status bukan AWAITING_CONFIRMATION, lempar ScheduleNotActionable. Konfirmasi PENDING dikunci dengan lockForUpdate lalu diubah menjadi READY dengan responded_at sekarang. Transisi ke CONFIRMED dijalankan dengan aktor null, sehingga status_logs.changed_by kosong (BR-003). Notifikasi tidak diantrekan pada langkah ini; kurir tidak lagi menerima WhatsApp, tugasnya terbaca dari aplikasi kurir.
- Output / Response: HTTP 200 dengan success true, message "Jadwal pengantaran berhasil dikonfirmasi.", redirect_url ke tautan tracking, dan data status serta tracking_number.
- Error & Edge Cases: permintaan kedua setelah sukses mengembalikan sukses tanpa transisi tambahan. Paket yang tidak lagi menunggu konfirmasi menghasilkan ScheduleNotActionable dengan pesan status tidak dapat dikonfirmasi. Konfirmasi PENDING yang tidak ada menghasilkan ScheduleNotActionable dengan pesan tidak ada jadwal menunggu konfirmasi.
- Side Effects: kolom response dan responded_at konfirmasi; baris status_logs ke CONFIRMED dengan changed_by null.
- Postcondition: paket siap ditugaskan ke kurir.
- Traceability: Actions/Client/ConfirmSchedule.php, Support/PackageStatusTransitioner.php, Api/Client/ScheduleConfirmationController, Client/ScheduleConfirmationController, app/Exceptions/ScheduleNotActionable.php, tests/Feature/ClientFlowTest.php test_schedule_confirm_transitions_status_with_null_actor, resources/js/pwa/screens/client/Schedule.tsx.
- Acceptance Criteria:
  - Given paket AWAITING_CONFIRMATION dengan konfirmasi PENDING, When konfirmasi dikirim, Then HTTP 200, paket menjadi CONFIRMED, dan baris status_logs baru berubah_by null.
  - Given paket yang sudah CONFIRMED, When konfirmasi dikirim lagi, Then HTTP 200 tanpa baris status_logs tambahan.
- Status: Live

---

### FR-TRACK-007 — Permintaan jadwal ulang pelanggan

- Deskripsi: Sistem HARUS menerima pilihan slot baru dari pelanggan, memindahkan paket ke RESCHEDULE_REQUESTED, dan memberitahu seluruh admin aktif agar menyusun jadwal pengganti.
- Aktor & Otorisasi: pembeli pemilik paket atau guest pemegang tautan.
- Trigger: POST /api/client/orders/{tracking_number}/schedule/reschedule, POST /api/guest/{token}/schedule/reschedule, dan POST /api/client/{token}/schedule/reschedule.
- Precondition: paket berstatus AWAITING_CONFIRMATION dengan konfirmasi PENDING.
- Input & Validasi: date wajib format Y-m-d dan tidak sebelum hari ini; slot wajib salah satu nilai dari config coldchain.client.slots. Validator lanjutan menolak tanggal lebih dari 14 hari ke depan dan slot yang waktunya kurang dari 2 jam dari sekarang, dengan pesan yang menyebut angka batasnya (BR-006).
- Proses / Business Rules: transaksi dengan lockForUpdate. Bila status sudah RESCHEDULE_REQUESTED, kembalikan paket apa adanya sebagai idempotensi. Bila status bukan AWAITING_CONFIRMATION, lempar ScheduleNotActionable. Konfirmasi PENDING diubah menjadi RESCHEDULE_REQUESTED dengan proposed_scheduled_at berisi waktu slot yang dipilih dalam UTC dan responded_at sekarang. Transisi ke RESCHEDULE_REQUESTED dijalankan dengan aktor null. Setiap admin aktif menerima notifikasi reschedule_requested berisi tanggal usulan dan jadwal saat ini.
- Output / Response: HTTP 200 dengan success true, message "Permintaan perubahan jadwal berhasil dikirim.", redirect_url ke tracking, dan data status serta tracking_number.
- Error & Edge Cases: tanggal lebih dari 14 hari menghasilkan 422 pada errors.date; slot kurang dari 2 jam menghasilkan 422 pada errors.slot. Permintaan kedua saat status sudah RESCHEDULE_REQUESTED mengembalikan sukses tanpa perubahan.
- Side Effects: kolom response, proposed_scheduled_at, dan responded_at konfirmasi; baris status_logs; baris notifications QUEUED reschedule_requested per admin aktif.
- Postcondition: paket menunggu admin menyusun jadwal baru (FR-PKG-005).
- Traceability: Actions/Client/RequestReschedule.php, Requests/Client/RescheduleRequest.php, DTOs/Client/RescheduleRequestData.php, config/coldchain.php client.reschedule_max_days dan min_lead_hours, Support/NotificationQueue.php, tests/Feature/ClientFlowTest.php test_schedule_reschedule_validates_and_transitions_status, resources/js/pwa/screens/client/Schedule.tsx.
- Acceptance Criteria:
  - Given paket AWAITING_CONFIRMATION, When pelanggan memilih slot besok, Then HTTP 200, paket menjadi RESCHEDULE_REQUESTED, dan setiap admin aktif menerima notifikasi reschedule_requested.
  - Given slot yang tinggal satu jam dari sekarang, When diajukan, Then HTTP 422 pada errors.slot dan status paket tidak berubah.
- Status: Live

