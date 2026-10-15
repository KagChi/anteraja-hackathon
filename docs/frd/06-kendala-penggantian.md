# FRD Modul ISS — Laporan Kendala, Keputusan, dan Penggantian

Modul ini menutup paket bermasalah: laporan kurir di lapangan, antrean kendala hub
lima arah keputusan, menu pilihan pelanggan lewat WhatsApp, dan pembuatan paket
pengganti.

Aturan bisnis terkait: BR-002, BR-003, BR-004, BR-007, BR-008, BR-012, BR-013
BR-022, BR-025, BR-026.

---

### FR-ISS-001 — Menyiapkan form laporan kendala

- Deskripsi: Sistem HARUS menyediakan daftar kategori kendala dan konteks paket agar kurir dapat membuat laporan dari lapangan.
- Aktor & Otorisasi: kurir pemilik paket.
- Trigger: GET /api/courier/deliveries/{tracking_number}/issue.
- Precondition: paket berstatus COURIER_ASSIGNED yang sudah diterima, CHECKED, atau IN_DELIVERY.
- Input & Validasi: tracking_number pada path.
- Proses / Business Rules: kategori yang ditawarkan adalah enam nilai IssueCategory: kemasan_bocor, kemasan_basah, suhu_naik, pembeli_tidak_ada, alamat_tidak_ditemukan, dan cod_ditolak. Konteks yang dikirim mencakup resi, nama penerima, nama pelapor, batas catatan 200 karakter, dan daftar checklist dari konfigurasi.
- Output / Response: HTTP 200 dengan issueForm berisi tracking, recipient, reporter, noteLimit, options (value dan label), dan checklistItems.
- Error & Edge Cases: paket di luar tiga status yang diizinkan menghasilkan 422 dengan redirect ke layar pengantaran paket itu. Bila sudah ada kendala terbuka pada paket yang sama, sistem mengembalikan 422 dengan pesan bahwa kendala sudah dilaporkan. Paket COURIER_ASSIGNED yang belum diterima menghasilkan 422 dengan redirect ke /courier/tasks.
- Side Effects: tidak ada.
- Postcondition: kurir dapat mengirim laporan.
- Traceability: routes/api.php, Courier/IssueReportController@create, Enums/IssueCategory, config/coldchain.php checklist_items, tests/Feature/CourierFlowTest.php test_courier_report_issue_flow, resources/js/pwa/screens/courier/Issue.tsx.
- Acceptance Criteria:
  - Given paket IN_DELIVERY tanpa kendala terbuka, When form diminta, Then HTTP 200 dengan enam opsi kategori.
  - Given paket yang sudah punya kendala belum diputuskan, When form diminta, Then HTTP 422 dan kurir tidak dapat membuat laporan kedua.
- Status: Live

---

### FR-ISS-002 — Mengirim laporan kendala

