# FRD Modul NOTIF — Notification Outbox dan Penjadwalan

Modul ini adalah satu jalur keluar untuk seluruh komunikasi pelanggan. Aplikasi
tidak pernah memanggil WhatsApp secara langsung.

Aturan bisnis terkait: BR-015, BR-016.

---

### FR-NOTIF-001 — Menulis notifikasi ke outbox

- Deskripsi: Sistem HARUS menulis setiap pemberitahuan sebagai baris pada tabel notifications berstatus QUEUED, lengkap dengan konteks pengiriman, sebelum job pengiriman dijalankan.
- Aktor & Otorisasi: sistem; dipanggil dari Action domain, bukan dari controller.
- Trigger: pemanggilan NotificationQueue::queue dan pembungkusnya (queueTaskRejected, queueOnTheWay, queueCourierArrived, queueDelivered, queueDeliveryPin, queueIssueReported).
- Precondition: paket ada.
- Input & Validasi: paket, recipient_type (BUYER, COURIER, ADMIN), recipient_id, template, dan payload array opsional.
- Proses / Business Rules: bila penerima adalah BUYER dan payload belum memuat client_url, tautan masuk diisi otomatis dari ClientLinkService (BR-015). Payload kemudian diperkaya: recipient_type, recipient_name, recipient_phone ternormalisasi E.164, tracking_number, client_token, courier_name, dan window yang dihitung DisplayTime::windowLabel bila belum ada. Untuk BUYER, address dan buyer_name juga diisi dari data pembeli. Nomor staf dicari dari baris staff untuk penerima COURIER dan ADMIN. Baris notifications dibuat dengan status QUEUED, lalu DispatchNotificationToN8n dijadwalkan dengan afterCommit agar hanya berjalan setelah transaksi selesai.
- Output / Response: objek Notification.
- Error & Edge Cases: recipient_phone null dibiarkan null, bukan string kosong. Notifikasi yang dibuat di dalam transaksi yang kemudian digulung tidak akan pernah terkirim karena penjadwalan memakai afterCommit.
- Side Effects: satu baris notifications QUEUED; satu job pada antrean setelah commit.
- Postcondition: notifikasi siap dikirim oleh worker.
- Traceability: Support/NotificationQueue.php, app/Models/Notification.php, database/migrations/0001_01_01_000014_create_notifications.php, Support/DisplayTime.php, Support/PhoneFormatter.php, Services/Client/ClientLinkService.php, tests/Feature/WhatsAppNotificationFlowTest.php test_notification_payload_carries_whatsapp_delivery_context dan test_a_queued_confirmation_carries_the_formatted_window.
- Acceptance Criteria:
  - Given perubahan status yang memicu notifikasi pembeli tanpa client_url pada payload, When notifikasi ditulis, Then baris notifications memuat client_url, recipient_phone E.164, tracking_number, dan window.
  - Given transaksi yang digulung setelah notifikasi ditulis, When transaksi gagal, Then baris notifications tidak ada dan tidak ada job yang terkirim.
- Status: Live

---

### FR-NOTIF-002 — Mengirim notifikasi ke n8n

