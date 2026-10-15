# FR-AUTH-010 — Klaim tautan menjadi akun

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-010 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Klaim tautan menjadi akun |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Aturan bisnis | BR-017, BR-018 |
| Berkas kode | app/Http/Controllers/Api/Client/AuthController.php |

## 1. Ringkasan

Tautan token yang dikirim lewat WhatsApp dapat dibuka tanpa login. Endpoint ini
mengubah pemegang tautan menjadi pemilik akun penuh dengan menetapkan password
pada baris pembeli yang sudah ada, lalu menerbitkan token aplikasi.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | guest pemegang tautan token yang sah |
| Middleware | api, force.json, client.headers, client.package, throttle 60,1 |
| Efek middleware | token diselesaikan menjadi objek Package dan disimpan sebagai atribut client_package |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Jalur baru | POST /api/guest/{token}/claim |
| Jalur legacy | POST /api/client/{token}/claim (tanpa middleware force.json) |
| UI | layar pelanggan yang menawarkan pembuatan password |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket dengan client_token itu ada, pembelinya ada, dan kolom password masih null |
| Postcondition | pembeli memiliki password dan phone_verified_at; klien memegang token ability client |

## 5. Input dan validasi

| Field | Tipe | Aturan | Pesan |
| --- | --- | --- | --- |
| password | string | required, min 8, confirmed | bawaan Laravel |
| password_confirmation | string | harus sama | konfirmasi password tidak sama (bawaan) |
| token | string pada path | pola hex 32 sampai 128 karakter | diperiksa middleware, bukan FormRequest |

## 6. Proses langkah demi langkah

1. Middleware client.package mencari paket dari client_token dan menyimpan objeknya pada atribut client_package.
2. Bila atribut itu kosong, controller mencari ulang paket dari parameter token; parameter route bisa berupa string token atau objek Package karena middleware juga menimpanya.
3. Bila paket tetap null, balas 404 dengan code LINK_INVALID.
4. Ambil pembeli dari paket. Bila null, balas 404 dengan code BUYER_NOT_FOUND.
5. Bila pembeli sudah punya password, balas 409 dengan code ACCOUNT_ALREADY_CLAIMED dan saran untuk login langsung.
6. Bila belum, set password dan phone_verified_at dengan waktu sekarang, lalu simpan.
7. Terbitkan token client-mobile ber-ability client berlaku 30 hari.
8. Balas HTTP 200 dengan message "Akun berhasil diklaim dan diaktifkan." beserta data token dan buyer.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Akun berhasil diklaim dan diaktifkan." |
| data.token | string | plain text token |
| data.token_type | string | Bearer |
| data.expires_at | string | ISO8601 |
| data.buyer | objek | BuyerResource |

## 8. Error dan edge case

| Kasus | HTTP | Code | Pesan |
| --- | --- | --- | --- |
| Token tidak cocok atau format salah | 404 atau 400 | LINK_INVALID | Tautan klaim tidak valid. (format salah ditolak middleware lebih dulu) |
| Paket ada tetapi pembeli tidak ditemukan | 404 | BUYER_NOT_FOUND | Data pembeli tidak ditemukan. |
| Pembeli sudah punya password | 409 | ACCOUNT_ALREADY_CLAIMED | Akun pembeli ini sudah memiliki password. Silakan login langsung. |
| Password kurang dari 8 karakter | 422 | - | pesan validasi |
| Dua puluh kegagalan token per menit per IP | 429 | - | pesan sisa waktu tunggu dari ClientAccessService |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| password dan phone_verified_at terisi | buyers |
| Token baru | personal_access_tokens |
| Penghitung kegagalan token dibersihkan | rate limiter per IP |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup guest dan grup legacy |
| Controller | Api/Client/AuthController@claim |
| FormRequest | Requests/Api/Client/ClaimAccountRequest.php |
| Middleware | app/Http/Middleware/ResolveClientPackage.php, Services/Client/ClientAccessService.php |
| Test | tests/Feature/ClientApiAuthTest.php test_guest_account_claim_sets_password_and_second_claim_returns_409 |

## 11. Skenario uji

1. Given paket dengan pembeli tanpa password, When claim dikirim dengan password sah, Then HTTP 200, kolom password terisi, phone_verified_at terisi, dan token diterbitkan.
2. Given akun yang sudah punya password, When claim dikirim lagi, Then HTTP 409 ACCOUNT_ALREADY_CLAIMED dan password lama tidak berubah.
3. Given token yang tidak ada, When claim dikirim, Then HTTP 404 LINK_INVALID.

## 12. Yang perlu dikonfirmasi

1. Klaim tidak memverifikasi bahwa pemegang tautan benar-benar pemilik nomor; siapa pun yang memegang tautan dapat menetapkan password. Apakah perlu verifikasi tambahan (misalnya OTP ke nomor pembeli)?
2. Bila tautan sudah kedaluwarsa karena paket berstatus terminal lebih dari 30 hari, klaim gagal dengan pesan tautan. Apakah klaim perlu tetap boleh untuk paket selesai?

