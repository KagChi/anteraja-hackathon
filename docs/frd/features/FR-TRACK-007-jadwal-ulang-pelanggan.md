# FR-TRACK-007 — Permintaan jadwal ulang pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-TRACK-007 |
| Modul | TRACK — Pelacakan Publik dan Tautan Tamu |
| Judul | Permintaan jadwal ulang pelanggan |
| Status | Live |
| Berkas modul | docs/frd/09-pelacakan.md |
| Aturan bisnis | BR-002, BR-004, BR-006, BR-014, BR-025 |
| Berkas kode | app/Actions/Client/RequestReschedule.php, app/Http/Requests/Client/RescheduleRequest.php |

## 1. Ringkasan

Pembeli memilih slot lain ketika jendela yang diusulkan tidak cocok. Paket
berpindah ke RESCHEDULE_REQUESTED dan seluruh admin aktif menerima permintaannya.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | pembeli pemilik paket atau guest pemegang tautan |
| Middleware | client.owned atau client.package |
| Aktor transisi | null |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | POST schedule/reschedule pada tiga jalur |
| Pemanggil lain | workflow n8n inbound saat pelanggan menyebut jadwal lain |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | status AWAITING_CONFIRMATION dengan konfirmasi PENDING |
| Postcondition | paket berstatus RESCHEDULE_REQUESTED dengan usulan waktu tersimpan |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| date | string | required, format Y-m-d, after_or_equal today |
| slot | string | required, harus salah satu nilai config coldchain.client.slots |

Validator lanjutan menolak tanggal lebih dari config reschedule_max_days (14 hari)
dan slot yang waktunya kurang dari config min_lead_hours (2 jam) dari sekarang,
dengan pesan yang menyebut angka batasnya.

## 6. Proses langkah demi langkah

1. Validasi dasar dan validator lanjutan dijalankan.
2. toData mengubah slot menjadi waktu mulai di zona Jakarta, lalu ke UTC.
3. Action membuka transaksi dan mengunci baris paket.
4. Bila status sudah RESCHEDULE_REQUESTED, kembalikan paket apa adanya (idempotensi).
5. Bila status bukan AWAITING_CONFIRMATION, lempar ScheduleNotActionable.
6. Ambil konfirmasi PENDING dengan lockForUpdate; bila tidak ada, lempar ScheduleNotActionable.
7. Ubah konfirmasi menjadi RESCHEDULE_REQUESTED dengan proposed_scheduled_at berisi usulan dan responded_at sekarang.
8. Transisi ke RESCHEDULE_REQUESTED dengan aktor null.
9. Antrekan notifikasi reschedule_requested ke setiap admin aktif berisi resi, usulan waktu, dan jadwal saat ini.
10. Balas redirect ke tautan tracking.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| success | boolean | true |
| message | string | "Permintaan perubahan jadwal berhasil dikirim." |
| redirect_url | string | tautan tracking |
| data.status | string | RESCHEDULE_REQUESTED |
| data.tracking_number | string | resi |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Tanggal lebih dari 14 hari | 422 pada errors.date dengan pesan batas |
| Slot kurang dari 2 jam dari sekarang | 422 pada errors.slot |
| Slot di luar daftar | 422 dari aturan in |
| Permintaan berulang | sukses tanpa perubahan |
| Admin menyusun ulang bersamaan | salah satu permintaan gagal karena penguncian |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Konfirmasi | schedule_confirmations response RESCHEDULE_REQUESTED dan proposed_scheduled_at |
| Log status | status_logs ke RESCHEDULE_REQUESTED dengan changed_by null |
| Notifikasi | reschedule_requested ke setiap admin aktif |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Controller | Api/Client/ScheduleRescheduleController, Client/ScheduleRescheduleController |
| Action | Actions/Client/RequestReschedule.php |
| FormRequest | Requests/Client/RescheduleRequest.php |
| DTO | DTOs/Client/RescheduleRequestData.php |
| Konfigurasi | config/coldchain.php client.reschedule_max_days, min_lead_hours, slots |
| Test | tests/Feature/ClientFlowTest.php test_schedule_reschedule_validates_and_transitions_status, tests/Feature/ClientNativeScreensTest.php test_requesting_another_window_posts_the_chosen_slot |
| UI | app/NativeComponents/Client/Schedule.php |

## 11. Skenario uji

1. Given paket AWAITING_CONFIRMATION, When pelanggan memilih slot besok, Then HTTP 200, paket RESCHEDULE_REQUESTED, dan setiap admin aktif menerima notifikasi.
2. Given slot yang hanya satu jam dari sekarang, When diajukan, Then HTTP 422 pada errors.slot.
3. Given tanggal 20 hari ke depan, When diajukan, Then HTTP 422 pada errors.date.

## 12. Yang perlu dikonfirmasi

1. Batas 14 hari dan 2 jam adalah konfigurasi manual. Apakah perlu berbeda untuk layanan INSTANT_FROZEN yang SLA-nya 2 sampai 4 jam?
2. Setelah admin menyusun ulang, pelanggan harus menjawab lagi. Apakah perlu batas berapa kali siklus ini boleh berulang?