- Deskripsi: Sistem HARUS menyimpan pemeriksaan kondisi dan laporan kendala beserta foto, lalu memindahkan paket ke ISSUE_REPORTED dan memberi tahu admin serta mengirim menu penyelesaian ke pelanggan.
- Aktor & Otorisasi: kurir pemilik paket.
- Trigger: POST /api/courier/deliveries/{tracking_number}/issue, multipart dengan foto.
- Precondition: paket berstatus COURIER_ASSIGNED yang sudah diterima, CHECKED, atau IN_DELIVERY, dan belum ada kendala terbuka.
- Input & Validasi: issue_category wajib salah satu IssueCategory; description wajib 10 sampai 200 karakter; photo wajib gambar jpeg, jpg, png, atau webp maksimal 5120 KB; temperature_c wajib bila kategori suhu_naik dan opsional selain itu dengan rentang -60 sampai 40; checklist opsional array yang dinormalkan. prepareForValidation menerima alias lama berupa jenis_kendala, catatan_kendala, atau note, serta memetakan pembayaran_cod_ditolak ke cod_ditolak dan akses_ditutup ke alamat_tidak_ditemukan.
- Proses / Business Rules: validasi status dan kendala terbuka diulang di dalam Action dengan lockForUpdate (BR-004). Bila status COURIER_ASSIGNED, paket terlebih dulu dipindahkan ke CHECKED dengan catatan "Pemeriksaan menemukan kendala". ConditionCheck dibuat dengan is_normal false, checklist, catatan label kategori, suhu, dan waktu pemeriksaan. IssueReport dibuat dengan kategori, deskripsi, foto, dan waktu laporan. Paket lalu dipindahkan ke ISSUE_REPORTED dengan deskripsi keputusan berisi label kategori. Bila terjadi kegagalan, foto yang tersimpan dihapus.
- Output / Response: HTTP 200 dengan message "Laporan terkirim, menunggu keputusan admin." dan objek package yang memuat statusRef.
- Error & Edge Cases: kategori suhu_naik tanpa suhu menghasilkan 422. Foto lebih dari 5 MB atau format lain menghasilkan 422. Status di luar daftar menghasilkan DeliveryNotActionable. Kendala terbuka yang sudah ada menghasilkan DeliveryNotActionable::issueAlreadyOpen. GPS tidak divalidasi pada endpoint ini sehingga laporan kendala tidak pernah diblokir oleh lokasi.
- Side Effects: baris condition_checks dan issue_reports; baris status_logs untuk CHECKED bila perlu dan untuk ISSUE_REPORTED; satu baris notifications QUEUED per admin aktif dengan template issue_reported; satu baris notifications QUEUED untuk pembeli dengan template issue_options berisi kategori dan labelnya.
- Postcondition: paket menunggu keputusan admin dan tidak dapat menerima bukti serah terima sampai kendala diputuskan.
- Traceability: routes/api.php, Courier/IssueReportController@store, Actions/Courier/ReportIssue, Requests/Courier/ReportIssueRequest, DTOs/Courier/ReportIssueData, Support/ChecklistNormalizer, Support/MediaStorage, Support/NotificationQueue::queueIssueOptions, tests/Feature/CourierFlowTest.php test_courier_report_issue_flow, tests/Feature/WhatsAppNotificationFlowTest.php test_courier_issue_report_queues_buyer_resolution_menu, resources/js/pwa/screens/courier/Issue.tsx.
- Acceptance Criteria:
  - Given paket CHECKED tanpa kendala, When kurir mengirim laporan lengkap, Then HTTP 200, paket menjadi ISSUE_REPORTED, satu issue_reports tersimpan, dan notifikasi issue_options terantre untuk pembeli.
  - Given kategori suhu_naik tanpa kolom suhu, When laporan dikirim, Then HTTP 422 dan tidak ada issue_reports baru.
- Status: Live

---

### FR-ISS-003 — Antrean kendala admin

- Deskripsi: Sistem HARUS menampilkan daftar kendala dalam dua tab: aktif (belum diputuskan, urut terlama lebih dulu) dan selesai (sudah diputuskan, urut terbaru lebih dulu).
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/exceptions dari halaman /admin/shipment dengan tab kendala.
- Precondition: tidak ada.
- Input & Validasi: query tab bernilai resolved mengaktifkan tab selesai, selain itu dianggap aktif.
- Proses / Business Rules: relasi yang di-eager load adalah check beserta package, buyer, statusRef, courier, dan decidedByStaff. Setiap baris memuat resi, nama pembeli, nama kurir, label kategori, deskripsi, keputusan dan labelnya, waktu laporan dalam WIB, tanggal berlabel, dan status resolusi yang dihitung IssueReport::resolutionStatus. Status resolusi memetakan keputusan menjadi Menunggu Keputusan, Selesai, Dilanjutkan, Proses Kirim Ulang, Dijadwalkan, atau Pemeriksaan Ulang, bergantung status paket saat ini.
- Output / Response: HTTP 200 dengan tab, issues, dan paginator (total, per_page, current_page, last_page). Ukuran halaman 10.
- Error & Edge Cases: kategori kendala null diganti KEMASAN_BOCOR sebagai cadangan agar tampilan tidak kosong. Tanpa data, daftar kosong dengan paginator nol.
- Side Effects: tidak ada.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Admin/ExceptionController@index, Queries/ExceptionIndexQuery, app/Models/IssueReport.php@resolutionStatus, Enums/ResolutionStatus, tests/Feature/AdminExceptionTest.php test_admin_can_view_exceptions_list, resources/js/pages/admin/Shipment.tsx.
- Acceptance Criteria:
  - Given satu kendala belum diputuskan dan satu sudah diputuskan, When tab aktif diminta, Then hanya kendala belum diputuskan yang muncul.
  - Given kendala dengan keputusan PROCEED pada paket yang masih IN_DELIVERY, When daftar diminta, Then status resolusinya Dilanjutkan dan bukan Selesai.
