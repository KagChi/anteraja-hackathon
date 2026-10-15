# FRD Modul RPT — Pelaporan, Ekspor, dan Media

Modul ini adalah sisi baca operasi: KPI harian, riwayat paket terminal, tiga
ekspor CSV, dan penyajian foto bukti dari disk privat.

Aturan bisnis terkait: BR-005, BR-022, BR-023.

---

### FR-RPT-001 — KPI laporan harian

- Deskripsi: Sistem HARUS menghitung enam angka operasi untuk satu tanggal Jakarta: total pengiriman, persentase kapasitas armada, jumlah terkirim, tingkat keberhasilan, jumlah kendala, dan tingkat insiden.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/reports dari halaman /admin/report.
- Precondition: tidak ada.
- Input & Validasi: date opsional format Y-m-d dengan bawaan tanggal hari ini; category opsional string; page minimal 1.
- Proses / Business Rules: rentang hari dihitung memakai batas hari Jakarta lalu dikonversi ke UTC, dan kueri memakai rentang setengah terbuka yaitu lebih besar sama dengan start dan kurang dari end (BR-005). Tiga angka diambil dalam satu selectOne: paket dengan scheduled_at dalam rentang, paket yang punya baris delivery dalam rentang, dan laporan kendala dalam rentang. Utilisasi dihitung terhadap config coldchain.daily_fleet_capacity yang bernilai 1500. Hasil untuk tanggal lampau di-cache 600 detik; tanggal hari ini selalu dihitung ulang. Daftar kendala hari itu dipaginasi lima baris, dengan label kategori mendapat tambahan suhu bila kategori suhu_naik dan suhunya ada. Respons juga memuat daftar kategori kendala yang muncul pada tanggal itu dan label tanggal berbahasa Indonesia.
- Output / Response: HTTP 200 dengan date, dateLabel, kpis, issues, categoriesOnDate, selectedCategory, dan paginator.
- Error & Edge Cases: hari tanpa data menghasilkan seluruh KPI nol, bukan error. Kategori yang dikirim tetapi tidak ada pada tanggal itu menghasilkan daftar kendala kosong. Format tanggal salah menghasilkan 422.
- Side Effects: cache report_kpis_{tanggal} selama 600 detik untuk tanggal lampau.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Admin/ReportController@index, Queries/DailyReportQuery::getKpis, Requests/Admin/IndexReportRequest, DTOs/ReportFilters, Support/DisplayTime, config/coldchain.php daily_fleet_capacity, tests/Feature/AdminExceptionTest.php test_admin_can_view_daily_report_and_export_csv, resources/js/pages/admin/Report.tsx.
- Acceptance Criteria:
  - Given sepuluh paket dijadwalkan hari ini dan empat di antaranya punya bukti pengantaran, When laporan diminta, Then total_shipments 10, delivered_count 4, dan success_rate_pct 40.
  - Given tanggal yang tidak memiliki data, When laporan diminta, Then seluruh KPI bernilai 0 dan daftar kendala kosong.
- Status: Live

---

### FR-RPT-002 — Ekspor laporan harian

- Deskripsi: Sistem HARUS mengunduhkan laporan kendala satu hari sebagai berkas CSV yang aman dibuka di Excel.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/reports/export.
- Precondition: tidak ada.
- Input & Validasi: date dan category mengikuti IndexReportRequest.
- Proses / Business Rules: laporan diambil untuk rentang hari Jakarta yang sama, dengan penyaringan kategori bila ada, diurutkan waktu laporan menurun, dan memuat relasi paket, pembeli, kurir, serta staf pemutus. Kolom CSV adalah RESI, PEMBEMBELI, KURIR, JENIS_KENDALA, KEPUTUSAN, STATUS_RESOLUSI, dan DILAPORKAN. Nama berkas berbentuk laporan-harian-{tanggal}.csv. StreamedCsv menulis BOM UTF-8 agar Excel membaca aksara Indonesia dengan benar dan menyaring formula injection dengan menambahkan apostrof di depan sel yang dimulai karakter sama dengan, tambah, minus, at, tab, atau carriage return (BR-023).
- Output / Response: StreamedResponse dengan Content-Type text/csv dan nama berkas sesuai tanggal.
- Error & Edge Cases: hari tanpa kendala tetap menghasilkan CSV berisi baris header saja. Kategori suhu_naik menyertakan suhu dalam label sehingga pembaca dapat membedakan.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Admin/ReportExportController, Support/Csv/StreamedCsv.php, tests/Feature/AdminExceptionTest.php test_admin_can_view_daily_report_and_export_csv, tests/Unit/ColdChainUnitTest.php test_csv_injection_prevention.
- Acceptance Criteria:
  - Given tiga kendala pada satu tanggal, When ekspor diminta, Then respons berisi berkas CSV dengan header ditambah tiga baris data.
  - Given catatan yang dimulai dengan tanda sama dengan, When diekspor, Then isi sel diberi prefiks apostrof sehingga tidak dieksekusi sebagai rumus.
