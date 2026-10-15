# Anteraja Frozen — Kesiapan Penerimaan dan Kondisi Paket

Prototipe hackathon Anteraja NextGen AI Academy. Satu proses utuh "FROZEN"
berjalan end-to-end: paket dibuat, pembeli mengonfirmasi jadwal **lewat
WhatsApp**, admin menugaskan kurir, kurir menerima tugas dan memeriksa kemasan
dari aplikasi Android, kendala (mis. kemasan bocor) dilaporkan dan diputuskan
admin, lalu semuanya masuk laporan harian.

Tiga permukaan, satu backend Laravel:

| Permukaan | Teknologi | Masuk lewat |
| --- | --- | --- |
| Dashboard admin | React + Inertia (desktop) | `/login` → `/admin/shipment` |
| Aplikasi Pembeli | NativePHP for Mobile (Jetpack Compose) | `com.anteraja.frozen` |
| Aplikasi Kurir | NativePHP for Mobile (Jetpack Compose) | `com.anteraja.kurir` |
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

Untuk mengemas APK: `scripts/build-apk.sh kurir` dan `scripts/build-apk.sh client`
(membutuhkan Android SDK; lihat `mobile/README.md`).

## Environment

| Variabel | Arti |
| --- | --- |
| `APP_TIMEZONE` | **Harus UTC.** Waktu disimpan UTC dan ditampilkan WIB oleh `App\Support\DisplayTime`; nilai ini juga menyetel zona sesi database. |
| `DB_TIMEZONE` | Zona sesi database, default `UTC`. Jangan disamakan dengan zona tampilan. |
| `DB_*` | Koneksi PostgreSQL. |
| `N8N_WEBHOOK_ENABLED` | `true` mengaktifkan pengiriman WhatsApp lewat n8n. `false` membuat notifikasi tetap `QUEUED` (mode uji lokal). |
| `N8N_WEBHOOK_URL` | Webhook workflow outbound, mis. `http://n8n/webhook/anteraja-frozen/notify`. |
| `N8N_WEBHOOK_TOKEN` | Token bersama (`X-Anteraja-Token`) untuk webhook n8n dan endpoint internal aplikasi (`/api/internal/whatsapp/*`). Kosong = endpoint internal menjawab 503. |
| `MOBILE_API_BASE_URL` | Backend yang dipakai APK (diisi otomatis oleh `scripts/build-apk.sh`). |
| `ANDROID_APK_URL`, `ANDROID_APK_VERSION` | Unduhan APK di landing page. |

## Alur FROZEN (yang didemokan)

1. **Paket dibuat** — admin mengisi pembeli, produk, jadwal, instruksi
   penanganan. Status `AWAITING_CONFIRMATION`, pembeli menerima permintaan
   konfirmasi jadwal di WhatsApp.
2. **Jadwal dikonfirmasi** — pembeli membalas *SIAP* (atau *JADWAL LAIN*).
   Kurir tidak bisa ditugaskan sebelum ini; `CONFIRMED`.
3. **Kurir ditugaskan** — admin memilih kurir. Kurir menerima tugas beserta
   instruksi penanganan di WhatsApp dan aplikasi; `COURIER_ASSIGNED`.
4. **Kondisi diperiksa** — kurir mengisi checklist kemasan (tanpa sensor suhu);
   `CHECKED`, atau `ISSUE_REPORTED` bila ada kendala.
5. **Paket diantar** — kurir berangkat; pembeli menerima pesan pengantaran dan
   pemberitahuan menjelang tiba; `IN_DELIVERY`.
6. **Kendala diputuskan** — admin memilih tindakan (kembalikan, kirim ulang,
   lanjut, periksa ulang, jadwalkan ulang) dan mencatat alasannya. Instruksi
   dikirim ke kurir, pembeli menerima informasi keputusan.
7. **Paket diterima** — kurir mencatat serah terima (foto bukti di aplikasi);
   `RECEIVED`, pengiriman masuk riwayat dan laporan.

Cabang yang didemokan: *pembeli minta jadwal lain → admin memperbarui jadwal →
kurir menerima tugas → kurir melaporkan kemasan bocor → admin memutuskan
pengembalian → kurir dan pembeli menerima informasi keputusan*.

## WhatsApp

Semua kanal pesan lewat WhatsApp (WAHA), tidak ada Telegram. Aplikasi menulis
baris `notifications`, job `DispatchNotificationToN8n` mengirim template +
payload ke workflow outbound, dan workflow merender teksnya. Balasan masuk
dibaca workflow inbound: niatnya dikenali (aturan + LLM sebagai cadangan), lalu
aksi untuk kurir dan admin dijalankan domain aplikasi lewat
`POST /api/internal/whatsapp/action` — sehingga status paket, riwayat, dan
notifikasi lanjutan sama persis dengan yang dihasilkan aplikasi kurir atau
dashboard.

Isi pesan selalu berdiri sendiri: nomor resi, produk, jadwal, status, langkah
selanjutnya — tanpa satu pun URL. Setiap pesan masuk dan setiap balasan dicatat
di tabel `whatsapp_messages` dan bisa dilihat di **Riwayat → Riwayat Pesan
WhatsApp**.

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
php artisan test                                    # 252 test, semua harus lulus
node deploy/n8n/tests/inbound-scenarios.mjs         # 66 skenario pesan
bun run typecheck && bun run build                  # tsc + bundel produksi
```

Suite PHPUnit berjalan di atas database yang sudah di-seed, jadi jalankan
`php artisan migrate:fresh --seed` lebih dulu bila database baru saja diubah.

## Akun demo

| Peran | Masuk dengan |
| --- | --- |
| Admin | `dimas@anteraja.id` / `password` |
| Kurir | `budi@anteraja.id`, `ahmad@anteraja.id` / `password` |
| Pembeli | nomor `+6281298765432` (Rina Putri) / `password123` |

`php artisan migrate:fresh --seed` juga mencetak URL bertoken untuk tiap paket
demo supaya alur pembeli bisa dibuka tanpa aplikasi.
