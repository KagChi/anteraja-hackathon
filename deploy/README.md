# Panduan Deploy Backend Anteraja Frozen (FrankenPHP + Octane)

Dokumentasi ini menjelaskan konfigurasi dan tata cara deployment service Backend Laravel Anteraja Frozen ke VPS yang menggunakan arsitektur **Zero Trust Nginx Reverse Proxy**.

---

## 1. Arsitektur & Alur Traffic (Zero Trust)

```
[Internet]
    │
    ▼
[Cloudflare CDN / DNS]
    │
    ▼ (Cloudflare Tunnel: tunnel-net)
[cloudflared-tunnel]
    │
    ▼ (Docker internal :8080)
[nginx-proxy] (production.conf)
    │
    ├─► upstream anteraja_frozen_backend: server anteraja-frozen-backend:80 (via backend-net)
    │
    ▼
[anteraja-frozen-backend] (FrankenPHP / Octane :80)
    │
    ├── (anteraja-net privat) ──► [anteraja-frozen-postgres:5432]
    └── (anteraja-net privat) ──► [anteraja-frozen-redis:6379]
```

### Isolasi Jaringan:
1. **`backend-net` (External Bridge):** 
   - Menghubungkan `nginx-proxy` dengan container backend `anteraja-frozen-backend`.
   - Tidak ada port yang diekspos ke host VPS (`expose: 80` hanya di dalam internal network).
2. **`anteraja-net` (Internal Bridge):**
   - Jaringan privat yang hanya dihuni oleh `app`, `pgsql`, dan `redis`.
   - Database dan Redis **TIDAK** terhubung ke `backend-net` maupun internet, memastikan keamanan data tingkat tinggi.

---

## 2. Konfigurasi Nginx Reverse Proxy di VPS

File konfigurasi Nginx siap pakai telah disediakan di [`deploy/frozen.conf`](./frozen.conf).

### Cara Pasang di Docker Compose Nginx VPS:

1. Buat file `frozen.conf` di folder Nginx VPS Anda (isinya sama persis dengan [`deploy/frozen.conf`](./frozen.conf)).
2. Di file `docker-compose.yml` milik Nginx VPS, tambahkan volume mount untuk `frozen.conf`:
   ```yaml
       volumes:
         # Konfigurasi Nginx utama
         - ./production.conf:/etc/nginx/conf.d/default.conf:ro
         # Static files — root HTML (gallery + configs-page inside)
         - ./html:/usr/share/nginx/html:ro
         # Images directory — served at /images/
         - ./images:/usr/share/nginx/images:ro
         # Konfigurasi subdomain
         - ./n8n.conf:/etc/nginx/conf.d/n8n.conf:ro
         - ./frozen.conf:/etc/nginx/conf.d/frozen.conf:ro
   ```
3. Restart atau reload container Nginx:
   ```bash
   docker compose restart nginx
   # atau
   docker exec -it nginx-proxy nginx -s reload
   ```

---

## 3. Langkah Deployment di VPS

### Langkah 1: Pastikan Network `backend-net` Sudah Ada
```bash
docker network create backend-net
```

### Langkah 2: Siapkan File `.env`
```bash
cd deploy
cp .env.production.example .env
```
Edit file `.env` dan pastikan:
- `APP_KEY=...` (Key rahasia Laravel)
- `DB_PASSWORD=...` (Password aman untuk PostgreSQL)
- `APP_URL=https://frozen.madgo.my.id`
- `ASSET_URL=https://frozen.madgo.my.id`

### Langkah 3: Build & Jalankan Stack
```bash
docker compose up -d --build
```

Container memiliki script inisialisasi otomatis (`docker-entrypoint.sh`) yang akan:
1. Menunggu PostgreSQL dan Redis siap (*healthy*).
2. Menjalankan migrasi database otomatis (`php artisan migrate --force`).
3. Menjalankan seeder referensi status FSM dan role staf (`ReferenceDataSeeder`).
4. Mengoptimalkan cache Laravel (`config`, `route`, `view`, `event`).
5. Memulai FrankenPHP Octane pada port 80.

---

## 4. Perintah Operasional & Manajemen

- **Melihat Log Realtime Backend:**
  ```bash
  docker compose logs -f app
  ```

- **Melihat Log Database:**
  ```bash
  docker compose logs -f pgsql
  ```

- **Jalankan Seeder Demo Data (Opsional):**
  ```bash
  docker compose exec app php artisan db:seed --class="Database\Seeders\DemoDataSeeder" --force
  ```

- **Clear / Refresh Cache:**
  ```bash
  docker compose exec app php artisan optimize:clear
  docker compose exec app php artisan optimize
  ```

- **Restart Backend Service:**
  ```bash
  docker compose restart app
  ```

- **Stop Seluruh Stack:**
  ```bash
  docker compose down
  ```
