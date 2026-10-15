# PRD — Anteraja Frozen

Versi dokumen: 1.0 (as-is, reverse-engineered dari kode)
Tanggal: 15 Oktober 2026
Cakupan: seluruh produk yang benar-benar ada di repositori ini
Metode: pembacaan langsung kode, migrasi, dan test. Setiap klaim fakta
menyebutkan berkas sumbernya. Klaim yang bukan fakta diberi label [INFERENSI],
dan hal yang tidak dapat dibuktikan ditandai [TIDAK TERVERIFIKASI].

Dokumen turunan: docs/frd_00.md (FRD master), docs/frd/ (FRD per modul),
docs/_analysis/00-inventory.md (feature inventory), docs/_analysis/01-actors-domain.md
(aktor dan domain), docs/_analysis/02-verification.md (hasil verifikasi mandiri).

---

## 1. Ringkasan Produk

Anteraja Frozen adalah satu aplikasi Laravel 13 yang melayani empat permukaan
sekaligus:

1. Admin console berbentuk SPA Inertia + React untuk hub operasional
   (routes/web.php, resources/js/pages/admin/*).
2. Aplikasi Kurir berupa PWA yang dipasang dari browser, satu SPA React yang
   berbicara ke JSON API dengan bearer token realm courier
   (resources/js/pwa/screens/courier/*, routes/web.php).
3. Aplikasi Pelanggan, PWA kedua dari origin yang sama, sekaligus menjadi
   storefront produk beku (resources/js/pwa/screens/client/*, config/pwa.php).
4. Landing page publik plus blog yang menjadi pintu masuk dan alat lacak resi
   (routes/web.php, resources/js/pages/Landing.tsx, resources/js/data/posts.ts).

Masalah yang dipecahkan adalah pengiriman produk beku: barang rusak bukan karena
freezer mati sekali, melainkan karena setiap perpindahan suhu yang tidak
tercatat. Produk menjawabnya dengan tiga hal yang terlihat jelas di kode:

- Pemeriksaan fisik sebelum berangkat wajib, dengan checklist dan suhu tercatat
  (config/coldchain.php, Actions/Courier/StartDelivery.php).
- Validasi lokasi serah terima dihitung server, bukan klien
  (Services/Gps/GpsValidator.php, Services/Gps/GeofenceEvaluator.php).
- Riwayat status tidak bisa ditulis langsung aplikasi; database yang menjaga
  matriks transisinya (migrasi 0001_01_01_000016_add_package_status_guard.php).

Nilai utama produk, disimpulkan dari perilaku yang ada:

- Rantai dingin yang dapat diaudit: setiap titik mencatat suhu, checklist, foto,
  dan siapa pelakunya (condition_checks, deliveries, status_logs).
- Serah terima yang tidak bisa diklaim asal: radius titik penerima dinilai server
  dan bukti foto disimpan ke disk privat dengan pratinjau ber-token
  (Support/MediaStorage.php, Media/MediaPreviewController.php).
- Kendala yang ditangani manusia dengan jejak lengkap: antrean kendala, keputusan
  admin beralasan wajib, dan riwayat keputusan
  (Actions/Issues/ResolveIssue.php, migrasi 0001_01_01_000012).
- Percakapan pelanggan yang nyata: seluruh notifikasi keluar lewat outbox dan
  n8n + WAHA, dan balasan pelanggan diterjemahkan kembali menjadi aksi
  (Support/NotificationQueue.php, deploy/n8n/README.md).

## 2. Tujuan dan Non-Tujuan

### Tujuan yang terbukti dari fitur yang ada

| Tujuan | Bukti fitur |
| --- | --- |
| Menjalankan FSM paket yang tidak bisa dilanggar dari aplikasi | FSM matrix di package_status_transitions, trigger packages_validate_status |
| Memberi hub satu antrean kerja: shipment, kendala, GPS, laporan, riwayat | lima halaman admin di routes/web.php, API di routes/api.php |
| Memberi kurir alur lapangan lengkap: terima tugas, berangkat, tiba, bukti, kendala, riwayat, profil | routes/api.php grup courier, app/Actions/Courier/* |
| Membuka pesanan mandiri pelanggan dari katalog sampai jadwal | Api/Client/OrderController, Actions/Client/CreateClientOrder |
| Mengirim seluruh pemberitahuan pelanggan lewat WhatsApp tanpa menyimpan kredensial WA di aplikasi | Support/NotificationQueue, Jobs/DispatchNotificationToN8n, config coldchain.n8n |
| Menyediakan pelacakan publik tanpa membocorkan identitas penerima | Api/Tracking/TrackController, PublicTrackingTest |

### Non-tujuan (tidak ada di kode, jadi di luar cakupan produk versi ini)

| Non-tujuan | Dasar |
| --- | --- |
| Pembayaran daring atau dompet saldo | tidak ada tabel, endpoint, atau enum pembayaran; COD hanya muncul sebagai kategori kendala cod_ditolak |
| Manajemen staf dan CRUD kurir dari UI | tidak ada route atau controller staf; data staf hanya dari seeder |
| Manajemen produk/katalog dari UI admin | tidak ada endpoint tulis untuk products; kategori dan harga hanya bisa disemai |
| Multi-hub atau multi-tenant | nama hub adalah satu nilai konfigurasi (config coldchain.hub_name) |
| Cakupan nasional | copy landing page menyatakan Jabodetabek saja (resources/js/data/landing.ts) |
| Registrasi kurir mandiri | kurir hanya bisa masuk dengan kredensial staf yang sudah ada |
| Aplikasi native | kedua aplikasi adalah PWA; tidak ada paket Android atau iOS yang dibangun |

## 3. Persona dan User Journey

### 3.1 Admin Hub (staff role ADMIN)

Masuk lewat halaman /login yang dirender Inertia, lalu autentikasi sesungguhnya
ke POST /api/auth/login (routes/web.php, Auth/SessionController.php). Setelah
masuk, ia bekerja di lima halaman: Shipment, Report, History, GpsChecks,
Geofence.

Perjalanan utama:

1. Membuat shipment: isi pembeli (baru atau pilih yang ada), item produk, jadwal,
   berat, dan instruksi penanganan. Aksi membuat paket berstatus CREATED lalu
   langsung memindahkannya ke AWAITING_CONFIRMATION dan mengirim notifikasi
   schedule_request ke pembeli (Actions/Packages/CreatePackage.php).
2. Menunggu pembeli. Kalau lewat dua jam tanpa jawaban, scheduler
   confirmations:remind-unanswered menaikkan pengingat ke admin
   (app/Console/Commands/RemindUnansweredConfirmations.php).
3. Menugaskan kurir saat status CONFIRMED dan belum ada kurir
   (Actions/Packages/AssignCourier.php, satu-satunya jalan ke COURIER_ASSIGNED).
4. Menangani kendala: melihat antrean, membaca checklist dan foto, lalu memilih
   satu dari lima keputusan yang diizinkan kategori kendala tersebut
   (Actions/Issues/ResolveIssue.php, Enums/IssueCategory.php).
5. Menyusun ulang jadwal untuk paket RESCHEDULE_REQUESTED
   (Actions/Admin/RescheduleShipment.php).
6. Meninjau validasi GPS: melihat verdict BLOCKED atau SUSPECTED dan permintaan
   tinjauan kurir, lalu menyetujui atau menolak dengan alasan minimal 10 karakter
   (Admin/GpsCheckController.php, Admin/GpsDecisionRequest.php).
7. Mengubah radius geofence per segmen layanan
   (Admin/GeofenceController.php, UpdateGeofenceRequest.php).
8. Mengekspor CSV shipment, laporan harian, dan riwayat
   (tiga controller ekspor memakai Support/Csv/StreamedCsv.php).

[INFERENSI] Karena tidak ada fitur pembatasan per-hub atau per-shift, seluruh
admin melihat antrean yang sama.

### 3.2 Kurir

Masuk lewat aplikasi kurir dengan email dan password staf, menerima token
Sanctum berumur 30 hari ber-ability courier
(Api/Courier/AuthController.php). Semua aksinya dijaga Policy yang hanya
mengizinkan kurir yang ditugaskan pada paket itu (app/Policies/PackagePolicy.php).

Perjalanan utama:

1. Papan tugas memisahkan tugas baru dan tugas aktif; tugas baru adalah status
   COURIER_ASSIGNED yang belum di-accept (Queries/CourierTaskQuery.php).
2. Menerima atau menolak tugas. Menolak memindahkan paket kembali ke CONFIRMED
   dan melepas courier_id, lalu memberi tahu semua admin aktif
   (Actions/Courier/RejectTask.php).
3. Melihat estimasi jarak dan ETA ke pembeli (Courier/TaskDistanceController.php).
4. Berangkat: checklist fisik wajib diisi; bila ada jawaban true pada checklist,
   sistem menolak berangkat dan mengarahkan ke laporan kendala. Bila normal,
   ConditionCheck tersimpan, status pindah CHECKED lalu IN_DELIVERY, dan
   milestone DEPARTED tercatat (Actions/Courier/StartDelivery.php).
5. Tiba di lokasi: milestone ARRIVED dicatat beserta koordinat; radius TIDAK
   ditegakkan di titik ini karena kurir masih boleh berjalan kaki
   (Courier/MarkArrivedController.php).
6. Bukti serah terima: foto wajib, nama penerima wajib, hubungan penerima wajib,
   dan radius geofence DITEGAKKAN di titik ini karena paket berpindah tangan
   (Courier/ProofController.php, Actions/Courier/ConfirmDelivery.php).
7. Lapor kendala bila ada masalah: foto wajib, deskripsi 10-200 karakter, suhu
   wajib bila kategori suhu_naik (Courier/IssueReportController.php,
   Requests/Courier/ReportIssueRequest.php).
8. Bila GPS memblokir, kurir dapat meminta tinjauan hub; permintaan bersifat
   idempoten (Courier/GpsReviewController.php, GpsValidator::requestReview).

### 3.3 Pembeli (Buyer)

Dua jalur masuk yang hidup berdampingan:

- Jalur akun: registrasi atau login di aplikasi pelanggan, memakai nomor HP
  sebagai identitas dan password yang dipilih sendiri (tanpa OTP)
  (Api/Client/AuthController.php, Requests/Api/Client/RegisterRequest.php).
- Jalur tautan: tautan berisi client_token yang dibawa notifikasi WhatsApp,
  bisa langsung dipakai tanpa login, dan bisa diklaim menjadi akun
  (Services/Client/ClientAccessService.php, Api/Client/AuthController@claim).

Perjalanan utama:

1. Menjelajah katalog tanpa login (Api/Client/CatalogController.php).
2. Checkout: memilih produk, layanan REGULER atau INSTANT_FROZEN, tanggal, dan
   jendela jam. Alamat penerima sekaligus menjadi profil pengiriman yang
   disimpan balik ke baris buyers supaya checkout berikutnya terisi otomatis dan
   geofence punya koordinat (Actions/Client/CreateClientOrder.php).
3. Menerima pesan WhatsApp berisi permintaan konfirmasi jadwal, lalu menjawab
   "siap" di WhatsApp atau menekan konfirmasi di aplikasi. Keduanya berujung ke
   Actions/Client/ConfirmSchedule.php.
4. Minta jadwal lain bila waktunya tidak cocok; aturannya minimal 2 jam dari
   sekarang dan maksimal 14 hari ke depan (Requests/Client/RescheduleRequest.php).
5. Melacak kiriman: kartu cold chain, timeline, dan tombol menghubungi kurir
   hanya terbuka saat status IN_DELIVERY
   (Services/Client/TrackingViewService.php).
6. Saat kendala, memilih penyelesaian lewat menu WhatsApp atau menekan minta
   pengiriman ulang di aplikasi bila memenuhi syarat retur
   (Actions/Client/RecordIssueChoice.php, Actions/Client/RequestReplacement.php).

### 3.4 Visitor publik

Datang ke landing page, membaca layanan dan jangkauan, memakai panel lacak resi
tanpa login, membuka atau memasang aplikasi, atau membaca blog. Endpoint lacaknya hanya
mengembalikan status, jendela, kota tingkat kabupaten, dan timeline berlabel
tetap: tanpa nama, tanpa nomor telepon, tanpa alamat jalan, tanpa teks bebas dari
staf atau kurir (Api/Tracking/TrackController.php).

### 3.5 Sistem otomasi (n8n + WAHA + LLM)

Aplikasi tidak pernah memanggil WhatsApp secara langsung. Ia menulis baris ke
tabel notifications, dan job DispatchNotificationToN8n mengirim template plus
payload ke webhook n8n. n8n merender pesan, mengirim lewat WAHA, dan menyimpan
state percakapan di tabel anteraja_chat_state. Balasan pelanggan masuk kembali ke
webhook inbound n8n, diterjemahkan (LLM dengan fallback aturan), lalu dipanggil
ke endpoint guest aplikasi. Untuk menghubungkan nomor pengirim dengan paket, n8n
memanggil POST /api/internal/whatsapp/lookup dengan header X-Anteraja-Token
(deploy/n8n/README.md, Support/NotificationQueue.php, Jobs/DispatchNotificationToN8n.php).

## 4. Daftar Fitur per Surface

Prioritas memakai istilah yang diminta: Existing-Core untuk fitur yang menjadi
tulang punggung alur, Existing-Supporting untuk penunjang. Status Live, Partial,
atau [TIDAK TERVERIFIKASI].

### 4.1 Admin SPA

| Fitur | Nilai | Aktor | Prioritas | Status |
| --- | --- | --- | --- | --- |
| Antrean shipment dengan filter status, kurir, jadwal, tanggal, sumber | satu antrean kerja hub | Admin | Existing-Core | Live |
| Form pembuatan shipment plus master data produk/pembeli/kurir | titik masuk paket dari hub | Admin | Existing-Core | Live |
| Detail shipment: timeline, foto bukti, tautan klien, label status | investigasi satu paket | Admin | Existing-Core | Live |
| Penugasan kurir | menggerakkan paket dari CONFIRMED ke lapangan | Admin | Existing-Core | Live |
| Jadwal ulang oleh admin | menutup permintaan jadwal ulang pembeli | Admin | Existing-Core | Live |
| Antrean kendala dan keputusan lima arah | menyelesaikan paket bermasalah | Admin | Existing-Core | Live |
| Laporan harian dengan enam KPI dan ekspor CSV | pemantauan operasi harian | Admin | Existing-Core | Live |
| Riwayat paket terminal dengan pencarian dan ekspor CSV | audit dan arsip | Admin | Existing-Supporting | Live |
| Konsol validasi GPS plus putusan tinjauan | menutup celah kecurangan lokasi | Admin | Existing-Core | Live |
| Pengaturan radius geofence per segmen | menyesuaikan toleransi lapangan | Admin | Existing-Supporting | Live |
| Ekspor CSV shipment | serah terima data ke luar sistem | Admin | Existing-Supporting | Live |
| Pratinjau foto bukti dari disk privat | membuktikan serah terima | Admin | Existing-Supporting | Live |

### 4.2 App Kurir

| Fitur | Nilai | Aktor | Prioritas | Status |
| --- | --- | --- | --- | --- |
| Login kurir dengan bearer token 30 hari | masuk sekali, kerja seharian | Kurir | Existing-Core | Live |
| Papan tugas baru dan aktif | tahu apa yang harus dikerjakan | Kurir | Existing-Core | Live |
| Terima dan tolak tugas dengan alasan | kurir mengendalikan penugasan | Kurir | Existing-Core | Live |
| Estimasi jarak dan ETA | prioritas rute | Kurir | Existing-Supporting | Live |
| Berangkat dengan checklist fisik dan suhu | menjaga rantai dingin sejak awal | Kurir | Existing-Core | Live |
| Milestone tiba di lokasi | jejak perjalanan | Kurir | Existing-Core | Live |
| Bukti serah terima dengan foto, nama, hubungan, dan validasi radius | bukti yang bisa diaudit | Kurir | Existing-Core | Live |
| Laporan kendala lapangan berkategori | memicu penanganan hub | Kurir | Existing-Core | Live |
| Permintaan tinjauan GPS | jalan keluar saat diblokir | Kurir | Existing-Core | Live |
| Riwayat pengantaran | rekap harian | Kurir | Existing-Supporting | Live |
| Profil dengan statistik harian | motivasi dan identitas | Kurir | Existing-Supporting | Live |
| Pemilih tema sistem/terang/gelap | kenyamanan lapangan | Kurir | Existing-Supporting | Live |

### 4.3 App Frozen / Customer

| Fitur | Nilai | Aktor | Prioritas | Status |
| --- | --- | --- | --- | --- |
| Registrasi dan login nomor HP | identitas pembeli | Pembeli | Existing-Core | Live |
| Katalog produk beku | etalase | Publik | Existing-Core | Live |
| Detail produk dengan catatan penanganan | informasi pembelian | Publik | Existing-Supporting | Live |
| Keranjang lokal | alur belanja | Pembeli | Existing-Core | Live |
| Checkout dengan penerima, layanan, dan jadwal | pesanan masuk ke hub | Pembeli | Existing-Core | Live |
| Resi dan struk pesanan | bukti pesanan | Pembeli | Existing-Core | Live |
| Daftar pesanan dengan tab aktif, selesai, kendala | status pesanan | Pembeli | Existing-Core | Live |
| Halaman tracking dengan kartu cold chain dan timeline | ketenangan selama kirim | Pembeli | Existing-Core | Live |
| Konfirmasi jadwal | menggerakkan paket | Pembeli | Existing-Core | Live |
| Permintaan jadwal ulang berbasis slot | fleksibilitas | Pembeli | Existing-Core | Live |
| Layar kendala dengan keputusan admin | transparansi masalah | Pembeli | Existing-Core | Live |
| Minta pengiriman ulang | pemulihan | Pembeli | Existing-Core | Live |
| Klaim tautan menjadi akun | konversi dari tautan WhatsApp | Pembeli | Existing-Supporting | Live |
| Layanan pelanggan lewat tautan WhatsApp | eskalasi manusia | Pembeli | Existing-Supporting | Live |

### 4.4 Landing dan Blog

| Fitur | Nilai | Aktor | Prioritas | Status |
| --- | --- | --- | --- | --- |
| Landing page dengan hero, statistik, layanan, keunggulan, jangkauan, tarif, kartu aplikasi | akuisisi | Publik | Existing-Core | Live |
| Panel lacak resi di landing | layanan mandiri | Publik | Existing-Core | Live |
| Blog index dan artikel dari module TypeScript | konten dan SEO | Publik | Existing-Supporting | Live |

### 4.5 Guest Tracking (tautan token)

| Fitur | Nilai | Aktor | Prioritas | Status |
| --- | --- | --- | --- | --- |
| Resolusi token menjadi satu paket dengan TTL 30 hari untuk status terminal | akses tanpa login | Guest | Existing-Core | Live |
| Layar otomatis: jadwal, kendala, atau tracking sesuai status dan kondisi | tidak ada keputusan yang salah | Guest | Existing-Core | Live |
| Konfirmasi dan jadwal ulang tanpa akun | menghilangkan hambatan | Guest | Existing-Core | Live |
| Menu pilihan penyelesaian kendala opsi 1 sampai 4 | menutup kendala dari WhatsApp | Guest | Existing-Core | Live |
| Rate limit 20 kegagalan token per IP per menit | mencegah enumerasi token | Sistem | Existing-Supporting | Live |

### 4.6 WhatsApp Bot (n8n + WAHA)

| Fitur | Nilai | Aktor | Prioritas | Status |
| --- | --- | --- | --- | --- |
| Outbox notifikasi dengan konteks kirim lengkap | satu jalur keluar yang dapat dipertanggungjawabkan | Sistem | Existing-Core | Live |
| Job pengiriman dengan retry 3 kali dan backoff 10 lalu 30 detik | tahan gangguan n8n | Sistem | Existing-Core | Live |
| Template notifikasi: schedule_request, schedule_rescheduled, on_the_way, courier_arrived, delivered, issue_options, issue_reported, issue_decision, issue_decision_update, task_assigned, task_rejected, resend_requested, buyer_issue_choice, confirmation_overdue | komunikasi pelanggan | Sistem | Existing-Core | Live |
| Terjemahan balasan pelanggan menjadi aksi lewat state anteraja_chat_state | bot yang tidak menebak | Sistem | Existing-Core | Live |
| Resolusi LID WhatsApp ke nomor telepon | balasan tetap sampai | Sistem | Live |
| Lookup resi internal dengan shared token | menghubungkan nomor dan paket | n8n | Existing-Supporting | Live |
| Pengingat konfirmasi tak dijawab setiap 15 menit | mencegah paket macet | Scheduler | Existing-Supporting | Live |

## 5. Prinsip Produk yang Tersirat dari Arsitektur

Prinsip berikut bukan aspirasi: masing-masing punya bukti mekanisme di kode, dan
punya konsekuensi produk yang berbeda dari aplikasi biasa.

### 5.1 Database yang memiliki state machine

Transisi status dijaga trigger database, bukan hanya kelas PHP
(migrasi 0001_01_01_000016, tabel package_status_transitions). Konsekuensinya
untuk produk: menambah status atau transisi baru adalah pekerjaan migrasi dan seed,
bukan sekadar menambah cabang di satu controller. Fitur yang ingin memindahkan
paket dengan aturan baru tidak bisa dirilis tanpa perubahan data.

### 5.2 Setiap perubahan status punya aktor

Aplikasi menulis aktor ke sesi PostgreSQL lewat set_config app.staff_id sebelum
menyimpan, dan trigger yang menyalinnya ke status_logs.changed_by
(Support/PackageStatusTransitioner.php). Konsekuensinya: laporan siapa mengubah
apa dapat dipercaya tanpa kode tambahan di setiap Action; price-nya, aksi
pelanggan dan sistem terpaksa memakai aktor null sehingga jejak mereka harus
dibaca dari tabel lain seperti schedule_confirmations dan issue_reports.

### 5.3 Notifikasi adalah baris data, bukan efek samping

Semua pemberitahuan masuk tabel notifications lalu dikirim job
(Support/NotificationQueue.php). Konsekuensinya: kegagalan pengiriman WhatsApp
adalah status FAILED pada satu baris, bukan exception yang menghilang; dan
pemetaan template ke pesan WhatsApp hidup di n8n, sehingga template yang belum
dipetakan sengaja dibiarkan QUEUED supaya tidak menimbulkan kegagalan palsu
(Jobs/DispatchNotificationToN8n.php).

### 5.4 Lokasi dinilai server, klien hanya mengusulkan

Aplikasi kurir mengirim jendela fix GPS, dan server menjalankan detektor serta
geofence sendiri (Services/Gps/FakeGpsDetector.php,
Services/Gps/GeofenceEvaluator.php). Konsekuensinya: aplikasi tidak pernah
menjadi sumber kebenaran lokasi, sehingga memperbaiki detektor cukup satu
deployment backend. Sebaliknya, tanpa izin lokasi sama sekali, pengiriman tetap
bisa jalan: GPS bersifat best effort dan hanya kehilangan bukti
(GpsValidator::validate mengembalikan null bila koordinat tidak ada).

### 5.5 Bukti disimpan, bukan hanya status

Setiap penilaian GPS ditulis sebagai baris evidence apa pun hasilnya, termasuk
yang ditolak (LocationCheck). Konsekuensinya: pertanyaan "kenapa kurir tidak bisa
menyelesaikan" dapat dijawab dari data, bukan dari ingatan.

### 5.6 Frontend pelanggan tidak boleh melihat data bebas

Endpoint tracking publik membuang nama, nomor telepon, alamat jalan, dan teks
bebas staf, dan hanya menyisakan kota tingkat kabupaten plus timeline berlabel
tetap (Api/Tracking/TrackController.php). Header keamanan klien memasang
X-Robots-Tag noindex, Referrer-Policy no-referrer, dan Cache-Control private
no-store (Middleware/ClientSecurityHeaders.php). Konsekuensinya: resi bersifat
seperti kunci, dan itu sebabnya ada rate limit 20 per menit per IP.

### 5.7 Zona waktu adalah keputusan produk

Jadwal dipilih dalam waktu Jakarta dan disimpan UTC, lalu ditampilkan kembali
sebagai WIB (Support/DisplayTime.php, Actions/Packages/CreatePackage.php).
Konsekuensinya: laporan harian memakai batas hari Jakarta, bukan batas hari
server (Queries/DailyReportQuery.php).

### 5.8 Satu pintu untuk setiap perubahan uang atau status

Penugasan kurir dan penetapan keputusan kendala memakai lockForUpdate di dalam
DB::transaction (Actions/Packages/AssignCourier.php, Actions/Issues/ResolveIssue.php).
Konsekuensinya: dua admin yang menekan tombol bersamaan tidak menghasilkan dua
keputusan atau dua kurir pada satu paket.

## 6. Metrik Keberhasilan

Bagian ini hanya memuat angka yang bisa dihitung dari data yang sudah dicatat
sistem. Tidak ada target bisnis yang dikarang di sini.

| Metrik | Definisi operasional (dari kode) | Sumber data | Status |
| --- | --- | --- | --- |
| Total pengiriman per hari | COUNT packages dengan scheduled_at dalam rentang hari Jakarta | Queries/DailyReportQuery::getKpis | Terukur sekarang |
| Jumlah terkirim per hari | COUNT packages yang punya baris deliveries dalam rentang hari | idem | Terukur sekarang |
| Tingkat keberhasilan harian | delivered_count dibagi total_shipments | idem | Terukur sekarang |
| Jumlah kendala per hari | COUNT issue_reports dalam rentang hari | idem | Terukur sekarang |
| Tingkat insiden harian | issue_count dibagi total_shipments | idem | Terukur sekarang |
| Utilisasi kapasitas armada | total_shipments dibagi config coldchain.daily_fleet_capacity (1500) | idem, config/coldchain.php | Terukur sekarang |
| Waktu dari AWAITING_CONFIRMATION ke CONFIRMED | selisih changed_at pada status_logs berurutan | tabel status_logs | Terukur sekarang, belum ada query |
| Waktu tanggap putusan kendala | selisih issue_reports.reported_at terhadap decided_at | tabel issue_reports | Terukur sekarang, belum ada query |
| Perubahan jadwal pertama pada pengiriman pertama | COUNT issue_reports dengan decision RESCHEDULE dibagi COUNT packages | issue_reports, packages | Terukur sekarang, belum ada query |
| Tingkat pemblokiran GPS | COUNT location_checks dengan level BLOCKED dibagi seluruh location_checks | location_checks | Terukur sekarang, dan sebagian sudah tampil di konsol GPS |
| Persentase kendala yang diputuskan dalam hari yang sama | COUNT decision NOT NULL dengan tanggal decided_at sama dengan reported_at | issue_reports | Terukur sekarang, belum ada query |
| Pengiriman tanpa bukti foto | COUNT deliveries dengan photo_url NULL dibagi seluruh deliveries | deliveries | Terukur sekarang, belum ada query |
| Keputusan penggantian paket | COUNT packages dengan resend_of_package_id NOT NULL | packages | Terukur sekarang, belum ada query |
| Tingkat jawaban WhatsApp | COUNT notifications SENT dibagi seluruh notifications per template | notifications | Terukur sekarang, belum ada query |

Angka yang TIDAK boleh dianggap metrik karena belum ada datanya:

- Tingkat kepuasan pelanggan: tidak ada tabel survei atau rating. Nilai rating
  4.8 yang tampil di profil kurir adalah konstanta di controller
  (Courier/ProfileController.php), bukan data nyata. Harus dikonfirmasi
  stakeholder sebelum dipakai sebagai target.
- Persentase tepat waktu 96 persen atau 97 persen yang muncul di profil pembeli
  dan kurir adalah konstanta (BuyerProfileResource.php, Courier/ProfileController.php).
- Angka jangkauan dan SLA di landing page berasal dari data statis
  (resources/js/data/landing.ts), bukan pengukuran operasi.
- Target bisnis seperti biaya per pengiriman, retensi, atau pangsa pasar tidak
  ada di kode. Perlu dikonfirmasi stakeholder.

## 7. Asumsi, Risiko, dan Dependensi

### Asumsi yang dipegang kode

| Asumsi | Bukti | Konsekuensi bila salah |
| --- | --- | --- |
| Pembeli bisa dijangkau lewat WhatsApp | seluruh alur konfirmasi bertumpu padanya | paket macet di AWAITING_CONFIRMATION sampai pengingat admin |
| Koordinat tujuan tersedia untuk validasi radius | Checkout pelanggan dan form shipment mewajibkan titik lokasi dipin sebelum paket dibuat; GeofenceEvaluator mengukur dari recipient_latitude dan recipient_longitude | tanpa koordinat, geofence diam dan validasi lokasi hilang |
| Aplikasi kurir mengirim jendela fix GPS | Requests/Courier/Concerns/ValidatesGpsSignals.php | tanpa jendela fix, deteksi hanya memakai sinyal yang ada |
| Waktu Jakarta adalah satu-satunya zona tampilan | Support/DisplayTime.php | jadwal bisa terbaca salah bila hub lain zona waktu |
| Satu paket hanya punya satu pengganti | unique index uq_packages_one_replacement | penggantian kedua akan gagal di database |
| Kapasitas armada harian 1500 | config coldchain.daily_fleet_capacity | KPI utilisasi bisa menyesatkan bila angka tidak diperbarui |

### Risiko teknis dan operasional

| Risiko | Dampak | Bukti |
| --- | --- | --- |
| Aplikasi bergantung pada n8n dan WAHA untuk semua komunikasi pelanggan | gangguan n8n menghentikan konfirmasi jadwal dan menu kendala | Support/NotificationQueue, Jobs/DispatchNotificationToN8n |
| Google Maps dan WhatsApp dipakai lewat tautan luar pada perangkat kurir | tanpa keduanya, navigasi dan kontak pelanggan manual | Courier/DeliveryController@show (mapHref), Courier/ProofController (tel:) |
| Kelas ClientData dan CourierData masih menyimpan data contoh di app/Support | layar yang membaca kelas itu akan menampilkan data contoh, bukan data nyata | App/Support/ClientData.php, App/Support/CourierData.php |
| Tabel state percakapan hidup di database n8n, bukan di database aplikasi | data alur WhatsApp tidak ikut di-backup bersama aplikasi | deploy/n8n/state-table.sql |
| Alamat WhatsApp memakai LID pada sebagian chat | balasan gagal bila LID tidak diresolusi | deploy/n8n/README.md |
| Index unik konfirmasi PENDING hanya satu per paket | pengajuan jadwal ulang kedua harus menunggu yang pertama selesai | migrasi 0001_01_01_000010 |
| Foto bukti disimpan di disk lokal, bukan object storage | replika lebih dari satu tanpa volume bersama akan kehilangan pratinjau | Support/MediaStorage.php, Media/MediaPreviewController.php |
| Radius geofence di-cache 60 detik | perubahan radius tidak langsung berlaku | Services/Gps/GeofenceEvaluator.php |
| Halaman Inertia /courier dan /client tanpa middleware role | halaman bisa dibuka siapa saja, datanya tetap aman karena dari API | routes/web.php |

### Dependensi eksternal

| Dependensi | Peran | Bukti |
| --- | --- | --- |
| PostgreSQL 18 | database utama, pemilik FSM melalui trigger | composer.json, migrasi, docker-compose.yml |
| Redis | queue dan cache | composer.json (predis), config/queue.php |
| n8n 2.44.1 | perender pesan dan otomasi percakapan | deploy/n8n/README.md |
| WAHA 2026.9.2 | gateway WhatsApp | deploy/n8n/README.md, deploy/k8s/waha |
| LLM OpenAI-compatible deepseek-v4-flash | klasifikasi maksud balasan | deploy/n8n/README.md |
| FrankenPHP dan Octane | runtime HTTP | Dockerfile |
| Kubernetes dengan CloudNativePG dan Dragonfly | orkestrasi dan database terkelola | deploy/k8s/* |

Dependensi yang disebut di berkas tugas tetapi TIDAK ditemukan di repositori:
Cloudflare. Tidak ada konfigurasi Cloudflare di kode, Dockerfile, atau manifest
k8s; hanya nginx (deploy/nginx-anteraja.conf, deploy/frozen.conf) dan ingress k8s.

## 8. Gap dan Rekomendasi Backlog

### 8.1 Gap fungsional

| Gap | Bukti | Dampak produk | Rekomendasi |
| --- | --- | --- | --- |
| Tidak ada penggerak transisi ke CLOSED | tidak satu pun pemanggilan transitioner menuju CLOSED; kolom closed_at tidak pernah ditulis | paket selesai berhenti di RECEIVED atau RETURNED, arsip tidak pernah menutup | tentukan pemicunya, misalnya job harian atau aksi admin, lalu tambahkan Action dan test |
| Kolom closed_at tidak terpakai | app/Models/Package.php mencantumkan closed_at, tidak ada penulis selain itu | field mati yang membingungkan | isi saat transisi ke CLOSED |
| Keputusan kendala RESEND dan penggantian tidak punya idempotensi di level service | Actions/Client/RequestReplacement menangani duplicate key, tetapi ResendDecisionHandler hanya bergantung pada index unik | admin yang menekan dua kali bisa mendapat error database | tangkap QueryException di handler seperti yang sudah dilakukan di RequestReplacement |
| Tidak ada endpoint untuk menutup atau membatalkan paket dari admin | tidak ada route cancel di routes/api.php | paket salah input harus diakali lewat kendala retur | tambahkan aksi pembatalan yang tetap lewat FSM |
| Statistik pada profil pembeli dan kurir adalah konstanta | BuyerProfileResource on_time_rate 96 persen, Courier/ProfileController 97 persen dan rating 4.8 | angka menyesatkan bila dipakai sebagai KPI | hitung dari status_logs dan deliveries, atau beri label sebagai contoh |
| Tidak ada validasi kuota koli terhadap item | koli_count dan items diisi terpisah | data operasi bisa tidak konsisten | turunkan koli_count dari item atau validasi silang |

### 8.2 Gap cakupan test

Perlu diperjelas: tidak ada satu pun penanda TODO, FIXME, atau HACK di app, config,
routes, atau database; jadi backlog di bawah disusun dari ketiadaan test, bukan
dari komentar pengembang.

| Area tanpa test khusus | Bukti | Rekomendasi |
| --- | --- | --- |
| Endpoint dokumen OpenAPI /api/docs* | hanya closure; tidak ada test | tambahkan test smoke yang memastikan 404 di production |
| Ekspor riwayat dan laporan pada kondisi kosong | AdminExceptionTest menguji jalur bahagia | tambahkan test batas: hari tanpa data dan pencarian tanpa hasil |
| Geofence untuk layanan INSTANT_FROZEN pada radius minimum | AdminGpsConsoleTest menguji batas atas dan bawah; test jarak pada nilai ekstrem belum ada | tambahkan test batas jarak tepat sama dengan radius |
| Rate limit track-resi | PublicTrackingTest tidak menyentuh limit | tambahkan test 429 |
| Klaim tautan pada paket terminal yang sudah kedaluwarsa | ClientUnitTest menguji TTL di level service | tambahkan test endpoint |
| Pengiriman ulang saat decision bukan RETURN | ada test pada level eligibility service | tambahkan test endpoint |

### 8.3 Gap dokumentasi

| Temuan | Bukti | Rekomendasi |
| --- | --- | --- |
| openapi.yaml belum memuat seluruh route aktual | lihat bagian 11 FRD master untuk daftar lengkap ketidaksesuaian | sinkronkan atau tandai sebagai dokumen subset |
| Tidak ada daftar environment variable wajib | .env.example ada, tetapi tidak ada penjelasan per variabel | tambahkan tabel di README |
| Dua kelas data contoh masih ada di produksi kode | App/Support/ClientData.php dan CourierData.php | tandai jelas sebagai fixture atau hapus setelah dipastikan tidak ada pembaca selain tautan WhatsApp dan pesan bantuan |

## 9. Open Questions

Pertanyaan berikut tidak bisa dijawab dari kode; jawabannya menentukan apakah
suatu perilaku dianggap benar.

1. Apa pemicu resmi transisi RECEIVED ke CLOSED dan RETURNED ke CLOSED? Apakah
   ada proses administrasi di luar sistem, atau memang fiturnya belum dibuat?
2. Siapa yang berwenang menutup paket: admin hub, scheduler otomatis, atau
   pelanggan?
3. Apakah angka kapasitas armada harian 1500 di config coldchain.php masih
   relevan, dan dari mana asalnya?
4. Apakah rating 4.8 dan tepat waktu 97 persen pada profil kurir boleh
   dipertahankan sebagai placeholder, atau harus dihitung dari data?
5. Apakah aplikasi pelanggan tetap perlu menyimpan katalog hasil seeder (delapan
   produk contoh) atau katalog akan diisi dari sistem lain?
6. Apakah paket INSTANT_FROZEN perlu perlakuan berbeda pada batas jadwal minimal
   2 jam, mengingat SLA yang dipromosikan 2 sampai 4 jam?
7. Apakah kredensial kurir dan admin tetap sepenuhnya di luar sistem (dibuat di
   database), atau akan ada alur undangan dan pengelolaan staf?
8. Bila radius geofence diubah saat ada pengiriman berjalan, apakah penilaian
   yang sudah terjadi untuk paket itu perlu dinilai ulang?
9. Apakah data percakapan WhatsApp di tabel anteraja_chat_state perlu diarsipkan
   bersama data aplikasi untuk keperluan audit?
10. Apakah endpoint legacy /api/client/{token}/* boleh dipertahankan sebagai
    bagian dari kontrak publik, atau boleh dihapus setelah aplikasi pelanggan
    sepenuhnya memakai /api/client/orders/*?

## 10. Catatan Metodologi dan Batas Dokumen

Dokumen ini menggambarkan sistem apa adanya pada tanggal penulisan. Tidak ada
kode aplikasi yang diubah untuk menghasilkan PRD dan FRD ini: perubahan hanya
berupa penambahan berkas Markdown di docs/.

Semua label [INFERENSI] menandakan penalaran dari bukti, bukan pernyataan
langsung kode. Semua label [TIDAK TERVERIFIKASI] menandakan area yang tidak
memiliki bukti sama sekali dan wajib dikonfirmasi manusia sebelum dipakai sebagai
dasar pekerjaan berikutnya.
