# FR-NOTIF-001 — Menulis notifikasi ke outbox

| Properti | Nilai |
| --- | --- |
| ID | FR-NOTIF-001 |
| Modul | NOTIF — Notification Outbox dan Penjadwalan |
| Judul | Menulis notifikasi ke outbox |
| Status | Live |
| Berkas modul | docs/frd/07-notifikasi-outbox.md |
| Aturan bisnis | BR-015 |
| Berkas kode | app/Support/NotificationQueue.php |

## 1. Ringkasan

Semua pemberitahuan ditulis sebagai baris data lebih dulu, bukan dikirim langsung.
Dengan begitu kegagalan pengiriman menjadi status pada satu baris, bukan exception
yang menghilang, dan isi pesan dapat diaudit.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | sistem, dipanggil dari Action domain |
| Penerima | BUYER, COURIER, atau ADMIN |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Method | NotificationQueue::queue dan pembungkusnya |
| Pembungkus | queueTaskRejected, queueOnTheWay, queueCourierArrived, queueDelivered, queueIssueReported, queueIssueOptions |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket ada |
| Postcondition | satu baris notifications berstatus QUEUED dan satu job terjadwal setelah commit |

## 5. Input dan validasi

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| package | Package | paket sumber |
| type | RecipientType | BUYER, COURIER, ADMIN |
| recipientId | integer | id pembeli atau staf |
| template | string | nama template |
| payload | array | data tambahan, boleh kosong |

## 6. Proses langkah demi langkah

1. Bila penerima BUYER dan payload belum memuat client_url, isi dengan ClientLinkService::entry.
2. Perkaya payload: recipient_type, recipient_name, recipient_phone dinormalkan E.164, tracking_number, client_token, courier_name, dan window.
3. Untuk penerima BUYER, isi juga address dan buyer_name dari data pembeli bila belum ada.
4. Untuk penerima COURIER dan ADMIN, cari nama dan nomor staf dari baris staff.
5. Nomor telepon dinormalkan lewat PhoneFormatter; nilai kosong dibiarkan null.
6. Buat baris notifications dengan status QUEUED.
7. Jadwalkan DispatchNotificationToN8n dengan afterCommit agar hanya berjalan setelah transaksi selesai.
8. Kembalikan model Notification.

Daftar template yang dipakai sistem: schedule_request, schedule_rescheduled
on_the_way, courier_arrived, delivered, delivery_pin, issue_options, issue_reported
issue_decision_update, task_rejected
resend_requested, buyer_issue_choice, dan confirmation_overdue.

## 7. Output

| Field notifications | Tipe | Keterangan |
| --- | --- | --- |
| package_id | bigint | paket |
| recipient_type | string | BUYER, COURIER, ADMIN |
| recipient_id | bigint | id penerima |
| template | string | nama template |
| payload | jsonb | berisi konteks kirim lengkap |
| status | string | QUEUED |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Nomor penerima kosong | recipient_phone null, bukan string kosong |
| Penerima ADMIN tanpa staf ditemukan | nama dan nomor null |
| Transaksi digulung setelah penulisan | baris dan job ikut hilang karena afterCommit |
| Tidak ada admin aktif | pembungkus yang mengirim ke semua admin tidak membuat baris apa pun |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Baris outbox | notifications |
| Job | DispatchNotificationToN8n pada antrean |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Support | Support/NotificationQueue.php, Support/DisplayTime.php, Support/PhoneFormatter.php |
| Model | app/Models/Notification.php |
| Migrasi | 0001_01_01_000014_create_notifications.php |
| Service | Services/Client/ClientLinkService |
| Test | tests/Feature/WhatsAppNotificationFlowTest.php test_notification_payload_carries_whatsapp_delivery_context, test_a_queued_confirmation_carries_the_formatted_window |

## 11. Skenario uji

1. Given notifikasi pembeli tanpa client_url pada payload, When ditulis, Then payload memuat client_url, recipient_phone E.164, tracking_number, dan window.
2. Given transaksi yang digulung setelah notifikasi ditulis, When transaksi gagal, Then tidak ada baris notifications dan tidak ada job terkirim.

## 12. Yang perlu dikonfirmasi

1. Apakah perlu kebijakan retensi untuk tabel notifications yang terus bertambah?
2. Apakah perlu kolom prioritas agar notifikasi penting (misalnya kendala) tidak tertunda di belakang notifikasi rutin?