- Status: Live

---

### FR-ISS-004 — Detail kendala admin

- Deskripsi: Sistem HARUS menampilkan detail satu kendala: paket, laporan, pemeriksaan kondisi, checklist yang sudah dinormalkan, daftar keputusan yang diizinkan kategori, dan timeline.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/exceptions/{tracking_number}.
- Precondition: paket punya minimal satu condition check dengan issue report.
- Input & Validasi: tracking_number pada path.
- Proses / Business Rules: sistem mencari condition check pertama yang punya laporan kendala. Bila tidak ada, permintaan dibatalkan dengan 404 dan pesan "Paket tidak memiliki laporan kendala.". Kategori diambil dengan IssueCategory::fromOrFallback sehingga nilai tak dikenal jatuh ke kemasan_bocor disertai log peringatan. Daftar keputusan yang diizinkan diambil dari IssueCategory::allowedDecisions dan dilengkapi label serta helpText. Checklist dibentuk dengan menggabungkan kunci konfigurasi dan nilai jsonb yang tersimpan, dengan detail "Terbukti" bila jawabannya true dan "Normal" bila tidak. Foto kendala diubah menjadi URL sementara; bila disk tidak mendukung, dipakai route pratinjau.
- Output / Response: HTTP 200 dengan package, issueReport, conditionCheck, statusLabel, tone, resolutionStatus, resolutionTone, category, allowedDecisions, checklist, timeline, reportedAtFormatted, photoTemporaryUrl.
- Error & Edge Cases: resi tanpa kendala menghasilkan 404 dengan pesan khusus. Kategori tak dikenal tidak menggagalkan tampilan karena fallback dan hanya menulis log peringatan.
- Side Effects: log peringatan bila kategori tak dikenal.
- Postcondition: admin dapat memilih keputusan.
- Traceability: routes/api.php, Admin/ExceptionController@show, Enums/IssueCategory, Support/PackageTimelineBuilder, Support/MediaStorage, config/coldchain.php checklist_items, tests/Feature/AdminExceptionTest.php test_admin_can_view_exception_detail, tests/Feature/AdminConsoleWiringTest.php test_the_exception_detail_carries_the_decisions_and_resolution, resources/js/components/ShipmentDetailModal.tsx.
- Acceptance Criteria:
  - Given kendala kategori kemasan_bocor, When detail diminta, Then allowedDecisions memuat lima keputusan termasuk RECHECK.
  - Given resi tanpa laporan kendala, When detail diminta, Then HTTP 404 dengan pesan bahwa paket tidak memiliki laporan kendala.
- Status: Live

---

### FR-ISS-005 — Putusan kendala

