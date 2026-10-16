# FR-WA-002 — Percakapan masuk dan pemetaan aksi

| Properti | Nilai |
| --- | --- |
| ID | FR-WA-002 |
| Modul | WA — Percakapan WhatsApp (n8n, WAHA, LLM) |
| Judul | Percakapan masuk dan pemetaan aksi |
| Status | Live |
| Berkas modul | docs/frd/08-whatsapp.md |
| Berkas kode | deploy/n8n/workflows/anteraja-frozen-inbound.json |

## 1. Ringkasan

Bot tidak menebak. Balasan pelanggan hanya diperlakukan sebagai aksi bila cocok
dengan langkah yang sedang menunggu, dan langkah itu dibaca dari state percakapan
yang ditulis flow outbound.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | workflow n8n inbound |
| Autentikasi masuk | header X-Anteraja-Hook dicocokkan credential Anteraja WAHA Hook |
| Autentikasi ke aplikasi | X-Anteraja-Token pada endpoint internal lookup; API guest memakai token tautan |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Webhook | POST /webhook/anteraja-frozen/waha-inbound |
| Sumber | WAHA, event message |
| Konfigurasi WAHA | WHATSAPP_HOOK_URL dan WHATSAPP_HOOK_EVENTS pada configmap |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | baris anteraja_chat_state ada untuk nomor itu, atau pesan memuat nomor resi agar state disegarkan |
| Postcondition | langkah yang menunggu terpenuhi dan state dimajukan, atau state tetap menunggu bila jawaban tidak dikenali |

## 5. Input dan validasi

| Sumber | Keterangan |
| --- | --- |
| payload.from | alamat pengirim, dapat berbentuk nomor atau LID |
| payload.fromMe | pesan dari diri sendiri diabaikan |
| payload.body | teks balasan pelanggan |

## 6. Proses langkah demi langkah

1. Node Resolusi LID memanggil endpoint WAHA untuk memperoleh nomor telepon bila pengirim berbentuk LID. Bila tidak ada nomor, perilaku lama dipakai.
2. Pesan dicari ke baris state berdasarkan nomor hasil resolusi.
3. Bila pesan memuat nomor resi, state disegarkan lewat endpoint internal lookup.
4. Klasifikasi maksud dilakukan LLM deepseek-v4-flash dengan fallback aturan sederhana.
5. Aksi dijalankan sesuai langkah yang menunggu:
   - schedule_request atau schedule_rescheduled dan jawaban setuju memanggil POST schedule/confirm.
   - schedule_request atau schedule_rescheduled dan permintaan jadwal lain memanggil POST schedule/reschedule, meminta tanggal dan jam bila belum lengkap.
   - on_the_way, courier_arrived, delivered, dan issue_decision_update memakai balasan apa pun tanpa aksi, hanya menjawab status singkat.
   - Ada paket tanpa langkah tertunda: jawab status paket dan jadwalnya.
   - Tidak ada state: kirim teks bantuan yang meminta nomor resi.
6. Setelah aksi berhasil, last_template dimajukan menjadi confirmed atau reschedule_requested.
7. Bila API menolak, pesan penolakan diteruskan apa adanya dan state tidak dimajukan.
8. Balasan dikirim lewat WAHA ke nomor hasil resolusi.

Pesan yang dikirim bot tidak pernah memuat URL atau token, karena tautan API
mengandung client_token yang tidak dapat dibuka pelanggan.

## 7. Output

| Keluaran | Keterangan |
| --- | --- |
| Pesan balasan | teks singkat sesuai langkah atau status |
| Aksi ke aplikasi | confirm, reschedule, atau issue choice |
| State | kolom last_template dan context pada anteraja_chat_state |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Chat berbentuk LID tanpa nomor yang dapat diresolusi | balasan dikirim ke alamat lama; bila ditolak WAHA, n8n mencatat eksekusi gagal |
| Balasan tidak dikenali | tidak ada aksi, bot mengirim bantuan |
| API menolak aksi | pesan penolakan diteruskan, state tidak dimajukan |
| State tertinggal setelah admin menyusun ulang | disegarkan ketika pelanggan menyebut nomor resi |
| Pesan dari diri sendiri | diabaikan |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| State percakapan | anteraja_chat_state |
| Aksi domain | confirm jadwal, jadwal ulang, atau pilihan kendala beserta seluruh efeknya |
| Pesan balasan | WhatsApp pelanggan |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Workflow | deploy/n8n/workflows/anteraja-frozen-inbound.json |
| State | deploy/n8n/state-table.sql |
| Konfigurasi WAHA | deploy/k8s/waha/configmap.yaml dan deployment.yaml |
| Dokumentasi | deploy/n8n/README.md tabel last_template dan bagian LID |
| Test | deploy/n8n/tests/inbound-scenarios.mjs melalui .github/workflows/n8n-scenarios.yaml |
| Aksi aplikasi | routes/api.php grup guest, Actions/Client/ConfirmSchedule.php, RequestReschedule.php |

## 11. Skenario uji

1. Given state schedule_request dan pelanggan menjawab "siap", When webhook diproses, Then schedule/confirm dipanggil, paket CONFIRMED, dan last_template menjadi confirmed.
2. Given state issue_decision_update dan pelanggan menjawab "1", When webhook diproses, Then tidak ada aksi dan bot menjawab status kendala.
3. Given tidak ada state dan pelanggan mengetik nomor resi, When webhook diproses, Then lookup dipanggil dan state dibuat.

## 12. Yang perlu dikonfirmasi

1. Apakah percakapan perlu diarsipkan untuk audit, mengingat state hidup di database n8n?
2. Bahasa bot hanya Indonesia. Apakah perlu dukungan bahasa lain?
3. Apakah LLM perlu batas biaya harian, mengingat setiap pesan masuk memanggil model?