- Status: Live

---

### FR-RPT-003 — Pencarian riwayat paket

- Deskripsi: Sistem HARUS menampilkan paket terminal dengan pencarian resi, nama pembeli, dan nama kurir, serta penyaringan status, beserta waktu penyelesaian.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/history dari halaman /admin/history.
- Precondition: tidak ada.
- Input & Validasi: q opsional maksimal 100; status opsional salah satu RECEIVED, RETURNED, CLOSED, all; page minimal 1.
- Proses / Business Rules: hanya paket berstatus RECEIVED, RETURNED, atau CLOSED yang masuk. Waktu penyelesaian dihitung sebagai COALESCE deliveries.delivered_at atau waktu log status terakhir menuju RETURNED, sehingga paket retur tetap punya waktu penyelesaian. Pencarian memakai ILIKE dengan escape karakter khusus agar tanda persen tidak memperluas hasil. Urutan menempatkan waktu penyelesaian menurun dengan NULLS LAST lalu id menurun. Label waktu disusun berisi tanggal berlabel seperti "Hari ini" atau "Besok" dan jam dalam WIB. Ukuran halaman 15.
- Output / Response: HTTP 200 dengan history, filters, dan paginator. Setiap baris memuat id, tracking, buyer, courier (atau "Belum Ditugaskan"), completed_at, status, statusKey, dan tone.
- Error & Edge Cases: basis data tanpa paket terminal menghasilkan daftar kosong. Paket tanpa bukti pengantaran dan tanpa log RETURNED menampilkan completed_at "-".
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Admin/HistoryController@index, Queries/HistoryQuery, Requests/Admin/IndexHistoryRequest, DTOs/HistoryFilters, Support/DisplayTime, tests/Feature/AdminExceptionTest.php test_admin_can_view_history_and_export_csv, resources/js/pages/admin/History.tsx.
- Acceptance Criteria:
  - Given paket RECEIVED dengan bukti pengantaran dan paket RETURNED dengan log retur, When riwayat diminta, Then kedua baris punya waktu penyelesaian terisi.
  - Given pencarian nama kurir yang tidak ada, When riwayat diminta, Then daftar kosong dan bukan seluruh riwayat.
- Status: Live

---

### FR-RPT-004 — Ekspor riwayat

- Deskripsi: Sistem HARUS mengunduhkan riwayat paket terminal sebagai CSV sesuai filter yang aktif di layar.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/history/export.
- Precondition: tidak ada.
- Input & Validasi: q, status, dan page mengikuti IndexHistoryRequest.
- Proses / Business Rules: memakai kueri riwayat yang sama, tanpa paginasi, sehingga hasil filter konsisten dengan layar. Kolom CSV adalah RESI, PEMBEMBELI, KURIR, SELESAI, dan STATUS. Nama berkas berbentuk riwayat-{Ymd-Hi}.csv memakai waktu Jakarta. Sanitasi CSV dan BOM mengikuti BR-023.
- Output / Response: StreamedResponse CSV dengan nama berkas berstempel waktu.
- Error & Edge Cases: filter yang tidak menghasilkan baris tetap menghasilkan CSV dengan header.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Admin/HistoryExportController, Queries/HistoryQuery::get, Support/Csv/StreamedCsv.php, tests/Feature/AdminExceptionTest.php test_admin_can_view_history_and_export_csv.
- Acceptance Criteria:
  - Given filter status RETURNED, When ekspor diminta, Then CSV hanya memuat paket RETURNED.
  - Given riwayat kosong, When ekspor diminta, Then berkas tetap berisi baris header.