- Deskripsi: Sistem HARUS mencatat keputusan admin atas satu kendala beserta alasannya, memvalidasi bahwa keputusan itu diizinkan kategorinya, dan menolak keputusan kedua.
- Aktor & Otorisasi: admin hub.
- Trigger: POST /api/admin/exceptions/{tracking_number}/decision.
- Precondition: paket berstatus ISSUE_REPORTED dan laporan kendala belum punya keputusan.
- Input & Validasi: decision wajib salah satu RETURN, RESEND, PROCEED, RESCHEDULE, RECHECK; decision_reason wajib 10 sampai 2000 karakter; quality_inspection_note opsional maksimal 500 karakter.
- Proses / Business Rules: transaksi dengan lockForUpdate pada baris paket DAN baris laporan kendala (BR-004). Bila status paket bukan ISSUE_REPORTED atau laporan sudah punya keputusan, lempar IssueAlreadyDecided yang menyebut nama pemutus sebelumnya. Keputusan diperiksa terhadap daftar yang diizinkan kategori (BR-012); bila tidak cocok, lempar InvalidArgumentException dengan pesan yang diambil dari lang coldchain.errors.invalid_decision. Empat kolom diisi sekaligus: decision, decision_reason, quality_inspection_note, decided_by, dan decided_at, sesuai constraint ck_issue_reports_decided_together dan ck_issue_reports_reason_with_decision (BR-013). Setelah itu, eksekusi dialihkan ke satu dari lima handler sesuai keputusan.
- Output / Response: HTTP 200 dengan message "Keputusan kendala untuk resi {resi} berhasil disimpan." dan objek package yang memuat statusRef. Bila laporan sudah diputuskan, controller mengembalikan 422 dengan message "Kendala sudah diputuskan sebelumnya." tanpa memanggil Action.
- Error & Edge Cases: keputusan tidak sesuai kategori, misalnya RECHECK untuk pembeli_tidak_ada, ditolak. Alasan kurang dari 10 karakter menghasilkan 422. Laporan yang sudah diputuskan menghasilkan 422 dari controller, atau IssueAlreadyDecided dari Action bila balapan terjadi di antara permintaan bersamaan.
- Side Effects: baris issue_reports diperbarui; baris status_logs baru dari handler; baris notifications QUEUED dari handler; baris packages baru bila keputusan RESEND.
- Postcondition: kendala dianggap selesai diputuskan dan tidak dapat diubah lagi.
- Traceability: routes/api.php, Admin/ExceptionDecisionController, Actions/Issues/ResolveIssue, Requests/Admin/ResolveIssueRequest, DTOs/ResolveIssueData, Enums/IssueDecision, app/Exceptions/IssueAlreadyDecided.php, tests/Feature/AdminExceptionTest.php test_admin_can_resolve_issue_with_return_decision, tests/Feature/AdminConsoleE2ETest.php test_the_kendala_journey_completes_end_to_end, resources/js/components/ShipmentDetailModal.tsx.
- Acceptance Criteria:
  - Given kendala kemasan_bocor tanpa keputusan, When admin memilih RETURN dengan alasan 10 karakter atau lebih, Then HTTP 200 dan kelima kolom keputusan terisi.
  - Given kendala kategori pembeli_tidak_ada, When admin memilih RECHECK, Then permintaan ditolak dan keputusan tidak tersimpan.
- Status: Live

---

### FR-ISS-006 — Efek samping lima jenis putusan

- Deskripsi: Sistem HARUS menjalankan efek yang berbeda untuk tiap keputusan: transisi status, notifikasi ke kurir dan pembeli, dan pembuatan paket pengganti.
- Aktor & Otorisasi: admin hub, dieksekusi di dalam transaksi putusan.
- Trigger: pemanggilan handler dari ResolveIssue setelah keputusan tersimpan.
- Precondition: keputusan sudah tersimpan dan paket berstatus ISSUE_REPORTED.
- Input & Validasi: data keputusan yang sama (decision, decision_reason, quality_inspection_note).
- Proses / Business Rules: pemetaan perilaku per keputusan:
  - RETURN: transisi ISSUE_REPORTED ke RETURNED; kurir menerima instruksi menarik paket ke DC untuk klaim retur; pembeli menerima pesan bahwa pengiriman dibatalkan dan paket dikembalikan.
  - RESEND: transisi ISSUE_REPORTED ke RETURNED lalu pembuatan paket pengganti melalui ReplacementPackageFactory; kurir menerima instruksi bahwa paket asal kembali dan menerima nomor resi pengganti; pembeli menerima pesan bahwa pengiriman ulang disiapkan beserta resi pengganti.
  - PROCEED: transisi ISSUE_REPORTED ke IN_DELIVERY; kurir menerima instruksi melanjutkan pengantaran; pembeli menerima pesan bahwa pengiriman dilanjutkan.
  - RESCHEDULE: transisi ISSUE_REPORTED ke AWAITING_CONFIRMATION sambil mengosongkan courier_id dan courier_accepted_at; konfirmasi PENDING baru dibuat; pembeli menerima template schedule_request.
  - RECHECK: transisi ISSUE_REPORTED ke CHECKED; kurir menerima instruksi memeriksa ulang kondisi fisik; pembeli menerima pesan bahwa paket sedang diperiksa ulang.
