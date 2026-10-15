# FR-TRACK-005 — Data jadwal pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-TRACK-005 |
| Modul | TRACK — Pelacakan Publik dan Tautan Tamu |
| Judul | Data jadwal pelanggan |
| Status | Live |
| Berkas modul | docs/frd/09-pelacakan.md |
| Berkas kode | app/Services/Client/ScheduleViewService.php |

## 1. Ringkasan

Layar permintaan konfirmasi jadwal: apa yang dikirim, berapa beratnya, jam berapa
kurir datang, dan slot apa saja yang bisa dipilih bila pelanggan ingin mengubah.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | pembeli pemilik paket atau guest pemegang tautan |
| Middleware | client.owned atau client.package |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | GET /api/client/orders/{tracking_number}/schedule, GET /api/guest/{token}/schedule, GET /api/client/{token}/schedule |
| UI | app/NativeComponents/Client/Schedule.php |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket dapat diakses pemanggil |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Susun label status dari lang.
2. Susun teks jadwal: tanggal berbahasa Indonesia dan rentang jam dengan pemisah sampai, memakai zona Jakarta; bila jendela berakhir tidak ada, ditambah dua jam.
3. Untuk setiap item, buat baris kargo berisi kategori beku, nama produk, dan label jumlah.
4. Gabungkan catatan penanganan dari semua produk dengan pemisah titik koma; bila kosong, pakai catatan bawaan.
5. Tentukan lencana kurir hanya bila kurir sudah ditugaskan.
6. Tandai prioritas dingin bila layanan INSTANT_FROZEN.
7. Tentukan can_action: true hanya bila status AWAITING_CONFIRMATION dan ada konfirmasi PENDING.
8. Sertakan daftar slot dari konfigurasi.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data.trackingNumber | string | resi |
| data.status, statusLabel | string | status dan labelnya |
| data.buyerName, buyerAddress | string | penerima |
| data.cargoItems | array | kategori, nama, label jumlah |
| data.totalWeightKg | number | berat total |
| data.coldGuaranteedBadge | string | lencana jaminan suhu |
| data.handlingNote | string | catatan penanganan |
| data.scheduledDelivery | string | jendela yang diusulkan |
| data.hasColdPriority | boolean | penanda layanan instan |
| data.courierName, courierBadge | string atau null | kurir |
| data.canAction | boolean | apakah tombol konfirmasi ditawarkan |
| data.availableSlots | array | lima rentang jam |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Paket tanpa jadwal | scheduledDelivery berisi teks "Belum ditentukan" |
| Paket sudah CONFIRMED | canAction false |
| Produk tanpa catatan penanganan | memakai catatan bawaan |
| Paket tanpa item | cargoItems kosong, berat tetap dari kolom paket |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Service | Services/Client/ScheduleViewService.php |
| DTO | DTOs/Client/ScheduleView.php, CargoLine.php |
| Konfigurasi | config/coldchain.php client.slots |
| Test | tests/Feature/ClientFlowTest.php test_schedule_show_returns_cargo_items_and_weight, tests/Feature/ClientNativeScreensTest.php test_the_schedule_screen_shows_the_window_and_the_frozen_cargo |
| UI | app/NativeComponents/Client/Schedule.php, resources/js/pages/client/Schedule.tsx |

## 11. Skenario uji

1. Given paket AWAITING_CONFIRMATION dengan konfirmasi PENDING, When layar diminta, Then canAction true dan availableSlots memuat lima rentang.
2. Given paket yang sudah CONFIRMED, When layar diminta, Then canAction false.
3. Given layanan INSTANT_FROZEN, When layar diminta, Then hasColdPriority true.

## 12. Yang perlu dikonfirmasi

1. Slot bersifat tetap lima rentang. Apakah perlu menyesuaikan per hari atau per hub?
2. Berat ditampilkan dari kolom paket, bukan jumlah item. Apakah itu konsisten dengan yang dihitung pada checkout?

