# Deploy Kubernetes — Anteraja Hackathon (prod-other)

Manifest ini men-deploy backend Laravel Anteraja Hackathon (FrankenPHP / Octane)
ke namespace `prod-other` di cluster `innovare-cloud`, dengan host
`hackathon.kagchi.my.id`.

## Ringkasan

| Komponen | Nilai |
| --- | --- |
| Namespace | `prod-other` |
| Image | `ghcr.io/kagchi/anteraja-hackathon:main` (dibangun CI `.github/workflows/docker.yaml`) |
| Port container | `8080` (Octane FrankenPHP) |
| Service | `anteraja-hackathon-service:80` → `http` |
| Ingress | `anteraja-hackathon-ingress` → `hackathon.kagchi.my.id` (TLS `anteraja-hackathon-tls`) |
| Database | CloudNativePG `postgres`, database `anteraja_hackathon`, role `anteraja` |
| Cache/Session/Queue | Dragonfly (`dragonfly:6379`), Redis DB `2` dan `3` |
| Image pull secret | `image-pull-secrets` (sudah ada di namespace) |

Arsitektur runtime sama dengan deployment capstone di namespace ini: satu
container `web` (Octane) dan satu container `worker` (`queue:work`), keduanya
membaca konfigurasi dari ConfigMap `anteraja-hackathon-config` dan Secret
`anteraja-hackathon-secrets`.

## 1. Prasyarat

- Namespace `prod-other` sudah ada.
- Secret `image-pull-secrets` (kredensial GHCR) sudah ada di namespace.
- Cluster CloudNativePG bernama `postgres` sudah berjalan (write service `postgres-rw`).
- `dragonfly` (Redis-compatible) sudah berjalan.

## 2. Membuat Secret

File `secret.example.yaml` hanya template. Secret asli dibuat langsung di
cluster agar kredensial tidak masuk repo. Nilai database/Redis diambil dari
Secret bawaan aplikasi ini (`anteraja-secrets`) lalu disesuaikan untuk
database dan Redis DB yang berbeda:

```bash
kubectl --context innovare-cloud -n prod-other get secret anteraja-secrets -o json \
  | jq '
      .metadata = {name: "anteraja-hackathon-secrets", namespace: "prod-other"}
      | .data.APP_NAME       = ("Anteraja Hackathon"                  | @base64)
      | .data.APP_URL        = ("https://hackathon.kagchi.my.id"      | @base64)
      | .data.DB_DATABASE    = ("anteraja_hackathon"                  | @base64)
      | .data.REDIS_DB       = ("2"                                   | @base64)
      | .data.REDIS_CACHE_DB = ("3"                                   | @base64)
      | .data.REDIS_PREFIX   = ("anteraja-hackathon:"                 | @base64)
      | .data.CACHE_PREFIX   = ("anteraja-hackathon:"                 | @base64)
      | .data.APP_KEY        = ("base64:GENERATE_ME"                  | @base64)
    ' \
  | kubectl --context innovare-cloud -n prod-other apply -f -
```

Ganti `APP_KEY` dengan hasil `php artisan key:generate --show`.

## 3. Membuat Database

`database.yaml` mendeklarasikan resource CNPG `Database` yang membuat database
`anteraja_hackathon` dengan owner role `anteraja` pada cluster `postgres`
(database terpisah dari aplikasi lain yang berbagi cluster yang sama).

## 4. Deploy Workload

```bash
kubectl --context innovare-cloud apply -k deploy/k8s
```

`apply -k` menerapkan ConfigMap, Database, Deployment, Service, dan Ingress.
Job migrasi sengaja dipisah (lihat di bawah).

## 5. Migrasi & Seeder

`migrate-job.yaml` menjalankan `migrate` lalu `DatabaseSeeder` (ReferenceDataSeeder
+ DemoDataSeeder, keduanya idempoten). Karena Job bersifat sekali-jalan,
hapus Job lama sebelum apply ulang:

```bash
kubectl --context innovare-cloud -n prod-other delete job anteraja-hackathon-migrate --ignore-not-found
kubectl --context innovare-cloud apply -f deploy/k8s/migrate-job.yaml
kubectl --context innovare-cloud -n prod-other logs -f job/anteraja-hackathon-migrate
```

## 6. Verifikasi

```bash
kubectl --context innovare-cloud -n prod-other get deploy,pod,svc,ingress -l app.kubernetes.io/name=anteraja-hackathon
kubectl --context innovare-cloud -n prod-other get certificate anteraja-hackathon-tls
curl -I https://hackathon.kagchi.my.id/up
```

## 7. Operasional

Deploy ulang image terbaru (setelah CI push tag `main`):

```bash
kubectl --context innovare-cloud -n prod-other rollout restart deploy/anteraja-hackathon
kubectl --context innovare-cloud -n prod-other rollout status  deploy/anteraja-hackathon
```

Menjalankan artisan di container yang berjalan:

```bash
kubectl --context innovare-cloud -n prod-other exec deploy/anteraja-hackathon -c web -- php artisan about
```
