# Anteraja Frozen — Kesiapan Penerimaan dan Kondisi Paket

Prototipe hackathon Anteraja NextGen AI Academy. Aplikasi pelanggan adalah
aplikasi **pengirim**: pelanggan memilih isi paket dan alamat penerima —
keluarga, kerabat, atau teman — lalu paket diantar ke sana. Satu proses utuh
"FROZEN" berjalan end-to-end: paket dibuat, **penerima** mengonfirmasi jadwal
**lewat WhatsApp**, admin menugaskan kurir, kurir menerima tugas dan memeriksa
kemasan dari aplikasi kurir, kendala (mis. kemasan bocor) dilaporkan dan
diputuskan admin, lalu semuanya masuk laporan harian.

Empat permukaan, satu backend Laravel:

| Permukaan | Teknologi | Masuk lewat |
| --- | --- | --- |
| Dashboard admin | React + Inertia (desktop) | `/login` → `/admin/dashboard` |
| Aplikasi Pelanggan (pengirim) | PWA (React SPA + bearer token) | `/client` — pasang dari browser |
| Aplikasi Kurir | PWA (React SPA + bearer token) | `/courier` — pasang dari browser |
| Otomasi WhatsApp | n8n + WAHA (dua workflow, lihat `deploy/n8n/README.md`) | webhook |

Seluruh tampilan — landing, dashboard, dan kedua aplikasi — **hanya mode
terang**. Tidak ada toggle tema; `color-scheme: light` dipaku di CSS, meta tag,
dan build APK supaya perangkat yang diatur ke mode gelap tidak menggelapkan
halaman.

## Menjalankan

Prasyarat: PHP 8.4+ dengan ekstensi pdo_pgsql, PostgreSQL 17/18, Redis (boleh
dilewati dengan mengubah `CACHE_STORE`/`QUEUE_CONNECTION` ke `database`),
Bun 1.x, dan Node 20+ untuk suite n8n.

```bash
composer install
bun install

cp .env.example .env          # isi APP_KEY dengan: php artisan key:generate
# PostgreSQL lokal harus punya role/database seperti di .env (laravel/laravel)
php artisan migrate --seed    # ReferenceDataSeeder + DemoDataSeeder
bun run build                 # bundel aset untuk dashboard

php artisan serve             # http://127.0.0.1:8000
php artisan queue:work        # mengirim baris notifications ke n8n

# Ringkasan harian dikirim otomatis pukul 20:00 WIB oleh scheduler:
php artisan schedule:work
```

Kedua aplikasi adalah PWA: buka `/client` dan `/courier` di browser, lalu pilih
“Pasang aplikasi” dari menu browser (iPhone: Bagikan → Tambahkan ke Layar
Utama). Identitas, ikon, dan manifest kedua aplikasi diatur di `config/pwa.php`;
peta dan geolokasi membutuhkan HTTPS (kecuali localhost).

## Environment

| Variabel | Arti |
| --- | --- |
| `APP_TIMEZONE` | **Harus UTC.** Waktu disimpan UTC dan ditampilkan WIB oleh `App\Support\DisplayTime`; nilai ini juga menyetel zona sesi database. |
| `DB_TIMEZONE` | Zona sesi database, default `UTC`. Jangan disamakan dengan zona tampilan. |
| `DB_*` | Koneksi PostgreSQL. |
| `N8N_WEBHOOK_ENABLED` | `true` mengaktifkan pengiriman WhatsApp lewat n8n. `false` membuat notifikasi tetap `QUEUED` (mode uji lokal). |
| `N8N_WEBHOOK_URL` | Webhook workflow outbound, mis. `http://n8n/webhook/anteraja-frozen/notify`. |
| `N8N_WEBHOOK_TOKEN` | Token bersama (`X-Anteraja-Token`) untuk webhook n8n dan endpoint internal aplikasi (`/api/internal/whatsapp/*`). Kosong = endpoint internal menjawab 503. |

## Alur FROZEN (yang didemokan)

1. **Paket dibuat** — pelanggan memesan dari aplikasi (atau admin mengisi
   pengirim, penerima, produk, jadwal, dan instruksi penanganan). Status
   `AWAITING_CONFIRMATION`, penerima menerima permintaan konfirmasi jadwal di
   WhatsApp.
2. **Jadwal dikonfirmasi** — penerima membalas *SIAP* (atau *JADWAL LAIN*).
   Kurir tidak bisa ditugaskan sebelum ini; `CONFIRMED`.
3. **Kurir ditugaskan** — admin memilih kurir. Kurir menerima tugas beserta
   instruksi penanganan di WhatsApp dan aplikasi kurir; `COURIER_ASSIGNED`.
4. **Kondisi diperiksa** — kurir mengisi checklist kemasan (tanpa sensor suhu);
   `CHECKED`, atau `ISSUE_REPORTED` bila ada kendala.
5. **Paket diantar** — kurir berangkat; penerima menerima pesan pengantaran dan
   pemberitahuan menjelang tiba; `IN_DELIVERY`.
6. **Kendala diputuskan** — admin memilih tindakan (kembalikan, kirim ulang,
   lanjut, periksa ulang, jadwalkan ulang) dan mencatat alasannya. Instruksi
   dikirim ke kurir, penerima menerima informasi keputusan.
7. **Paket diterima** — kurir mencatat serah terima (foto bukti di aplikasi);
   `RECEIVED`, pengiriman masuk riwayat dan laporan.