- Output / Response: handler tidak mengembalikan respons HTTP; hasilnya terlihat pada status paket, baris notifikasi, dan bila RESEND juga paket baru.
- Error & Edge Cases: pengiriman notifikasi ke kurir dilewati bila paket tidak punya kurir. Paket pengganti yang gagal dibuat karena index unik tidak ditangani khusus di handler RESEND, sehingga dapat muncul sebagai error database (lihat temuan di docs/frd_00.md bagian 11.6).
- Side Effects: baris status_logs; baris notifications QUEUED untuk kurir dan pembeli; baris schedule_confirmations PENDING untuk RESCHEDULE; baris packages, package_items, dan schedule_confirmations untuk RESEND.
- Postcondition: paket berada pada status lanjutan sesuai keputusan, kecuali RESEND yang menghasilkan paket baru berstatus AWAITING_CONFIRMATION.
- Traceability: Actions/Issues/Decisions/ReturnDecisionHandler.php, ProceedDecisionHandler.php, RecheckDecisionHandler.php, RescheduleDecisionHandler.php, ResendDecisionHandler.php, Support/ReplacementPackageFactory.php, tests/Feature/WhatsAppNotificationFlowTest.php, tests/Feature/AdminExceptionTest.php, tests/Feature/ClientFlowTest.php.
- Acceptance Criteria:
  - Given keputusan PROCEED, When handler selesai, Then paket berstatus IN_DELIVERY dan kurir serta pembeli masing-masing menerima satu notifikasi keputusan.
  - Given keputusan RESCHEDULE pada paket yang punya kurir, When handler selesai, Then paket berstatus AWAITING_CONFIRMATION, courier_id null, dan ada satu konfirmasi PENDING baru.
- Status: Live

---

### FR-ISS-007 — Kelayakan dan pembuatan paket pengganti

- Deskripsi: Sistem HARUS membuat paket pengganti HANYA untuk paket asal yang sudah kembali dan diputuskan retur, serta HARUS memastikan hanya ada satu pengganti per paket asal.
- Aktor & Otorisasi: sistem dan admin, dipanggil dari keputusan RESEND atau permintaan pelanggan.
- Trigger: ResendDecisionHandler atau Actions/Client/RequestReplacement.
- Precondition: paket asal berstatus RETURNED atau CLOSED, keputusan kendala terakhir adalah RETURN, dan belum ada baris dengan resend_of_package_id menunjuk paket asal.
- Input & Validasi: tanpa input pengguna; paket asal menentukan seluruh data salinan.
- Proses / Business Rules: ReplacementEligibility::check mengembalikan salah satu dari tiga hasil: paket anak sudah ada (tidak layak, anak dikembalikan), status belum terminal (tidak layak dengan alasan), atau keputusan bukan RETURN (tidak layak dengan alasan). Bila layak, ReplacementPackageFactory membuat paket baru dengan jendela pengiriman hari berikutnya pada jam yang sama, atau jam yang sama pada hari berikutnya bila jadwal asal sudah lewat; item disalin; paket dibuat berstatus CREATED lalu dipindahkan ke AWAITING_CONFIRMATION; konfirmasi PENDING dibuat; notifikasi schedule_request dikirim ke pembeli; dan seluruh admin aktif menerima template resend_requested. Auto-approval dikendalikan config coldchain.client.resend_auto_approve yang bernilai true pada konfigurasi saat ini; bila false, admin hanya menerima notifikasi permintaan tanpa paket dibuat.
- Output / Response: paket pengganti, atau paket asal bila permintaan hanya dicatat untuk persetujuan manual.
- Error & Edge Cases: paket yang belum berstatus terminal menghasilkan ReplacementNotAllowed dengan alasan yang sesuai. Balapan yang menabrak unique index uq_packages_one_replacement ditangkap kode 23505 dan permintaan mengembalikan paket anak yang sudah ada, sehingga pengguna tidak melihat error meski menekan dua kali.
- Side Effects: baris packages, package_items, schedule_confirmations; baris status_logs; baris notifications QUEUED untuk pembeli dan admin.
- Postcondition: paket pengganti menunggu konfirmasi jadwal pembeli dan menunjuk paket asal lewat resend_of_package_id.
- Traceability: Services/Client/ReplacementEligibility.php, Support/ReplacementPackageFactory.php, Actions/Client/RequestReplacement.php, Actions/Issues/Decisions/ResendDecisionHandler.php, database/migrations/0001_01_01_000022_make_replacement_index_unique.php, tests/Feature/ClientFlowTest.php test_issue_replacement_creates_child_package_and_handles_double_tap, tests/Unit/ClientUnitTest.php test_replacement_eligibility_enforces_business_rules, tests/Feature/WhatsAppNotificationFlowTest.php test_resend_option_creates_replacement_only_when_eligible.
- Acceptance Criteria:
  - Given paket RETURNED dengan keputusan RETURN dan belum punya pengganti, When penggantian diminta, Then paket baru berstatus AWAITING_CONFIRMATION dibuat dengan resend_of_package_id menunjuk paket asal.
  - Given paket yang masih IN_DELIVERY, When penggantian diminta, Then ReplacementNotAllowed dilempar dan tidak ada paket baru.
