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
    ├─► upstream anteraja_laravel: server anteraja_nginx:80 (via backend-net)
    │
    ▼
[anteraja_nginx] (FrankenPHP / Octane :80)
    │
    ├── (anteraja-net privat) ──► [anteraja-postgres:5432]
    └── (anteraja-net privat) ──► [anteraja-redis:6379]
```

### Isolasi Jaringan:
1. **`backend-net` (External Bridge):** 
   - Hanya menghubungkan `nginx-proxy` dengan container backend `anteraja_nginx`.
   - Tidak ada port yang diekspos ke host VPS (`ports` tidak dipublish, hanya `expose: 80`).
2. **`anteraja-net` (Internal Bridge):**
   - Jaringan privat yang hanya dihuni oleh `app`, `pgsql`, dan `redis`.
   - Database dan Redis **TIDAK** terhubung ke `backend-net` maupun internet, memastikan keamanan data tingkat tinggi.

---

## 2. Kesesuaian dengan Konfigurasi Nginx VPS

Konfigurasi di `deploy/docker-compose.yml` telah **100% disesuaikan** dengan file `production.conf` Nginx Anda:

| Parameter Nginx VPS | Konfigurasi di `deploy/` | Keterangan |
| :--- | :--- | :--- |
| `upstream anteraja_laravel { server anteraja_nginx:80; }` | `container_name: anteraja_nginx`<br>`expose: 80` | Terhubung langsung tanpa perlu mengubah config Nginx |
| `location /anter-aja-backend/ { proxy_pass http://anteraja_laravel/; }` | Laravel menerima path `/api/v1/...` | Path stripping Nginx berjalan sempurna |
| `location /anter-aja-backend/api/v1/sse/stream` | FrankenPHP Octane mendukung persistent stream | Buffer & chunked transfer di-handle Nginx |

> **Tips Tambahan Nginx:**
> Jika belum ada, Anda dapat menambahkan redirect exact match di Nginx agar user yang membuka tanpa trailing slash diarahkan ke trailing slash:
> ```nginx
> location = /anter-aja-backend {
>     return 301 https://$http_host/anter-aja-backend/;
> }
> ```

---

## 3. Langkah-Langkah Deployment di VPS

### Langkah 1: Pastikan Docker Network `backend-net` Sudah Ada
Jika belum pernah dibuat secara terpisah, buat network eksternal:
```bash
docker network create backend-net
```

### Langkah 2: Masuk ke Direktori `deploy/` & Siapkan File `.env`
```bash
cd deploy
cp .env.production.example .env
```

Edit file `.env` dan sesuaikan nilainya:
- **`APP_KEY`**: Generate key Laravel (bisa gunakan `docker run --rm -v $(pwd)/..:/app composer:2 php /app/artisan key:generate --show` atau isi dari lokal).
- **`DB_PASSWORD`**: Masukkan password database PostgreSQL yang kuat.
- **`APP_URL`**: `https://madgo.my.id/anter-aja-backend`

### Langkah 3: Build & Jalankan Container
Jalankan Docker Compose:
```bash
docker compose up -d --build
```

Container `app` memiliki **Entrypoint Otomatis** (`docker-entrypoint.sh`) yang akan:
1. Menunggu container `pgsql` dan `redis` sehat (*healthy*).
2. Menjalankan migrasi database otomatis (`php artisan migrate --force`).
3. Menjalankan seeder referensi FSM & role staf (`ReferenceDataSeeder`).
4. Mengoptimalkan cache Laravel (`config:cache`, `route:cache`, `view:cache`, `event:cache`).
5. Memulai FrankenPHP Octane di port 80 dengan worker pool.

---

## 4. Perintah Operasional & Manajemen

- **Melihat Log Realtime:**
  ```bash
  docker compose logs -f app
  ```

- **Melihat Log Database:**
  ```bash
  docker compose logs -f pgsql
  ```

- **Menjalankan Seeder Data Tambahan (misal Demo Data):**
  ```bash
  docker compose exec app php artisan db:seed --class="Database\Seeders\DemoDataSeeder" --force
  ```

- **Clear / Refresh Cache Laravel:**
  ```bash
  docker compose exec app php artisan optimize:clear
  docker compose exec app php artisan optimize
  ```

- **Restart Backend:**
  ```bash
  docker compose restart app
  ```

- **Stop Seluruh Stack:**
  ```bash
  docker compose down
  ```
