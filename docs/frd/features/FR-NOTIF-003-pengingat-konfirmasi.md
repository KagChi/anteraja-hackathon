# FR-NOTIF-003 — Pengingat konfirmasi tak dijawab

| Properti | Nilai |
| --- | --- |
| ID | FR-NOTIF-003 |
| Modul | NOTIF — Notification Outbox dan Penjadwalan |
| Judul | Pengingat konfirmasi tak dijawab |
| Status | Live |
| Berkas modul | docs/frd/07-notifikasi-outbox.md |
| Berkas kode | app/Console/Commands/RemindUnansweredConfirmations.php |

## 1. Ringkasan

Paket yang menunggu jawaban pembeli adalah paket yang paling mudah macet. Perintah
terjadwal ini menaikkan pengingat ke admin untuk konfirmasi yang sudah lewat
ambang waktu.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | scheduler Laravel |
| Penerima | seluruh admin aktif |
| Perintah | confirmations:remind-unanswered |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Jadwal | setiap 15 menit, didefinisikan di routes/console.php |
| Perintah manual | php artisan confirmations:remind-unanswered |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | ada konfirmasi PENDING yang requested_at-nya lebih lama dari ambang |
| Postcondition | baris notifications confirmation_overdue dibuat untuk setiap admin aktif, maksimal satu kali per konfirmasi |

## 5. Input dan validasi

| Sumber | Nilai |
| --- | --- |
| Konfigurasi | coldchain.client.unanswered_after_minutes, bawaan 120 menit |
| Argumen | tidak ada |

## 6. Proses langkah demi langkah

1. Hitung potongan waktu: sekarang dikurangi ambang menit.
2. Ambil konfirmasi berstatus PENDING dengan requested_at kurang dari atau sama dengan potongan waktu, dan paketnya masih berstatus AWAITING_CONFIRMATION.
3. Bila tidak ada, cetak pesan tidak ada konfirmasi telat dan keluar dengan kode 0.
4. Ambil seluruh admin aktif.
5. Untuk setiap konfirmasi, periksa apakah sudah ada baris notifications bertipe ADMIN bertemplate confirmation_overdue dengan payload confirmation_id yang sama. Bila ada, lewati.
6. Hitung usia konfirmasi dalam menit.
7. Untuk setiap admin aktif, antrekan notifikasi confirmation_overdue berisi tracking_number, confirmation_id, dan age_minutes.
8. Cetak jumlah paket yang diproses dan keluar dengan kode 0.

## 7. Output

| Keluaran | Keterangan |
| --- | --- |
| stdout | jumlah paket yang diproses atau pesan tidak ada konfirmasi telat |
| exit code | 0 |
| baris notifications | template confirmation_overdue per admin aktif |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Tidak ada admin aktif | tidak ada notifikasi dibuat |
| Konfirmasi telat tetapi paket sudah pindah status | dilewati karena syarat status paket |
| Perintah dijalankan berulang | tidak ada pengingat ganda karena pemeriksaan dedup per confirmation_id |
| Konfirmasi tanpa requested_at | usia memakai nilai ambang sebagai cadangan |

## 9. Efek samping

Baris notifications QUEUED per admin aktif untuk setiap konfirmasi telat.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Jadwal | routes/console.php Schedule::command everyFifteenMinutes |
| Command | app/Console/Commands/RemindUnansweredConfirmations.php |
| Konfigurasi | config/coldchain.php client.unanswered_after_minutes |
| Support | Support/NotificationQueue |
| Model | ScheduleConfirmation, Notification, Staff |
| Test | tests/Feature/ClientFlowTest.php test_remind_unanswered_confirmations_command |

## 11. Skenario uji

1. Given konfirmasi PENDING berumur tiga jam dan dua admin aktif, When perintah dijalankan, Then dua baris notifications confirmation_overdue dibuat.
2. Given perintah dijalankan dua kali, When perintah kedua berjalan, Then tidak ada baris tambahan.
3. Given tidak ada konfirmasi telat, When perintah dijalankan, Then keluaran menyebut tidak ada konfirmasi dan kode keluar 0.

## 12. Yang perlu dikonfirmasi

1. Pengingat hanya ditujukan ke admin, tidak ke pembeli. Apakah pembeli perlu diingatkan ulang lewat WhatsApp?
2. Ambang 120 menit bersifat konfigurasi manual. Apakah perlu berbeda per segmen layanan, mengingat INSTANT_FROZEN memiliki SLA jauh lebih pendek?

