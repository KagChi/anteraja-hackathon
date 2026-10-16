# Daftar Fitur (Feature Index)

Dokumen ini adalah daftar lengkap fitur yang ada di repositori Anteraja Frozen
saat ini, dikelompokkan per modul fungsional. Setiap fitur menunjuk ke requirement
FRD tempat detail lengkapnya berada.

| Modul | Berkas detail | Jumlah FR |
| --- | --- | --- |
| AUTH — Autentikasi dan Sesi | docs/frd/01-auth.md | 10 |
| PKG — Paket dan FSM Status | docs/frd/02-paket-fsm.md | 8 |
| ASG — Penugasan Kurir dan Siklus Tugas | docs/frd/03-penugasan-kurir.md | 5 |
| DEL — Pengantaran dan Bukti Serah Terima | docs/frd/04-pengantaran-bukti.md | 7 |
| GPS — Cold Chain dan Validasi Lokasi | docs/frd/05-coldchain-gps.md | 7 |
| ISS — Laporan Kendala, Keputusan, dan Penggantian | docs/frd/06-kendala-penggantian.md | 10 |
| NOTIF — Notification Outbox dan Penjadwalan | docs/frd/07-notifikasi-outbox.md | 3 |
| WA — Percakapan WhatsApp | docs/frd/08-whatsapp.md | 3 |
| TRACK — Pelacakan Publik dan Tautan Tamu | docs/frd/09-pelacakan.md | 7 |
| ORD — Katalog dan Pemesanan Pelanggan | docs/frd/10-katalog-pesanan.md | 5 |
| RPT — Pelaporan, Ekspor, dan Media | docs/frd/11-pelaporan.md | 8 |
| LAND — Landing Page dan Blog | docs/frd/12-landing-blog.md | 3 |
| MOB — Shell Mobile Native | docs/frd/13-mobile-native.md | 6 |

Total: 82 requirement fungsional.

## AUTH — Autentikasi dan Sesi

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-AUTH-001 | Login sesi admin | Autentikasi staf role ADMIN dengan email dan password, hanya admin aktif | Live |
| FR-AUTH-002 | Logout sesi admin | Menghapus sesi dan mengganti token CSRF | Live |
| FR-AUTH-003 | Profil sesi admin | Mengembalikan identitas staf yang sedang login | Live |
| FR-AUTH-004 | Login kurir berbasis token | Menukar email dan password staf COURIER menjadi bearer token ability courier | Live |
| FR-AUTH-005 | Logout kurir | Mencabut token yang sedang dipakai | Live |
| FR-AUTH-006 | Registrasi pelanggan | Membuat akun pembeli dari nomor HP tanpa OTP dan mengembalikan token | Live |
| FR-AUTH-007 | Login pelanggan | Autentikasi nomor HP dan password dengan normalisasi E.164 | Live |
| FR-AUTH-008 | Logout pelanggan | Mencabut token klien aktif | Live |
| FR-AUTH-009 | Profil pelanggan | Identitas, jumlah pesanan, dan kanal dukungan | Live |
| FR-AUTH-010 | Klaim tautan menjadi akun | Menetapkan password pada pembeli yang datang dari tautan token | Live |

## PKG — Paket dan FSM Status

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-PKG-001 | Membuat paket dari hub | Membuat paket, item, konfirmasi PENDING, lalu mengirim permintaan konfirmasi | Live |
| FR-PKG-002 | Antrean shipment dengan filter | Daftar paket non-terminal dengan filter status, kurir, jadwal, tanggal, dan sumber | Live |
| FR-PKG-003 | Data master form pembuatan paket | Produk, pembeli terakhir, dan daftar kurir aktif | Live |
| FR-PKG-004 | Detail paket dan timeline | Detail lengkap, timeline, tautan klien, dan foto bukti | Live |
| FR-PKG-005 | Penjadwalan ulang oleh admin | Menetapkan jendela baru dan meminta konfirmasi ulang pembeli | Live |
| FR-PKG-006 | Penjagaan FSM oleh database | Trigger menolak transisi di luar matriks | Live |
| FR-PKG-007 | Atribusi aktor pada log status | Aktor ditulis ke sesi PostgreSQL dan disalin trigger ke status_logs | Live |
| FR-PKG-008 | Transisi ke CLOSED | Menutup paket terminal beserta closed_at | [TIDAK TERVERIFIKASI] |