- Deskripsi: Sistem HARUS mengirim satu baris notifikasi ke webhook n8n, menandainya SENT bila n8n menyatakan terkirim, membiarkannya QUEUED bila template belum dipetakan, dan menandainya FAILED bila terjadi penolakan lain.
- Aktor & Otorisasi: queue worker; webhook dipanggil dengan header X-Anteraja-Token.
- Trigger: eksekusi job DispatchNotificationToN8n dengan notification_id.
- Precondition: config coldchain.n8n.enabled true dan webhook_url tidak kosong; baris notifikasi masih berstatus QUEUED.
- Input & Validasi: tidak ada input pengguna. Job membaca config coldchain.n8n untuk webhook_url, token, dan timeout dengan bawaan 10 detik.
- Proses / Business Rules: bila integrasi tidak aktif atau URL kosong, job selesai tanpa perubahan (BR-016 hanya berlaku setelah permintaan dikirim). Permintaan dikirim sebagai JSON berisi notification_id, template, recipient_type, dan payload. Bila n8n menjawab delivered true, payload diperkaya wa_message_id bila ada, status menjadi SENT, dan sent_at diisi. Bila jawaban memuat reason unmapped atau skipped, baris dibiarkan QUEUED dan ditulis log info berisi notification_id dan template, supaya template yang belum dipetakan tidak dianggap gagal. Selain itu, status menjadi FAILED dan payload.delivery_error diisi potongan alasan maksimal 300 karakter. Kegagalan koneksi tidak ditangani sebagai kegagalan permanen: exception dilempar ulang agar antrean mencoba lagi. Job memakai tries 3 dengan backoff 10 detik lalu 30 detik.
- Output / Response: tidak ada respons HTTP; hasilnya terlihat pada kolom status, sent_at, dan payload.
- Error & Edge Cases: baris notifikasi yang sudah tidak QUEUED dilewati, sehingga retry yang datang setelah sukses tidak mengirim ulang. Kegagalan HTTP apa pun selain kasus unmapped menghasilkan FAILED, termasuk 500 dari n8n. Method failed mencatat FAILED beserta pesan exception saat seluruh percobaan habis.
- Side Effects: kolom status, sent_at, dan payload pada baris notifications; log aplikasi untuk kasus unmapped.
- Postcondition: notifikasi berada pada salah satu status akhir SENT atau FAILED, atau tetap QUEUED untuk template yang belum dipetakan.
- Traceability: Jobs/DispatchNotificationToN8n.php, config/coldchain.php n8n, Support/NotificationQueue.php, tests/Feature/WhatsAppNotificationFlowTest.php test_job_marks_notification_sent_when_n8n_delivers, test_job_marks_notification_failed_when_n8n_rejects, test_job_leaves_unmapped_template_queued, test_job_skips_when_webhook_is_disabled.
- Acceptance Criteria:
  - Given n8n menjawab delivered true dengan message_id, When job selesai, Then status SENT, sent_at terisi, dan payload memuat wa_message_id.
  - Given n8n menjawab reason unmapped, When job selesai, Then status tetap QUEUED dan tidak ada penanda gagal palsu.
- Status: Live

---

### FR-NOTIF-003 — Pengingat konfirmasi tak dijawab

- Deskripsi: Sistem HARUS menaikkan pengingat ke admin untuk konfirmasi jadwal yang belum dijawab melewati ambang waktu, tanpa mengulang pengingat yang sama.
- Aktor & Otorisasi: scheduler; perintah artisan internal.
- Trigger: perintah confirmations:remind-unanswered yang dijadwalkan setiap 15 menit di routes/console.php.
- Precondition: ada baris schedule_confirmations berstatus PENDING yang requested_at-nya lebih lama dari ambang.
- Input & Validasi: ambang diambil dari config coldchain.client.unanswered_after_minutes dengan bawaan 120 menit.
- Proses / Business Rules: kueri memilih konfirmasi PENDING yang requested_at lebih lama dari potongan waktu dan paketnya masih AWAITING_CONFIRMATION. Untuk setiap konfirmasi, sistem mengecek apakah sudah ada baris notifications bertipe ADMIN bertemplate confirmation_overdue dengan payload.confirmation_id yang sama; bila ada, konfirmasi itu dilewati sehingga pengingat tidak berulang. Bila belum ada, seluruh admin aktif menerima notifikasi confirmation_overdue berisi tracking_number, confirmation_id, dan age_minutes yang dihitung dari selisih waktu.
- Output / Response: kode keluar 0 dengan keluaran teks jumlah paket yang diproses, atau pesan bahwa tidak ada konfirmasi telat.
- Error & Edge Cases: admin nonaktif tidak menerima pengingat. Paket yang sudah berpindah dari AWAITING_CONFIRMATION tidak lagi menjadi sasaran meski konfirmasinya masih PENDING. Bila tidak ada admin aktif, tidak ada notifikasi yang dibuat meski konfirmasi telat terdeteksi.
- Side Effects: baris notifications QUEUED berjumlah sesuai jumlah admin aktif per konfirmasi yang telat.
- Postcondition: admin mengetahui paket yang menunggu jawaban pembeli.
- Traceability: routes/console.php, app/Console/Commands/RemindUnansweredConfirmations.php, config/coldchain.php client.unanswered_after_minutes, Support/NotificationQueue.php, tests/Feature/ClientFlowTest.php test_remind_unanswered_confirmations_command.
- Acceptance Criteria:
  - Given konfirmasi PENDING berumur tiga jam dan dua admin aktif, When perintah dijalankan, Then dua baris notifications confirmation_overdue dibuat.
  - Given perintah dijalankan dua kali berturut-turut, When perintah kedua berjalan, Then tidak ada baris pengingat tambahan untuk konfirmasi yang sama.
- Status: Live

