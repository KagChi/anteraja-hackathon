# FR-WA-001 — Kontrak webhook outbound

| Properti | Nilai |
| --- | --- |
| ID | FR-WA-001 |
| Modul | WA — Percakapan WhatsApp (n8n, WAHA, LLM) |
| Judul | Kontrak webhook outbound |
| Status | Live |
| Berkas modul | docs/frd/08-whatsapp.md |
| Aturan bisnis | BR-015, BR-016 |
| Berkas kode | app/Jobs/DispatchNotificationToN8n.php, deploy/n8n/workflows/anteraja-frozen-outbound.json |

## 1. Ringkasan

Perjanjian antara aplikasi dan n8n: bentuk body, header, dan makna jawaban. Karena
pemetaan template ke teks pesan hidup di n8n, kontrak ini adalah satu-satunya hal
yang perlu disepakati bila salah satu sisi berubah.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Pengirim | aplikasi Laravel lewat job |
| Penerima | workflow n8n anteraja-frozen-outbound |
| Header | X-Anteraja-Token berisi config coldchain.n8n.token |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint n8n | POST /webhook/anteraja-frozen/notify |
| Titik masuk aplikasi | DispatchNotificationToN8n |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | baris notifications berstatus QUEUED dan integrasi aktif |
| Postcondition | status baris menjadi SENT atau FAILED, atau tetap QUEUED untuk template yang belum dipetakan |

## 5. Input dan validasi

| Field body | Tipe | Keterangan |
| --- | --- | --- |
| notification_id | integer | id baris notifications |
| template | string | nama template |
| recipient_type | string | BUYER atau ADMIN (kurir tidak memakai WhatsApp) |
| payload | objek | konteks kirim lengkap |

Field penting di dalam payload: recipient_type, recipient_name, recipient_phone
(E.164), tracking_number, client_token, courier_name, window, dan untuk pembeli
ditambah client_url, address, dan buyer_name.

## 6. Proses langkah demi langkah

1. Aplikasi mengirim POST dengan header token dan timeout dari konfigurasi.
2. n8n memilih format pesan berdasarkan template.
3. n8n mengirim pesan lewat WAHA ke nomor recipient_phone.
4. n8n menyimpan template terakhir ke kolom last_template tabel anteraja_chat_state bersama context berisi window, client_url, dan decision.
5. n8n membalas JSON berisi delivered boolean, opsional reason, dan opsional message_id.
6. Aplikasi memperbarui status baris notifikasi sesuai jawaban itu.

Template yang dipakai sistem: schedule_request, schedule_rescheduled, on_the_way
courier_arrived, delivered, delivery_pin, issue_reported
issue_decision_update, task_rejected, resend_requested, dan confirmation_overdue.

## 7. Output

| Field jawaban n8n | Tipe | Keterangan |
| --- | --- | --- |
| delivered | boolean | true berarti pesan terkirim |
| message_id | string | opsional, disimpan ke payload |
| reason | string | alasan bila tidak dikirim, misalnya unmapped atau failed |

## 8. Error dan edge case

| Kasus | Makna |
| --- | --- |
| reason unmapped atau skipped | template belum punya format; baris tetap QUEUED |
| reason selain itu | baris FAILED dengan delivery_error |
| delivered tidak ada sama sekali | dianggap gagal, diproses sebagai reason unknown |
| Koneksi terputus | exception dilempar, antrean mencoba lagi |
| Token salah | n8n menolak; aplikasi mencatat FAILED |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Pesan WhatsApp terkirim | perangkat penerima |
| State percakapan | tabel anteraja_chat_state pada database n8n |
| Status notifikasi | notifications |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Job | Jobs/DispatchNotificationToN8n.php |
| Support | Support/NotificationQueue.php |
| Workflow | deploy/n8n/workflows/anteraja-frozen-outbound.json |
| State | deploy/n8n/state-table.sql |
| Dokumentasi | deploy/n8n/README.md |
| Test | tests/Feature/WhatsAppNotificationFlowTest.php (14 test).github/workflows/n8n-scenarios.yaml |

## 11. Skenario uji

1. Given template schedule_request untuk pembeli, When job berjalan, Then permintaan memuat tracking_number, recipient_phone E.164, window, dan client_url.
2. Given template yang belum dipetakan, When job berjalan, Then status tetap QUEUED.
3. Given n8n menjawab 500, When job berjalan, Then status FAILED dan payload memuat delivery_error.

## 12. Yang perlu dikonfirmasi

1. Apakah kontrak ini perlu dibekukan sebagai skema versi, mengingat perubahan nama field akan memutus workflow tanpa peringatan?
2. Kurir tidak lagi menerima WhatsApp; template issue_decision_update sekarang satu-satunya kabar keputusan kendala ke pembeli. Apakah teksnya perlu membedakan tiap keputusan lebih jauh?