## ASG — Penugasan Kurir dan Siklus Tugas

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-ASG-001 | Penugasan kurir | Menugaskan kurir pada paket CONFIRMED tanpa kurir, lalu memberitahu kurir | Live |
| FR-ASG-002 | Papan tugas kurir | Memisahkan tugas baru dan tugas aktif dengan usia penugasan | Live |
| FR-ASG-003 | Menerima tugas | Mencatat waktu terima tugas, idempoten | Live |
| FR-ASG-004 | Menolak tugas | Mengembalikan paket ke CONFIRMED dan melepas kurir | Live |
| FR-ASG-005 | Jarak dan ETA tujuan | Menghitung jarak haversine dan estimasi menit | Live |

## DEL — Pengantaran dan Bukti Serah Terima

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-DEL-001 | Layar pengantaran kurir | Detail tujuan, tombol aksi, checklist, dan konteks GPS | Live |
| FR-DEL-002 | Mulai pengantaran dengan checklist | Wajib checklist bersih, menyimpan condition check, pindah ke IN_DELIVERY | Live |
| FR-DEL-003 | Menandai tiba di lokasi | Mencatat milestone ARRIVED beserta koordinat tanpa menegakkan radius | Live |
| FR-DEL-004 | Form bukti serah terima | Data form bukti: relasi penerima dan placeholder catatan | Live |
| FR-DEL-005 | Menyimpan bukti serah terima | Menyimpan delivery, foto, dan memindahkan paket ke RECEIVED | Live |
| FR-DEL-006 | Riwayat pengantaran kurir | Daftar paket terminal kurir dengan ringkasan harian | Live |
| FR-DEL-007 | Profil kurir | Identitas dan statistik kurir | Partial |

## GPS — Cold Chain dan Validasi Lokasi

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-GPS-001 | Deteksi manipulasi sinyal GPS | Tujuh sinyal pemeriksa dengan tingkat CLEAN, SUSPECTED, BLOCKED | Live |
| FR-GPS-002 | Penilaian geofence per segmen layanan | Jarak haversine ke titik penerima dibandingkan radius segmen | Live |
| FR-GPS-003 | Pencatatan bukti penilaian lokasi | Setiap penilaian disimpan sebagai baris evidence | Live |
| FR-GPS-004 | Permintaan tinjauan GPS oleh kurir | Membuka satu permintaan PENDING per paket dan aksi | Live |
| FR-GPS-005 | Konsol validasi GPS admin | Daftar penilaian bermasalah plus antrean permintaan tinjauan | Live |
| FR-GPS-006 | Putusan tinjauan GPS | Menyetujui atau menolak permintaan dengan alasan | Live |
| FR-GPS-007 | Pengaturan radius geofence | Membaca dan mengubah radius per segmen beserta batasnya | Live |

## ISS — Laporan Kendala, Keputusan, dan Penggantian

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-ISS-001 | Menyiapkan form laporan kendala | Enam kategori kendala dan konteks paket | Live |
| FR-ISS-002 | Mengirim laporan kendala | Menyimpan condition check dan issue report lalu pindah ke ISSUE_REPORTED | Live |
| FR-ISS-003 | Antrean kendala admin | Daftar kendala aktif dan yang sudah diputuskan | Live |
| FR-ISS-004 | Detail kendala admin | Checklist, foto, timeline, dan daftar keputusan yang diizinkan | Live |
| FR-ISS-005 | Putusan kendala | Menyimpan keputusan beralasan dan mendelegasikan ke handler | Live |
| FR-ISS-006 | Efek samping lima jenis putusan | Transisi, notifikasi, dan paket pengganti per keputusan | Live |
| FR-ISS-007 | Kelayakan dan pembuatan paket pengganti | Syarat retur dan pembuatan resi baru | Live |
| FR-ISS-008 | Layar kendala pelanggan | Ringkasan masalah, keputusan, catatan mutu, dan timeline | Live |
| FR-ISS-010 | Permintaan pengiriman ulang dari aplikasi | Pengajuan resend dari aplikasi pelanggan | Live |

## NOTIF — Notification Outbox dan Penjadwalan

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-NOTIF-001 | Menulis notifikasi ke outbox | Baris notifications berstatus QUEUED dengan konteks kirim lengkap | Live |
| FR-NOTIF-002 | Mengirim notifikasi ke n8n | Job dengan retry dan pembaruan status SENT atau FAILED | Live |
| FR-NOTIF-003 | Pengingat konfirmasi tak dijawab | Scheduler menandai konfirmasi PENDING yang melewati ambang | Live |

