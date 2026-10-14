# n8n WhatsApp flow (Anteraja Frozen)

Dua workflow n8n yang menghubungkan aplikasi Laravel dengan WAHA:

| Workflow | File | Webhook |
| --- | --- | --- |
| Outbound WhatsApp | `workflows/anteraja-frozen-outbound.json` | `POST /webhook/anteraja-frozen/notify` |
| Inbound WhatsApp (LLM) | `workflows/anteraja-frozen-inbound.json` | `POST /webhook/anteraja-frozen/waha-inbound` |

Alur singkat: app membuat baris di tabel `notifications` -> job
`DispatchNotificationToN8n` mem-POST template + payload -> workflow outbound
merender pesan dan mengirim lewat WAHA -> balasan pembeli masuk lewat webhook
WAHA -> workflow inbound menentukan maksud balasan (LLM, dengan fallback
aturan sederhana) -> aksi dipanggil ke API guest aplikasi lalu dibalas ke pembeli.

## Kredensial

Semua rahasia masuk sebagai credential n8n (bukan env variable), jadi tidak ada
perubahan setelan global n8n dan tidak ada nilai asli di repo:

| Credential | ID | Isi |
| --- | --- | --- |
| Anteraja State DB | `anterajaStatePg` | Postgres `postgres-rw`, database `n8n` (tabel state percakapan) |
| Anteraja WAHA | `anterajaWaha` | Header `X-Api-Key` untuk API WAHA |
| Anteraja LLM | `anterajaLlm` | Header `Authorization: Bearer <API key>` |
| Anteraja App Hook | `anterajaAppHook` | Header `X-Anteraja-Token` yang dipakai app |
| Anteraja WAHA Hook | `anterajaWahaHook` | Header `X-Anteraja-Hook` yang dipakai WAHA |

Sumber nilainya adalah Secret `n8n-secrets` dan `waha-secrets` di namespace
`prod-other`:

| Secret | Key |
| --- | --- |
| `n8n-secrets` | `DB_POSTGRESDB_PASSWORD`, `WAHA_API_KEY`, `ANTERAJA_WEBHOOK_TOKEN`, `WAHA_HOOK_TOKEN`, `OPENAI_COMPAT_API_KEY` |
| `waha-secrets` | `WAHA_API_KEY`, `WHATSAPP_HOOK_CUSTOM_HEADERS` |
| `anteraja-hackathon-secrets` | `N8N_WEBHOOK_TOKEN` (harus sama dengan `ANTERAJA_WEBHOOK_TOKEN`) |

URL dan nama model bukan rahasia, jadi ditulis langsung di workflow:
`http://waha` untuk WAHA, `http://anteraja-hackathon-service` untuk app, dan
`http://nine-router-service:20128/v1` untuk endpoint OpenAI-compatible dengan
model `deepseek-v4-flash`. Kunci LLM-nya adalah API key 9router.

## Deploy

```bash
# 1. Tabel state percakapan di database n8n
kubectl --context innovare-cloud -n prod-other exec -i postgres-1 -c postgres -- \
  psql -U postgres -d n8n < deploy/n8n/state-table.sql

# 2. Import credential + workflow
./deploy/n8n/deploy.sh
```

`deploy.sh` membuat kelima credential dengan ID tetap, mengimpor kedua workflow,
lalu mengaktifkannya (`--activeState=fromJson`).

## Hook WAHA

`deploy/k8s/waha/configmap.yaml` mengarahkan event pesan masuk ke n8n:

```yaml
WHATSAPP_HOOK_URL: "http://n8n/webhook/anteraja-frozen/waha-inbound"
WHATSAPP_HOOK_EVENTS: "message"
```

`WHATSAPP_HOOK_CUSTOM_HEADERS` (format WAHA `Nama:value`) diisi dari Secret lewat
`deploy/k8s/waha/deployment.yaml`, dan node webhook inbound memvalidasinya dengan
credential `Anteraja WAHA Hook`. Request tanpa header yang cocok ditolak n8n.

## Alamat balasan (LID)

WhatsApp mulai mengalamatkan sebagian chat dengan **LID** (`<angka>@lid`),
bukan nomor telepon. Balasan ke `<lid>@c.us` ditolak WhatsApp Web — WAHA
menjawab `500` di `/api/sendText` — dan baris `anteraja_chat_state` yang
ditulis flow outbound memakai nomor telepon, jadi pencarian state ikut meleset.
Gejalanya: pembeli mengirim pesan, bot tidak membalas apa pun, dan n8n mencatat
eksekusi gagal di node `Balas WAHA`.

Karenanya inbound diawali node **Resolusi LID** yang memanggil
`GET http://waha/api/anteraja/lids/<id>` (kredensial `anterajaWaha`) lalu
memakai `pn` hasilnya:

- ada `pn` -> balasan dikirim ke `<pn>@c.us` dan state dicari dengan nomor itu;
- tanpa `pn` (chat `@c.us` biasa, atau lookup gagal) -> perilaku lama dipakai.

Endpoint `GET /api/{session}/lids/{id}` menerima id dengan atau tanpa akhiran
`@lid`. Bila WAHA mengubah skema LID-nya, cek ulang endpoint ini sebelum
mengubah node tersebut.

## Troubleshooting

- `GET /api/sessions` bisa menampilkan `webhooks: []` walaupun hook dari env
  (`WHATSAPP_HOOK_URL`) sedang jalan — sesi menyimpan konfigurasi sendiri dan
  env dipakai saat runtime. Jangan mendiagnosis dari field itu saja; lihat log
  WAHA untuk baris `WebhookSender` yang mencatat POST ke n8n beserta status
  responnya.
- `Balas WAHA` gagal dengan `500` dari `/api/sendText`: hampir selalu alamat
  chat yang salah (LID ditulis sebagai `@c.us`), bukan WAHA yang mati.

## Uji manual

```bash
# Outbound
curl -sS -X POST https://n8n.kagchi.my.id/webhook/anteraja-frozen/notify \
  -H "Content-Type: application/json" \
  -H "X-Anteraja-Token: $ANTERAJA_WEBHOOK_TOKEN" \
  -d '{"template":"schedule_request","payload":{"recipient_name":"Rina","recipient_phone":"+62812xxxxxxx","tracking_number":"AJ-FRZ-TEST","address":"Jl. Melati 14","window":"Besok 13:00-15:00"}}'

# Inbound (simulasi balasan pembeli)
curl -sS -X POST https://n8n.kagchi.my.id/webhook/anteraja-frozen/waha-inbound \
  -H "Content-Type: application/json" \
  -H "X-Anteraja-Hook: $WAHA_HOOK_TOKEN" \
  -d '{"event":"message","session":"anteraja","payload":{"from":"62812xxxxxxx@c.us","fromMe":false,"body":"2"}}'
```