- Status: Live

---

### FR-RPT-005 — Ekspor antrean shipment

- Deskripsi: Sistem HARUS mengunduhkan antrean shipment aktif sebagai CSV yang memakai filter yang sama seperti layar antrean.
- Aktor & Otorisasi: admin hub.
- Trigger: GET /api/admin/shipments/export.
- Precondition: tidak ada.
- Input & Validasi: q, status, courier, schedule, date, source, dan per_page mengikuti IndexShipmentRequest.
- Proses / Business Rules: memakai ShipmentIndexQuery::get sehingga aturan filter, termasuk tanggal eksplisit yang mengalahkan jadwal relatif, berlaku identik dengan layar. Kolom CSV adalah RESI, PEMBEMBELI, AREA, JADWAL, KURIR, STATUS, dan BERAT_KG. Kurir tanpa penugasan ditulis "Belum Ditugaskan". Nama berkas berbentuk shipment-{Ymd-Hi}.csv memakai waktu Jakarta. Sanitasi CSV mengikuti BR-023.
- Output / Response: StreamedResponse CSV.
- Error & Edge Cases: antrean kosong tetap menghasilkan CSV dengan header. Paket berstatus CLOSED dan RETURNED tidak masuk ekspor karena kueri antrean mengecualikannya.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Admin/ShipmentExportController, Queries/ShipmentIndexQuery, Support/DisplayTime, Support/Csv/StreamedCsv.php, tests/Feature/AdminShipmentTest.php test_admin_can_export_shipments_csv.
- Acceptance Criteria:
  - Given dua paket aktif dan satu paket CLOSED, When ekspor diminta, Then CSV memuat dua baris data.
  - Given filter kurir tertentu, When ekspor diminta, Then CSV hanya memuat paket kurir itu.
- Status: Live

---

### FR-RPT-006 — Pratinjau foto bukti

- Deskripsi: Sistem HARUS menyajikan foto bukti yang tersimpan pada disk privat kepada konsol admin, dan HARUS menolak permintaan di luar pola path yang sah.
- Aktor & Otorisasi: admin hub dengan middleware staff.role:ADMIN.
- Trigger: GET /api/media/preview dengan parameter path.
- Precondition: berkas ada pada disk local.
- Input & Validasi: path wajib cocok pola folder bernama huruf kecil, angka, garis bawah, atau tanda hubung; diikuti tahun empat digit, bulan dua digit, dan nama berkas alfanumerik dengan ekstensi. Pola apa pun di luar itu, termasuk upaya penelusuran direktori atau path absolut, ditolak.
- Proses / Business Rules: hanya berkas yang lolos pola dan benar-benar ada pada disk local yang disajikan. Endpoint ini ada karena URL sementara tidak dapat dibuat untuk disk lokal, sehingga MediaStorage jatuh ke route ini bila pembuatan URL sementara gagal.
- Output / Response: StreamedResponse berisi isi berkas dari disk.
- Error & Edge Cases: path dengan urutan titik-titik untuk keluar folder menghasilkan 404 tanpa menyentuh disk. Berkas yang tidak ada menghasilkan 404. Foto lama yang sudah dihapus menghasilkan 404, dan pelanggan atau admin melihat kartu tanpa gambar.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Media/MediaPreviewController, Support/MediaStorage::temporaryUrl, tests/Feature/AdminConsoleWiringTest.php test_the_media_preview_serves_only_proof_paths dan test_a_stored_photo_resolves_to_the_preview_route, resources/js/components/ShipmentDetailModal.tsx.
- Acceptance Criteria:
  - Given path bukti yang sah dan berkasnya ada, When pratinjau diminta, Then berkas tersaji dan bukan halaman HTML.
  - Given path yang mencoba keluar folder, When pratinjau diminta, Then HTTP 404 dan disk tidak diakses.
- Status: Live

