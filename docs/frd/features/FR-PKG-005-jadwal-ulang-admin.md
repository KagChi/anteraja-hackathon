# FR-PKG-005 — Penjadwalan ulang oleh admin

| Properti | Nilai |
| --- | --- |
| ID | FR-PKG-005 |
| Modul | PKG — Paket dan FSM Status |
| Judul | Penjadwalan ulang oleh admin |
| Status | Live |
| Berkas modul | docs/frd/02-paket-fsm.md |
| Aturan bisnis | BR-002, BR-003, BR-004, BR-005, BR-014 |
| Berkas kode | app/Actions/Admin/RescheduleShipment.php |

## 1. Ringkasan

Menutup permintaan jadwal ulang dari pembeli. Admin menetapkan jendela baru, sistem
membuat konfirmasi PENDING baru, dan mengembalikan paket ke AWAITING_CONFIRMATION
agar pembeli menjawab sekali lagi.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |
| Aktor transisi | staf admin, tercatat pada status_logs.changed_by |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | aksi reschedule pada modal detail atau baris antrean dengan availableAction reschedule |
| Endpoint | POST /api/admin/shipments/{tracking_number}/reschedule |
| Nama route | api.admin.shipments.reschedule |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus RESCHEDULE_REQUESTED |
| Postcondition | paket berstatus AWAITING_CONFIRMATION dengan konfirmasi PENDING baru dan jadwal yang diperbarui |

## 5. Input dan validasi

| Field | Tipe | Aturan | Catatan |
| --- | --- | --- | --- |
| scheduled_date | date | required, format Y-m-d, after_or_equal today | current timezone aplikasi, bukan zona Jakarta eksplisit |
| scheduled_time_window | string | required, string | dipecah pada tanda hubung; bagian kosong jatuh ke 09:00 dan 11:00 |

toWindow memakai str_replace untuk membuang spasi, memecah pada tanda hubung, lalu
memparsing dua jam itu di zona Asia/Jakarta dan mengubahnya ke UTC.

## 6. Proses langkah demi langkah

1. Controller mencari paket berdasarkan resi, dengan firstOrFail.
2. Controller memanggil toWindow untuk mendapatkan pasangan waktu mulai dan selesai sebagai CarbonImmutable.
3. Action membuka DB::transaction.
4. Baris paket dikunci ulang dengan lockForUpdate (BR-004).
5. Bila status bukan RESCHEDULE_REQUESTED, lempar InvalidArgumentException dengan pesan bahwa paket tidak menunggu penjadwalan ulang.
6. Kolom scheduled_at dan scheduled_end_at diperbarui, lalu disimpan.
7. Buat ScheduleConfirmation baru berisi response PENDING dan requested_at sekarang.
8. Transisi ke AWAITING_CONFIRMATION dengan aktor admin (BR-002 dan BR-003).
9. Antrekan notifikasi template schedule_rescheduled untuk pembeli berisi tracking_number, client_url hasil ClientLinkService::entry, dan window hasil DisplayTime::windowLabel.
10. Refresh dan kembalikan paket.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Shipment {resi} berhasil dijadwalkan ulang dan menunggu konfirmasi pembeli." |
| package | objek | paket hasil load buyer dan statusRef |
| package.status | string | AWAITING_CONFIRMATION |
| package.scheduled_at | string | UTC |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Tanggal lampau | 422 | aturan after_or_equal |
| Format tanggal salah | 422 | date_format |
| scheduled_time_window kosong | 422 | required |
| Status bukan RESCHEDULE_REQUESTED | 500 | InvalidArgumentException tidak dipetakan ke 422 oleh controller; ini temuan, bukan perilaku yang diinginkan |
| Resi tidak ada | 404 | firstOrFail |
| Format jendela tanpa tanda hubung | 200 | start dan end sama-sama jatuh ke bawaan 09:00 dan 11:00 |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Jadwal paket berubah | packages |
| Konfirmasi PENDING baru | schedule_confirmations |
| Log status | status_logs RESCHEDULE_REQUESTED ke AWAITING_CONFIRMATION |
| Notifikasi | notifications template schedule_rescheduled |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin shipments |
| Controller | Admin/ShipmentRescheduleController |
| Action | Actions/Admin/RescheduleShipment |
| FormRequest | Requests/Admin/RescheduleShipmentRequest.php |
| Support | PackageStatusTransitioner, NotificationQueue, DisplayTime, ClientLinkService |
| Test | tests/Feature/ClientFlowTest.php test_admin_reschedule_shipment_action |
| UI | resources/js/pages/admin/Shipment.tsx |

## 11. Skenario uji

1. Given paket RESCHEDULE_REQUESTED, When admin mengirim jadwal baru yang sah, Then HTTP 200, paket menjadi AWAITING_CONFIRMATION, dan satu konfirmasi PENDING baru ada.
2. Given paket berstatus CONFIRMED, When reschedule dipanggil, Then permintaan gagal dan tidak ada konfirmasi baru.
3. Given tanggal kemarin, When reschedule dikirim, Then HTTP 422 dan jadwal tidak berubah.

## 12. Yang perlu dikonfirmasi

1. Pesan kegagalan untuk status yang salah saat ini berupa error server 500. Perlu dipetakan ke 422 agar frontend dapat menampilkan pesan yang benar.
2. Validasi after_or_equal today memakai zona waktu aplikasi, sedangkan input jadwal diparsing di zona Jakarta; keduanya perlu diselaraskan.
3. Apakah admin perlu dapat menyusun jadwal lebih dari 14 hari ke depan, mengingat pelanggan dibatasi 14 hari?