- Status: Live

---

### FR-ISS-008 — Layar kendala pelanggan

- Deskripsi: Sistem HARUS menyajikan ringkasan kendala kepada pelanggan: kategori, keputusan admin, catatan pemeriksaan mutu, kartu suhu, timeline, dan tawaran pengiriman ulang bila layak.
- Aktor & Otorisasi: pembeli terautentikasi pemilik paket, atau guest pemegang tautan token paket tersebut.
- Trigger: GET /api/client/orders/{tracking_number}/issue atau GET /api/guest/{token}/issue.
- Precondition: paket punya minimal satu condition check dengan issue report.
- Input & Validasi: tanpa input. Kepemilikan paket dipastikan middleware client.owned atau client.package.
- Proses / Business Rules: bila paket tidak punya laporan kendala, sistem tidak mengembalikan kesalahan melainkan HTTP 200 dengan redirect_url ke tautan tracking. Isi layar: nomor resi, label status, nama pembeli, nama kurir, daftar produk, label kategori, pesan keputusan dari lang client.issue.decision dengan cadangan pesan pending, catatan mutu bila ada, chip suhu bila suhu terukur, timeline khusus kendala, kanal layanan pelanggan, serta penanda kelayakan pengiriman ulang beserta resi dan tautan jadwal paket pengganti bila sudah ada.
- Output / Response: HTTP 200 dengan success true dan data view.
- Error & Edge Cases: resi milik pembeli lain menghasilkan 404 PACKAGE_NOT_FOUND. Keputusan yang belum ada ditampilkan sebagai pesan pending, bukan kosong.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Api/Client/IssueController@show, Services/Client/IssueViewService, ColdChainSummaryService, ClientTimelineBuilder, ReplacementEligibility, ClientLinkService, lang id untuk client.issue dan client.status, tests/Feature/ClientFlowTest.php, resources/js/pwa/screens/client/Issue.tsx.
- Acceptance Criteria:
  - Given paket dengan kendala yang sudah diputuskan RETURN, When layar dibuka, Then pesan keputusan terisi dan penanda kelayakan penggantian bernilai true.
  - Given paket tanpa laporan kendala, When layar dibuka, Then HTTP 200 dengan redirect_url ke tracking dan tanpa data kendala.
- Status: Live

---

### FR-ISS-009 — Mencatat pilihan penyelesaian pelanggan

