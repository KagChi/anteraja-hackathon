# FRD Modul WA — Percakapan WhatsApp (n8n, WAHA, LLM)

Aplikasi ini tidak memanggil WhatsApp. Ia menyerahkan template dan payload ke n8n
dan n8n yang merender pesan, mengirim lewat WAHA, serta menerjemahkan balasan
pelanggan menjadi aksi ke API aplikasi.

Aturan bisnis terkait: BR-015, BR-016, BR-021.

---

### FR-WA-001 — Kontrak webhook outbound

- Deskripsi: Sistem HARUS menyerahkan notifikasi ke workflow n8n dalam bentuk yang tetap: notification_id, template, recipient_type, dan payload, dengan header autentikasi bersama.
- Aktor & Otorisasi: n8n sebagai penerima; aplikasi sebagai pengirim dengan header X-Anteraja-Token berisi config coldchain.n8n.token.
- Trigger: job DispatchNotificationToN8n (lihat FR-NOTIF-002).
- Precondition: notifikasi berstatus QUEUED dan integrasi aktif.
- Input & Validasi: body JSON dengan notification_id integer, template string, recipient_type salah satu BUYER, COURIER, atau ADMIN, dan payload objek.
- Proses / Business Rules: payload selalu memuat recipient_name, recipient_phone dalam E.164, tracking_number, client_token, courier_name, window, dan untuk penerima BUYER juga client_url dan address. Template yang dipakai sistem saat ini: schedule_request, schedule_rescheduled, on_the_way, courier_arrived, delivered, issue_options, issue_reported, issue_decision, issue_decision_update, task_assigned, task_rejected, resend_requested, buyer_issue_choice, dan confirmation_overdue. n8n memakai template untuk memilih format pesan, dan menyimpan template terakhir ke kolom last_template tabel anteraja_chat_state di database n8n sebagai penanda langkah yang sedang menunggu jawaban.
- Output / Response: respons JSON dari n8n, minimal memuat delivered boolean dan opsional reason serta message_id.
- Error & Edge Cases: template yang belum dipetakan n8n menjawab reason unmapped dan baris notifikasi dibiarkan QUEUED. Template issue_decision_update dan issue_decision dipakai untuk memberi tahu hasil keputusan kendala; template buyer_issue_choice adalah notifikasi ke admin, bukan ke pelanggan.
- Side Effects: pesan WhatsApp terkirim kepada penerima; baris anteraja_chat_state ditulis atau diperbarui di database n8n.
- Postcondition: pelanggan atau staf menerima pesan, dan state percakapan menandai langkah yang menunggu.
- Traceability: Support/NotificationQueue.php, Jobs/DispatchNotificationToN8n.php, config/coldchain.php n8n, deploy/n8n/workflows/anteraja-frozen-outbound.json, deploy/n8n/state-table.sql, deploy/n8n/README.md, tests/Feature/WhatsAppNotificationFlowTest.php.
- Acceptance Criteria:
  - Given notifikasi template schedule_request untuk pembeli, When job berjalan, Then permintaan ke n8n memuat tracking_number, recipient_phone E.164, window, dan client_url.
  - Given notifikasi dengan template yang belum dipetakan n8n, When job berjalan, Then status baris tetap QUEUED dan bukan FAILED.
- Status: Live

---

### FR-WA-002 — Percakapan masuk dan pemetaan aksi

- Deskripsi: Sistem HARUS memastikan balasan pelanggan hanya diperlakukan sebagai aksi bila cocok dengan langkah yang sedang menunggu, dan HARUS memanggil API guest aplikasi untuk melaksanakannya.
- Aktor & Otorisasi: workflow n8n inbound; header X-Anteraja-Hook divalidasi credential Anteraja WAHA Hook pada node webhook.
- Trigger: webhook WAHA POST ke /webhook/anteraja-frozen/waha-inbound pada event message.
- Precondition: baris anteraja_chat_state ada untuk nomor tersebut, atau pesan memuat nomor resi agar state dapat disegarkan dari endpoint lookup.
- Input & Validasi: event dan payload WAHA, termasuk from, fromMe, dan body pesan. Pesan dari diri sendiri diabaikan.
- Proses / Business Rules: pemetaan perilaku berdasarkan kolom last_template:
  - schedule_request dan schedule_rescheduled menerima jawaban setuju seperti "siap", "ya", atau "oke" dan memanggil POST schedule/confirm; jawaban minta jadwal lain atau tanggal dan jam memanggil POST schedule/reschedule.
  - issue_options menerima angka 1 sampai 4 atau kata kunci seperti "kirim ulang", "retur", "lanjut", atau "diskusi" dan memanggil POST issue/choice.
  - on_the_way, courier_arrived, delivered, dan issue_decision memakai balasan apa pun tanpa aksi, hanya menjawab status singkat.
  - Bila ada paket tetapi tidak ada langkah tertunda, bot menjawab status paket apa adanya.
  - Bila tidak ada state, bot mengirim teks bantuan yang meminta nomor resi.
  Pesan tidak pernah memuat tautan atau token. Alamat balasan diselesaikan lebih dulu melalui node Resolusi LID yang memanggil endpoint WAHA untuk mendapatkan nomor telepon bila chat datang sebagai LID. Setelah aksi berhasil, last_template dimajukan menjadi confirmed, reschedule_requested, atau issue_choice_recorded supaya bot tidak menagih langkah yang sama; bila API menolak, pesan penolakan diteruskan apa adanya dan state tidak dimajukan. Klasifikasi maksud memakai LLM deepseek-v4-flash dengan fallback aturan sederhana.
