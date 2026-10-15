# FR-TRACK-006 — Konfirmasi jadwal

| Properti | Nilai |
| --- | --- |
| ID | FR-TRACK-006 |
| Modul | TRACK — Pelacakan Publik dan Tautan Tamu |
| Judul | Konfirmasi jadwal |
| Status | Live |
| Berkas modul | docs/frd/09-pelacakan.md |
| Aturan bisnis | BR-002, BR-004, BR-014, BR-025 |
| Berkas kode | app/Actions/Client/ConfirmSchedule.php |

## 1. Ringkasan

Pembeli menyetujui jendela pengantaran. Ini satu-satunya jalan paket berpindah dari
AWAITING_CONFIRMATION ke CONFIRMED, dan karena itu juga satu-satunya jalan kurir
dapat ditugaskan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | pembeli pemilik paket atau guest pemegang tautan |
| Middleware | client.owned atau client.package |
| Aktor transisi | null, karena aksi pembeli |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | POST schedule/confirm pada tiga jalur: akun, guest, dan legacy |
| Pemanggil lain | workflow n8n inbound saat pelanggan menjawab setuju |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | status AWAITING_CONFIRMATION dengan konfirmasi PENDING |
| Postcondition | paket berstatus CONFIRMED dan konfirmasi terakhir berespons READY |

## 5. Input dan validasi

Tanpa body.

## 6. Proses langkah demi langkah

1. Action membuka transaksi dan mengunci baris paket.
2. Bila status sudah CONFIRMED dan konfirmasi terakhir berespons READY, kembalikan paket apa adanya (idempotensi).
3. Bila status bukan AWAITING_CONFIRMATION, lempar ScheduleNotActionable dengan pesan status tidak dapat dikonfirmasi.
4. Ambil konfirmasi PENDING dengan lockForUpdate. Bila tidak ada, lempar ScheduleNotActionable dengan pesan tidak ada jadwal menunggu konfirmasi.
5. Ubah konfirmasi menjadi READY dengan responded_at sekarang.
6. Transisi ke CONFIRMED dengan aktor null.
7. Balas redirect ke tautan tracking.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| success | boolean | true |
| message | string | "Jadwal pengantaran berhasil dikonfirmasi." |
| redirect_url | string | tautan tracking |
| data.status | string | CONFIRMED |
| data.tracking_number | string | resi |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Konfirmasi dua kali | sukses tanpa transisi tambahan |
| Status RESCHEDULE_REQUESTED | ScheduleNotActionable |
| Konfirmasi PENDING tidak ada | ScheduleNotActionable |
| Resi milik pembeli lain | 404 lewat middleware |
| Konfirmasi tepat saat admin menyusun ulang | salah satu permintaan gagal karena lockForUpdate |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Konfirmasi | schedule_confirmations response READY dan responded_at |
| Log status | status_logs ke CONFIRMED dengan changed_by null |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Controller | Api/Client/ScheduleConfirmationController, Client/ScheduleConfirmationController |
| Action | Actions/Client/ConfirmSchedule.php |
| Exception | app/Exceptions/ScheduleNotActionable.php |
| Support | PackageStatusTransitioner |
| Test | tests/Feature/ClientFlowTest.php test_schedule_confirm_transitions_status_with_null_actor, tests/Feature/PwaShellTest.php test_confirming_the_window_is_final |
| UI | resources/js/pwa/screens/client/Schedule.tsx |

## 11. Skenario uji

1. Given paket AWAITING_CONFIRMATION dengan konfirmasi PENDING, When konfirmasi dikirim, Then HTTP 200, paket CONFIRMED, dan baris log baru berubah_by null.
2. Given paket yang sudah CONFIRMED, When konfirmasi dikirim lagi, Then HTTP 200 tanpa baris log tambahan.
3. Given paket RESCHEDULE_REQUESTED, When konfirmasi dikirim, Then permintaan ditolak.

## 12. Yang perlu dikonfirmasi

1. Konfirmasi tidak mengirim notifikasi apa pun. Apakah hub perlu diberi tahu bahwa paket siap ditugaskan?
2. Setelah CONFIRMED, apakah pelanggan masih boleh minta jadwal ulang? Saat ini hanya bisa melalui kendala atau admin.