- Deskripsi: Sistem HARUS mencatat pilihan penyelesaian kendala yang dikirim pelanggan lewat menu WhatsApp empat opsi, dan HARUS memakai pilihan pertama bila pelanggan menjawab berulang.
- Aktor & Otorisasi: guest pemegang tautan token atau pembeli pemilik paket.
- Trigger: POST /api/guest/{token}/issue/choice atau POST /api/client/{token}/issue/choice dari workflow n8n inbound.
- Precondition: paket punya laporan kendala dan laporan itu belum punya pilihan pembeli.
- Input & Validasi: choice wajib integer antara 1 dan 4; pemetaannya berturut-turut RETURN, RESEND, PROCEED, dan DISCUSS. Nilai di luar rentang menghasilkan 422.
- Proses / Business Rules: transaksi dengan lockForUpdate pada baris paket lalu pada laporan kendala terbaru (BR-004). Bila laporan tidak ada, lempar IssueNotReported. Pilihan disimpan pada buyer_choice dan buyer_responded_at. Seluruh admin aktif menerima notifikasi buyer_issue_choice berisi resi, nilai pilihan, labelnya, dan kategori kendala. Bila pilihan adalah RESEND (opsi 2), sistem memeriksa kelayakan retur; paket pengganti hanya dibuat bila layak, sebaliknya pilihan tetap dicatat dan admin yang melanjutkan keputusan. Bila laporan sudah punya pilihan, pilihan lama yang dikembalikan dengan penanda already_recorded true, dan pembuatan pengganti tetap dievaluasi (BR-025).
- Output / Response: HTTP 200 dengan success true, message "Pilihan penyelesaian kendala berhasil dicatat.", dan data berisi choice, choice_label, already_recorded, replacement_tracking_number, replacement_url.
- Error & Edge Cases: paket tanpa laporan kendala menghasilkan permintaan gagal IssueNotReported. Opsi di luar 1 sampai 4 menghasilkan 422. Jawaban berulang tidak mengubah pilihan pertama.
- Side Effects: kolom buyer_choice dan buyer_responded_at pada issue_reports; baris notifications QUEUED per admin aktif; bila opsi 2 dan layak, ditambah baris paket pengganti beserta notifikasinya.
- Postcondition: pilihan pelanggan tercatat sebagai masukan keputusan admin.
- Traceability: routes/api.php, Api/Client/IssueChoiceController, Actions/Client/RecordIssueChoice, Requests/Client/IssueChoiceRequest, Enums/BuyerIssueChoice, tests/Feature/WhatsAppNotificationFlowTest.php test_buyer_can_pick_return_option_from_whatsapp dan test_first_buyer_choice_wins_when_reply_is_repeated, deploy/n8n/README.md tabel last_template.
- Acceptance Criteria:
  - Given kendala tanpa pilihan pembeli, When pelanggan mengirim "2", Then choice RESEND tersimpan, already_recorded false, dan admin menerima notifikasi buyer_issue_choice.
  - Given kendala yang sudah punya pilihan RETURN, When pelanggan mengirim "3", Then respons mengembalikan pilihan lama RETURN dengan already_recorded true dan kolom buyer_choice tidak berubah.
- Status: Live

---

### FR-ISS-010 — Permintaan pengiriman ulang dari aplikasi

- Deskripsi: Sistem HARUS menerima pengajuan pengiriman ulang dari aplikasi pelanggan dan menjawab dengan tautan jadwal paket pengganti bila paket baru berhasil dibuat.
- Aktor & Otorisasi: pembeli pemilik paket, atau guest pemegang tautan pada jalur legacy.
- Trigger: POST /api/client/orders/{tracking_number}/issue/resend (jalur akun) dan POST /api/client/{token}/issue/resend (jalur legacy).
- Precondition: paket sudah memenuhi syarat retur atau sudah punya paket pengganti.
- Input & Validasi: tanpa body. Kepemilikan paket dipastikan middleware.
- Proses / Business Rules: permintaan didelegasikan ke Actions/Client/RequestReplacement. Bila hasilnya paket yang berbeda dari paket asal, controller membalas 201 dengan message pengiriman ulang berhasil, redirect_url ke layar jadwal paket pengganti, replacement_tracking_number, dan replacement_token. Bila hasilnya paket asal yang sama, artinya permintaan hanya dicatat untuk persetujuan manual, dan controller membalas 200 dengan pesan bahwa permintaan diajukan ke tim operasional.
- Output / Response: HTTP 201 dengan redirect_url dan data pengganti, atau HTTP 200 dengan message permintaan dicatat.
- Error & Edge Cases: paket yang belum layak menghasilkan ReplacementNotAllowed. Permintaan berulang mengembalikan paket pengganti yang sudah ada, sehingga pengguna tidak melihat error dan tidak menghasilkan resi kedua.
- Side Effects: sama dengan FR-ISS-007.
- Postcondition: pelanggan mengarahkan diri ke konfirmasi jadwal paket pengganti.
- Traceability: routes/api.php, Api/Client/ReplacementController, Actions/Client/RequestReplacement, tests/Feature/ClientFlowTest.php test_issue_replacement_creates_child_package_and_handles_double_tap, tests/Feature/WhatsAppNotificationFlowTest.php test_resend_option_creates_replacement_when_package_is_returned, resources/js/pwa/screens/client/Issue.tsx.
- Acceptance Criteria:
  - Given paket RETURNED yang memenuhi syarat, When permintaan pengiriman ulang dikirim, Then HTTP 201 dengan replacement_tracking_number dan redirect_url berisi token paket baru.
  - Given paket yang masih IN_DELIVERY, When permintaan dikirim, Then permintaan ditolak ReplacementNotAllowed dan tidak ada paket baru.
- Status: Live

