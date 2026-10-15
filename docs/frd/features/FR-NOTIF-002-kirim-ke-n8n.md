# FR-NOTIF-002 — Mengirim notifikasi ke n8n

| Properti | Nilai |
| --- | --- |
| ID | FR-NOTIF-002 |
| Modul | NOTIF — Notification Outbox dan Penjadwalan |
| Judul | Mengirim notifikasi ke n8n |
| Status | Live |
| Berkas modul | docs/frd/07-notifikasi-outbox.md |
| Aturan bisnis | BR-015, BR-016 |
| Berkas kode | app/Jobs/DispatchNotificationToN8n.php |

## 1. Ringkasan

Job yang menyerahkan satu baris notifikasi ke workflow n8n dan menerjemahkan
jawabannya menjadi status: SENT, tetap QUEUED, atau FAILED. Format pesan WhatsApp
bukan tanggung jawab aplikasi.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | queue worker |
| Autentikasi | header X-Anteraja-Token berisi config coldchain.n8n.token |
| Konfigurasi | enabled, webhook_url, token, timeout |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Penjadwalan | NotificationQueue setelah commit |
| Kelas | Jobs/DispatchNotificationToN8n dengan tries 3 |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | integrasi aktif dan baris notifikasi masih QUEUED |
| Postcondition | status menjadi SENT, tetap QUEUED, atau FAILED |

## 5. Input dan validasi

| Sumber | Keterangan |
| --- | --- |
| notificationId | id baris notifikasi, satu-satunya argumen konstruktor |
| config coldchain.n8n | webhook_url, token, timeout bawaan 10 detik |

## 6. Proses langkah demi langkah

1. Baca konfigurasi. Bila enabled false atau webhook_url kosong, job selesai tanpa mengubah apa pun.
2. Ambil baris notifikasi. Bila tidak ada atau statusnya bukan QUEUED, job selesai.
3. Kirim POST JSON berisi notification_id, template, recipient_type, dan payload dengan header token dan Accept JSON.
4. Bila terjadi ConnectionException, lemparkan ulang agar antrean mencoba lagi.
5. Bila respons gagal (non-2xx), tandai FAILED dengan alasan "HTTP {status}".
6. Bila respons memuat delivered true, tulis wa_message_id ke payload bila ada, set status SENT dan sent_at sekarang.
7. Bila respons tidak delivered dan reason-nya unmapped atau skipped, biarkan QUEUED dan tulis log info berisi notification_id dan template.
8. Selain itu, tandai FAILED dengan payload.delivery_error berisi potongan alasan maksimal 300 karakter.
9. Bila seluruh percobaan habis, method failed menandai FAILED dengan pesan exception.

Backoff antar percobaan adalah 10 detik lalu 30 detik, dengan maksimal tiga percobaan.

## 7. Output

| Field notifications | Nilai |
| --- | --- |
| status | SENT atau FAILED, atau tetap QUEUED |
| sent_at | terisi saat SENT |
| payload.wa_message_id | diisi bila n8n mengirim message_id |
| payload.delivery_error | diisi saat FAILED |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Integrasi dimatikan | tidak ada perubahan status, baris tetap QUEUED |
| n8n tidak terjangkau | exception dilempar, antrean mencoba lagi |
| n8n menjawab 500 | FAILED dengan alasan HTTP 500 |
| Template belum dipetakan | tetap QUEUED tanpa penanda gagal |
| Baris sudah SENT | dilewati sehingga retry tidak mengirim ulang |
| Seluruh percobaan habis | FAILED dengan pesan exception |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Perubahan status | notifications |
| Log | baris info untuk kasus unmapped, log error untuk kegagalan job |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Job | app/Jobs/DispatchNotificationToN8n.php |
| Konfigurasi | config/coldchain.php bagian n8n |
| Pemanggil | Support/NotificationQueue.php |
| Test | tests/Feature/WhatsAppNotificationFlowTest.php test_job_marks_notification_sent_when_n8n_delivers, test_job_marks_notification_failed_when_n8n_rejects, test_job_leaves_unmapped_template_queued, test_job_skips_when_webhook_is_disabled |
| Dokumen | deploy/n8n/README.md |

## 11. Skenario uji

1. Given n8n menjawab delivered true dengan message_id, When job selesai, Then status SENT, sent_at terisi, dan payload memuat wa_message_id.
2. Given n8n menjawab reason unmapped, When job selesai, Then status tetap QUEUED.
3. Given webhook dimatikan, When job berjalan, Then tidak ada perubahan status.
4. Given n8n menjawab 500, When job selesai, Then status FAILED dengan delivery_error.

## 12. Yang perlu dikonfirmasi

1. Notifikasi FAILED tidak pernah dicoba ulang secara otomatis setelah tiga percobaan. Apakah perlu mekanisme antrean ulang manual dari konsol?
2. Apakah perlu pemantauan jumlah notifikasi FAILED sebagai alarm operasional?

