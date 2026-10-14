# n8n (prod-other)

n8n workflow automation di namespace `prod-other`, host `https://n8n.kagchi.my.id`.

| Komponen | Nilai |
| --- | --- |
| Image | `n8nio/n8n:2.44.1` |
| Port | `5678` |
| Service | `n8n:80` → `http` |
| Database | CloudNativePG `postgres`, database `n8n`, user `postgres` |
| Volume | `n8n-data` (2Gi, Longhorn) → `/home/node/.n8n` |
| Ingress | `n8n.kagchi.my.id`, TLS `n8n-tls` |

## Secret

`secret.example.yaml` hanya template. Secret asli dibuat langsung di cluster:

```bash
kubectl --context innovare-cloud -n prod-other get secret postgres-superuser -o json \
  | jq '{apiVersion:"v1",kind:"Secret",type:"Opaque",
         metadata:{name:"n8n-secrets",namespace:"prod-other",labels:{"app.kubernetes.io/name":"n8n"}},
         data:{DB_POSTGRESDB_PASSWORD:.data.password,
               N8N_ENCRYPTION_KEY:("<ENCRYPTION_KEY>"|@base64)}}' \
  | kubectl --context innovare-cloud -n prod-other apply -f -
```

`N8N_ENCRYPTION_KEY` wajib dipertahankan. Kalau nilainya berubah atau hilang,
semua kredensial yang tersimpan tidak bisa didekripsi lagi.

## Deploy

```bash
kubectl --context innovare-cloud apply -k deploy/k8s/n8n
kubectl --context innovare-cloud -n prod-other rollout status deploy/n8n
```

Migrasi skema dijalankan otomatis oleh n8n saat pertama start.

## Catatan

- Origin tidak dikunci per-IP di ingress. ServiceLB k3s berjalan dengan
  `externalTrafficPolicy=Cluster`, jadi ingress-nginx melihat semua request
  datang dari `10.42.0.1` dan `whitelist-source-range` akan memblokir seluruh
  trafik. Untuk benar-benar mengunci origin, pakai salah satu: Cloudflare
  Authenticated Origin Pulls (mTLS), `externalTrafficPolicy: Local` pada Service
  ingress-nginx, atau firewall di VPS yang hanya mengizinkan rentang IP Cloudflare.
- `strategy: Recreate` dipakai karena PVC bersifat RWO dan hanya boleh ada satu
  instance n8n yang menulis ke database secara bersamaan.