- Output / Response: pesan balasan WhatsApp dan pemanggilan API guest.
- Error & Edge Cases: chat yang dijawab ke alamat LID sebagai nomor biasa akan gagal di WAHA dengan 500, sehingga resolusi LID wajib berhasil lebih dulu. State yang tertinggal setelah admin menyusun ulang jadwal disegarkan dengan menyebut nomor resi. Jawaban yang tidak cocok dengan langkah yang menunggu tidak dipaksa menjadi aksi.
- Side Effects: baris anteraja_chat_state diperbarui (last_template dan context); aksi aplikasi seperti konfirmasi jadwal, pengajuan jadwal ulang, atau pencatatan pilihan kendala berjalan lewat API guest beserta seluruh efek sampingnya.
- Postcondition: langkah yang menunggu jawaban pelanggan sudah terpenuhi atau state tetap menunggu bila jawaban tidak dikenali.
- Traceability: deploy/n8n/workflows/anteraja-frozen-inbound.json, deploy/n8n/README.md, deploy/n8n/state-table.sql, deploy/n8n/tests/inbound-scenarios.mjs.github/workflows/n8n-scenarios.yaml, routes/api.php grup guest, Actions/Client/ConfirmSchedule.php, Actions/Client/RequestReschedule.php, Actions/Client/RecordIssueChoice.php.
- Acceptance Criteria:
  - Given state last_template schedule_request dan pelanggan menjawab "siap", When webhook inbound diproses, Then API schedule/confirm dipanggil, paket menjadi CONFIRMED, dan last_template menjadi confirmed.
  - Given state issue_options dan pelanggan menjawab angka 9, When webhook inbound diproses, Then tidak ada aksi yang dijalankan dan bot menjawab dengan bantuan.
- Status: Live

---

### FR-WA-003 — Lookup resi untuk bot

- Deskripsi: Sistem HARUS menyediakan endpoint internal bagi n8n untuk menghubungkan nomor pengirim dengan paketnya, dan HANYA bila resi itu benar-benar milik pembeli dengan nomor tersebut.
- Aktor & Otorisasi: n8n; middleware force.json dan internal.token. Header X-Anteraja-Token harus sama dengan config coldchain.n8n.token; token kosong pada konfigurasi menghasilkan 503 INTERNAL_TOKEN_UNCONFIGURED, header salah menghasilkan 401 UNAUTHENTICATED.
- Trigger: POST /api/internal/whatsapp/lookup dari workflow inbound saat pelanggan menyebut nomor resi.
- Precondition: token internal sudah dikonfigurasi.
- Input & Validasi: phone wajib string maksimal 32; resi wajib string maksimal 32. Nomor dinormalkan E.164 dan resi diubah ke huruf besar serta dipangkas.
- Proses / Business Rules: pencarian memerlukan DUA syarat sekaligus: tracking_number sama dengan resi dan paket dimiliki pembeli dengan nomor telepon tersebut. Bila cocok, respons memuat nomor resi, client_token, tautan klien, label status dari lang client.status, jendela pengantaran, dan template langkah yang tertunda. Template dihitung aplikasi dari OrderPresenter::resolveActionRequired dan dipetakan sebagai berikut: confirm_schedule menjadi schedule_request, view_issue menjadi issue_options, request_replacement menjadi issue_decision, track menjadi in_delivery, dan selainnya none.
- Output / Response: HTTP 200 dengan found true beserta tracking_number, client_token, client_url, template, status_label, dan window; atau HTTP 200 dengan found false bila tidak ada yang cocok.
- Error & Edge Cases: resi yang ada tetapi milik nomor lain menghasilkan found false, bukan data paket itu, sehingga satu pelanggan tidak dapat membuka paket orang lain. Token salah menghasilkan 401; token belum disetel menghasilkan 503. Input kosong menghasilkan 422.
- Side Effects: tidak ada penulisan di database aplikasi.
- Postcondition: workflow menyimpan state dari hasil resolusi sehingga pesan berikutnya tidak perlu menyebut resi lagi.
- Traceability: routes/api.php, Api/Internal/WhatsappLookupController, Middleware/VerifyInternalToken.php, Services/Client/OrderPresenter.php, ClientLinkService.php, Support/PhoneFormatter.php, Support/DisplayTime.php, tests/Feature/WhatsappLookupTest.php (13 test termasuk token, format, kepemilikan, dan pemetaan status), deploy/n8n/README.md.
- Acceptance Criteria:
  - Given resi milik pembeli dengan nomor yang dikirim, When lookup dipanggil dengan token sah, Then found true dan template sesuai langkah yang menunggu.
  - Given resi yang ada tetapi nomor pengirim berbeda, When lookup dipanggil, Then found false tanpa membocorkan data paket.
- Status: Live

