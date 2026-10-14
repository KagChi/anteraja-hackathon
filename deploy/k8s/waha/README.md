# WAHA (prod-other)

WAHA (WhatsApp HTTP API) di namespace `prod-other`, host `https://waha.kagchi.my.id`,
dipakai bersama n8n. n8n memanggilnya lewat jaringan cluster di `http://waha`
(Service port 80 → container 3000).

| Komponen | Nilai |
| --- | --- |
| Image | `devlikeapro/waha:latest-2026.9.2` (WAHA Core) |
| Port | `3000` |
| Service | `waha:80` → `http` |
| Engine | `WEBJS` (Chromium) |
| Volume | `waha-data` (2Gi, Longhorn) → `/app/.sessions` + `/app/.media` |
| Ingress | `waha.kagchi.my.id`, TLS `waha-tls` |
| Auth API | `X-Api-Key` header (`WAHA_API_KEY`) |
| Dashboard | `https://waha.kagchi.my.id/dashboard` (`WAHA_DASHBOARD_USERNAME` / `..._PASSWORD`) |

## Secret

`secret.example.yaml` hanya template. Secret asli dibuat langsung di cluster:

```bash
API_KEY=$(openssl rand -hex 32)
DASH_PASS=$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)

kubectl --context innovare-cloud -n prod-other create secret generic waha-secrets \
  --from-literal=WAHA_API_KEY="$API_KEY" \
  --from-literal=WAHA_DASHBOARD_PASSWORD="$DASH_PASS" \
  --from-literal=WHATSAPP_SWAGGER_PASSWORD="$DASH_PASS" \
  --dry-run=client -o yaml | kubectl --context innovare-cloud -n prod-other apply -f -

echo "WAHA_API_KEY=$API_KEY"
echo "dashboard login: admin / $DASH_PASS"
```

Simpan `WAHA_API_KEY`; nilainya dipakai sebagai kredensial HTTP Request dari n8n.

## Deploy

```bash
kubectl --context innovare-cloud apply -k deploy/k8s/waha
kubectl --context innovare-cloud -n prod-other rollout status deploy/waha
```

Setelah jalan, buka `https://waha.kagchi.my.id/dashboard`, login, lalu start
sesi `default` dan scan QR dari WhatsApp di ponsel.

## Menghubungkan ke n8n

Dari dalam n8n, pakai node HTTP Request dengan
`http://waha/api/...` dan header `X-Api-Key: <WAHA_API_KEY>`.

## Catatan

- `WHATSAPP_API_KEY_EXCLUDE_PATH=health,ping` menjaga endpoint health tetap publik
  supaya probe Kubernetes tidak butuh kredensial.
- Origin tidak dikunci per-IP di ingress. ServiceLB k3s berjalan dengan
  `externalTrafficPolicy=Cluster`, jadi ingress-nginx melihat semua request
  datang dari `10.42.0.1` dan `whitelist-source-range` akan memblokir seluruh
  trafik. Untuk benar-benar mengunci origin, pakai salah satu: Cloudflare
  Authenticated Origin Pulls (mTLS), `externalTrafficPolicy: Local` pada Service
  ingress-nginx, atau firewall di VPS yang hanya mengizinkan rentang IP Cloudflare.
- Sesi WhatsApp dan file media disimpan di volume Longhorn, jadi tidak hilang
  saat pod restart.