Cabang yang didemokan: *penerima minta jadwal lain (jendela pilihannya langsung
diterapkan) → kurir ditugaskan → admin mengajukan jendela baru lewat "Ajukan
jadwal" dan penerima mengonfirmasinya → kurir melaporkan kemasan bocor → admin
memutuskan pengembalian → kurir dan penerima menerima informasi keputusan*.

## Beranda admin

`/admin/dashboard` (tujuan bawaan `/admin`) menampilkan **ringkasan operasi
otomatis**: enam KPI hari yang sama dengan halaman Laporan, ikhtisar bahasa
Indonesia yang dihitung server (tanpa LLM), dan penyegaran otomatis tiap 60
detik. Di bawahnya ada **Log History** — tab *Aktivitas Paket* (jejak audit
`status_logs`: siapa mengubah status apa) dan tab *Pesan WhatsApp* (riwayat
percakapan yang dulu hanya ada di API). Tombol **Ekspor PDF** mengunduh rekap A4
satu hari yang dirender server lewat `dompdf`.

## Penerima paket

Pembeli pada akun pelanggan adalah **pengirim**; tujuan pengantaran disimpan
pada paketnya sendiri (`packages.recipient_name`, `recipient_phone`,
`recipient_address`, `recipient_area`, `recipient_address_type`,
`recipient_latitude`, `recipient_longitude`).

- Penerima tidak punya akun dan tidak memakai aplikasi. Satu-satunya kanal
  mereka adalah WhatsApp: konfirmasi jadwal, permintaan jadwal lain, dan pilihan
  penyelesaian kendala dijawab dari nomor penerima. Balasannya diterapkan lewat
  token paket, bukan tautan aplikasi pelanggan.
- Perjalanan kurir tetap satu kaki — hub ke titik penerima. Validasi geofence
  diukur dari koordinat penerima, bukan dari alamat pelanggan.
- Profil pelanggan tetap identitas pengirim dan tidak lagi ditimpa data
  pengantaran, jadi checkout aplikasi selalu mulai dari blok penerima yang
  kosong.
- Paket dari dashboard admin boleh tidak menyebut penerima: paket seperti itu
  diantar ke pembeli sendiri, sama seperti sebelum blok penerima ada.

## WhatsApp

Semua kanal pesan lewat WhatsApp (WAHA). Aplikasi menulis baris
`notifications`, job `DispatchNotificationToN8n` mengirim template +
payload ke workflow outbound, dan workflow merender teksnya. Balasan masuk
dibaca workflow inbound: niatnya dikenali (aturan + LLM sebagai cadangan), lalu
aksi untuk kurir dan admin dijalankan domain aplikasi lewat
`POST /api/internal/whatsapp/action` — sehingga status paket, riwayat, dan
notifikasi lanjutan sama persis dengan yang dihasilkan aplikasi kurir atau
dashboard.

Isi pesan selalu berdiri sendiri: nomor resi, produk, jadwal, status, langkah
selanjutnya — tanpa satu pun URL. Setiap pesan masuk dan setiap balasan dicatat
di tabel `whatsapp_messages` dan bisa dilihat di **Dashboard → Log History →
tab Pesan WhatsApp**.

### Mode uji tanpa WhatsApp asli

Kredensial WAHA tidak diperlukan untuk menguji alur:

```bash
# 1. Logika inbound + outbound (66 skenario, tanpa database/cluster)
node deploy/n8n/tests/inbound-scenarios.mjs

# 2. Endpoint aksi internal, sama dengan yang dipanggil workflow inbound
curl -sS -X POST http://127.0.0.1:8000/api/internal/whatsapp/action \
  -H "X-Anteraja-Token: \$N8N_WEBHOOK_TOKEN" -H 'Content-Type: application/json' \
  -d '{"phone":"081298760001","action":"accept","resi":"AJ-FRZ-0012345","message_id":"demo-1"}'

# 3. Laporan harian manual (juga ada tombolnya di halaman Laporan)
php artisan report:send-daily
```

## Test

```bash
php artisan test                                    # 205 test, semua harus lulus
node deploy/n8n/tests/inbound-scenarios.mjs         # 66 skenario pesan
bun run lint && bun run typecheck && bun run build  # biome + tsc + bundel produksi
```

Suite PHPUnit berjalan di atas database yang sudah di-seed, jadi jalankan
`php artisan migrate:fresh --seed` lebih dulu bila database baru saja diubah.
Uji layar kedua aplikasi ada di `tests/Feature/PwaShellTest.php`; pemasangan di
perangkat dan jalur kamera/geolokasi diperiksa manual dengan prosedur di
`docs/testing/skenario-aplikasi-pwa.md`.

## Akun demo

| Peran | Masuk dengan |
| --- | --- |
| Admin | `dimas@anteraja.id` / `password` |
| Kurir | `budi@anteraja.id`, `ahmad@anteraja.id` / `password` |
| Pembeli | nomor `+6281298765432` (Rina Putri) / `password` |

Penerima paket tidak punya akun. Nomornya ada di kolom penerima tiap paket demo
(mis. `+6281200010001` untuk Ibu Sari Wulandari, tujuan paket-paket demo Rina).

`php artisan migrate:fresh --seed` juga mencetak URL bertoken untuk tiap paket
demo supaya alur pembeli bisa dibuka tanpa memasang aplikasinya.
