# FR-PKG-005 — Pengajuan/penjadwalan ulang oleh admin atau kurir

| Properti | Nilai |
| --- | --- |
| ID | FR-PKG-005 |
| Modul | PKG — Paket dan FSM Status |
| Judul | Pengajuan/penjadwalan ulang oleh admin atau kurir |
| Status | Live |
| Berkas modul | docs/frd/02-paket-fsm.md |
| Aturan bisnis | BR-002, BR-003, BR-004, BR-005, BR-014 |
| Berkas kode | app/Actions/Packages/RequestScheduleConfirmation.php |

## 1. Ringkasan

Admin hub atau kurir menetapkan jendela pengantaran pada paket yang sudah
ditugaskan ke kurir. Bila jendela baru diberikan, jadwal paket diperbarui;
sistem membuat konfirmasi PENDING baru dan mengembalikan paket ke
AWAITING_CONFIRMATION agar penerima menjawab sekali lagi. Aksi ini adalah aksi
yang sama dengan permintaan konfirmasi jadwal; hanya jendelanya yang opsional.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub, atau kurir pemilik paket |
| Middleware | api, staff.role:ADMIN (admin) atau AuthenticateCourier + abilities:courier + staff.role:COURIER (kurir) |
| Aktor transisi | staf yang mengajukan, tercatat pada status_logs.changed_by |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | aksi "Ajukan jadwal" pada modal detail operator, atau tombol jadwal pada layar pengantaran kurir |
| Endpoint | POST /api/admin/shipments/{tracking_number}/request-confirmation, POST /api/courier/deliveries/{tracking_number}/request-confirmation |
| Nama route | api.admin.shipments.request-confirmation, api.courier.deliveries.request-confirmation |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus COURIER_ASSIGNED dan belum ada konfirmasi PENDING |
| Postcondition | paket berstatus AWAITING_CONFIRMATION dengan konfirmasi PENDING baru dan jadwal yang diperbarui (bila jendela diubah) |

## 5. Input dan validasi

| Field | Tipe | Aturan | Catatan |
| --- | --- | --- | --- |
| scheduled_date | date | opsional, format Y-m-d, after_or_equal today | wajib bersama scheduled_time_window |
| scheduled_time_window | string | opsional, string | dipecah pada tanda hubung; bagian kosong jatuh ke 09:00 dan 11:00 |

toWindow memakai str_replace untuk membuang spasi, memecah pada tanda hubung, lalu
memparsing dua jam itu di zona Asia/Jakarta dan mengubahnya ke UTC. Bila kedua
field kosong, aksi berjalan tanpa mengubah jadwal (permintaan konfirmasi biasa).

## 6. Proses langkah demi langkah

1. Controller mencari paket berdasarkan resi, dengan firstOrFail.
2. Controller memanggil toWindow untuk mendapatkan pasangan waktu mulai dan selesai (atau null).
3. Action membuka DB::transaction.
4. Baris paket dikunci ulang dengan lockForUpdate (BR-004).
5. Kurir hanya boleh mengajukan untuk paket yang ditugaskan padanya.
6. Bila status bukan COURIER_ASSIGNED, lempar ScheduleNotActionable (422).
7. Bila sudah ada konfirmasi PENDING, kembalikan paket apa adanya (idempotensi).
8. Bila jendela baru diberikan, kolom scheduled_at dan scheduled_end_at diperbarui.
9. Buat ScheduleConfirmation baru berisi response PENDING, proposed_scheduled_at, dan requested_at sekarang.
10. Transisi ke AWAITING_CONFIRMATION dengan aktor pengaju (BR-002 dan BR-003).
11. Antrekan notifikasi untuk pembeli: template schedule_rescheduled bila jendela diubah, schedule_request bila tidak.
12. Refresh dan kembalikan paket.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Permintaan konfirmasi jadwal resi {resi} dikirim ke penerima." |
| package | objek | paket hasil load buyer, courier, dan statusRef |
| package.status | string | AWAITING_CONFIRMATION |
| package.scheduled_at | string | UTC |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Status bukan COURIER_ASSIGNED | 422 | ScheduleNotActionable |
| Kurir bukan pemilik paket | 422 | ScheduleNotActionable |
| Konfirmasi PENDING sudah ada | 200 | idempoten, tidak ada konfirmasi baru |
| Tanggal lampau | 422 | aturan after_or_equal |
| Format tanggal salah | 422 | date_format |
| Hanya salah satu field jendela diisi | 422 | required_with |
| Resi tidak ada | 404 | firstOrFail |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Jadwal paket berubah (bila jendela diubah) | packages |
| Konfirmasi PENDING baru | schedule_confirmations |
| Log status | status_logs ke AWAITING_CONFIRMATION |
| Notifikasi | notifications template schedule_rescheduled atau schedule_request |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin shipments dan courier deliveries |
| Controller | Admin/ShipmentScheduleConfirmationController, Courier/RequestConfirmationController |
| Action | Actions/Packages/RequestScheduleConfirmation |
| FormRequest | Requests/ScheduleWindowRequest.php |
| Support | PackageStatusTransitioner, NotificationQueue, DisplayTime |
| Test | tests/Feature/ClientFlowTest.php test_admin_proposes_a_new_window_and_the_buyer_must_confirm |
| UI | resources/js/components/ShipmentDetailModal.tsx, resources/js/pwa/screens/courier/Delivery.tsx |

## 11. Skenario uji

1. Given paket COURIER_ASSIGNED tanpa konfirmasi PENDING, When admin mengirim jendela baru yang sah, Then HTTP 200, paket menjadi AWAITING_CONFIRMATION, jadwal berubah, dan satu konfirmasi PENDING baru ada.
2. Given paket berstatus CONFIRMED, When pengajuan dipanggil, Then HTTP 422 dan tidak ada konfirmasi baru.
3. Given tanggal kemarin, When pengajuan dikirim, Then HTTP 422 dan jadwal tidak berubah.

## 12. Yang perlu dikonfirmasi

1. Validasi after_or_equal today memakai zona waktu aplikasi, sedangkan input jadwal diparsing di zona Jakarta; keduanya perlu diselaraskan.
2. Apakah admin perlu dapat menyusun jadwal lebih dari 14 hari ke depan, mengingat pelanggan dibatasi 14 hari?
