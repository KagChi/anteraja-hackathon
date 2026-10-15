# FR-ISS-010 — Permintaan pengiriman ulang dari aplikasi

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-010 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Permintaan pengiriman ulang dari aplikasi |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Aturan bisnis | BR-008, BR-025 |
| Berkas kode | app/Http/Controllers/Api/Client/ReplacementController.php |

## 1. Ringkasan

Pelanggan menekan tombol pengiriman ulang pada layar kendala. Bila syarat retur
terpenuhi, sistem membuat resi baru dan mengarahkan pelanggan ke konfirmasi jadwal
paket pengganti.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | pembeli pemilik paket, atau guest pemegang tautan pada jalur legacy |
| Middleware | client.owned untuk jalur akun, client.package untuk jalur tautan |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | POST /api/client/orders/{tracking_number}/issue/resend dan POST /api/client/{token}/issue/resend |
| Nama route | api.client.orders.issue.resend, api.client.issue.resend |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket memenuhi syarat retur, atau sudah punya paket pengganti |
| Postcondition | paket pengganti menunggu konfirmasi jadwal, atau permintaan dicatat untuk persetujuan manual |

## 5. Input dan validasi

Tanpa body.

## 6. Proses langkah demi langkah

1. Controller mengambil paket dari atribut client_package yang dipasang middleware.
2. Panggil Actions/Client/RequestReplacement::execute.
3. Bila hasilnya paket yang berbeda dari paket asal, balas HTTP 201 dengan message pengiriman ulang berhasil, redirect_url ke jadwal paket pengganti, replacement_tracking_number, dan replacement_token.
4. Bila hasilnya paket asal yang sama, artinya permintaan hanya dicatat untuk persetujuan manual; balas HTTP 200 dengan pesan bahwa permintaan diajukan ke tim operasional.

Perbedaan kode respons 201 dan 200 itu adalah cara controller membedakan dua
kemungkinan hasil tanpa menambah kolom baru.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| success | boolean | true |
| message | string | pesan sesuai jalur |
| redirect_url | string | hanya pada 201 |
| replacement_tracking_number | string | hanya pada 201 |
| replacement_token | string | hanya pada 201 |
| data.tracking_number | string | hanya pada 200 |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Paket belum layak | ReplacementNotAllowed dari Action |
| Permintaan berulang | paket pengganti yang sudah ada dikembalikan, tanpa resi kedua |
| Auto-approve dimatikan | HTTP 200 dengan permintaan dicatat |
| Jalur legacy tanpa force.json | bentuk error dapat berbeda dari jalur akun |

## 9. Efek samping

Sama dengan FR-ISS-007: paket pengganti, item, konfirmasi PENDING, log status, dan notifikasi.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client orders dan grup legacy |
| Controller | Api/Client/ReplacementController, Client/ReplacementController |
| Action | Actions/Client/RequestReplacement |
| Test | tests/Feature/ClientFlowTest.php test_issue_replacement_creates_child_package_and_handles_double_tap, tests/Feature/WhatsAppNotificationFlowTest.php test_resend_option_creates_replacement_when_package_is_returned |
| UI | resources/js/pwa/screens/client/Issue.tsx |

## 11. Skenario uji

1. Given paket RETURNED yang memenuhi syarat, When permintaan dikirim, Then HTTP 201 dengan replacement_tracking_number dan redirect_url berisi token paket baru.
2. Given paket yang masih IN_DELIVERY, When permintaan dikirim, Then permintaan ditolak dan tidak ada paket baru.
3. Given permintaan yang sama dikirim dua kali, When permintaan kedua diproses, Then respons mengembalikan paket pengganti yang sama tanpa resi baru.

## 12. Yang perlu dikonfirmasi

1. Dua jalur (akun dan legacy) memiliki perilaku berbeda pada bentuk respons; apakah jalur legacy akan dihapus?
2. Apakah perlu batas berapa kali pengiriman ulang boleh diajukan untuk satu paket asal? Saat ini index unik membatasi satu paket pengganti, tetapi rantai penggantian dapat terus berlanjut.