## WA — Percakapan WhatsApp

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-WA-001 | Kontrak webhook outbound | Bentuk payload yang diterima n8n dan makna jawabannya | Live |
| FR-WA-002 | Percakapan masuk dan pemetaan aksi | Balasan pelanggan diubah menjadi aksi berdasarkan state percakapan | Live |
| FR-WA-003 | Lookup resi untuk bot | Endpoint internal ber-token untuk menghubungkan nomor dengan paket | Live |

## TRACK — Pelacakan Publik dan Tautan Tamu

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-TRACK-001 | Pelacakan publik berdasarkan resi | Status, jendela, kota, dan timeline berlabel tetap tanpa identitas | Live |
| FR-TRACK-002 | Resolusi tautan tamu berbatas waktu | Token hex dicari ke paket dengan rate limit dan TTL | Live |
| FR-TRACK-003 | Pemilihan layar otomatis | Menentukan jadwal, kendala, atau tracking dari status paket | Live |
| FR-TRACK-004 | Data tracking pelanggan | Kartu cold chain, timeline, dan akses menghubungi kurir | Live |
| FR-TRACK-005 | Data jadwal pelanggan | Kargo, berat, jendela, dan slot yang tersedia | Live |
| FR-TRACK-006 | Konfirmasi jadwal | Memindahkan paket ke CONFIRMED dengan aktor kosong | Live |
| FR-TRACK-007 | Permintaan jadwal ulang pelanggan | Memindahkan paket ke RESCHEDULE_REQUESTED dan memberitahu admin | Live |

## ORD — Katalog dan Pemesanan Pelanggan

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-ORD-001 | Daftar katalog publik | Produk yang tersedia dengan urutan katalog | Live |
| FR-ORD-002 | Detail produk publik | Satu produk berdasarkan slug | Live |
| FR-ORD-003 | Checkout pesanan pelanggan | Membuat paket sumber CLIENT_APP dari keranjang | Live |
| FR-ORD-004 | Daftar pesanan dengan tab dan pencarian | Tab aktif, selesai, kendala, semua plus hitungan | Live |
| FR-ORD-005 | Detail pesanan pelanggan | Ringkasan satu pesanan milik pembeli | Live |

## RPT — Pelaporan, Ekspor, dan Media

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-RPT-001 | KPI laporan harian | Enam angka operasi per tanggal Jakarta | Live |
| FR-RPT-002 | Ekspor laporan harian | CSV kendala per tanggal | Live |
| FR-RPT-003 | Pencarian riwayat paket | Daftar paket terminal dengan pencarian aman | Live |
| FR-RPT-004 | Ekspor riwayat | CSV riwayat paket terminal | Live |
| FR-RPT-005 | Ekspor antrean shipment | CSV antrean dengan filter yang sama seperti layar | Live |
| FR-RPT-006 | Pratinjau foto bukti | Menyajikan foto dari disk privat untuk konsol | Live |
| FR-RPT-007 | Ringkasan operasi otomatis beranda | KPI + ikhtisar bahasa Indonesia per tanggal untuk beranda | Live |
| FR-RPT-008 | Riwayat aktivitas dan ekspor PDF | Jejak audit status paket satu hari plus unduhan PDF beranda | Live |

## LAND — Landing Page dan Blog

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-LAND-001 | Halaman landing | Konten pemasaran, panel lacak resi, dan tautan unduhan APK | Live |
| FR-LAND-002 | Blog index dan artikel | Konten blog dari module TypeScript | Live |
| FR-LAND-003 | Halaman login admin dan redirect ringkas | Halaman login Inertia dan pengalihan path pendek | Live |

## PWA — Aplikasi Web Terpasang

| ID | Fitur | Ringkas | Status |
| --- | --- | --- | --- |
| FR-PWA-001 | Shell dan rute dua aplikasi | Shell publik per aplikasi, rute dipegang SPA | Live |
| FR-PWA-002 | Token realm dan sesi berakhir | Bearer token per realm di localStorage | Live |
| FR-PWA-003 | Klien API realm | Klien HTTP per realm yang menormalkan galat | Live |
| FR-PWA-004 | Kapabilitas perangkat dan peta | Kamera, fix window, dan peta Leaflet sebaris | Live |
| FR-PWA-005 | Keranjang dan struk pelanggan | Keranjang localStorage dan struk sesi terakhir | Live |
| FR-PWA-006 | Installability dan offline | Dua manifest, service worker, dan halaman luring | Live |
