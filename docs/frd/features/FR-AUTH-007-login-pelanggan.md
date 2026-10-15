# FR-AUTH-007 — Login pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-007 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Login pelanggan |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Aturan bisnis | BR-021 |
| Berkas kode | app/Http/Controllers/Api/Client/AuthController.php |

## 1. Ringkasan

Pembeli masuk memakai nomor HP dan password. Nomor dinormalkan terlebih dulu
sehingga format 08xx, 62xx, dan +62xx menunjuk akun yang sama.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | publik |
| Middleware | api, force.json, client.headers, throttle client-auth |
| Rate limit | 5 per menit per kombinasi phone dan IP |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | layar Login aplikasi pelanggan, resources/js/pwa/screens/client/Login.tsx |
| Endpoint | POST /api/client/auth/login |
| Nama route | api.client.auth.login |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | baris buyers dengan nomor tersebut ada dan kolom password terisi |
| Postcondition | satu token ability client baru berlaku 30 hari |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| phone | string | required, string |
| password | string | required, string |

## 6. Proses langkah demi langkah

1. Ambil dan validasi phone dan password.
2. Normalisasi nomor memakai PhoneFormatter::toE164Id.
3. Cari pembeli dengan nomor E.164, dan bila tidak ketemu cari memakai bentuk asli yang dikirim.
4. Bila pembeli null, password kosong, atau Hash::check gagal, balas 401 dengan message "Nomor atau password salah." dan code INVALID_CREDENTIALS.
5. Bila is_active false, balas 403 dengan code ACCOUNT_INACTIVE.
6. Buat token client-mobile ber-ability client dan expires_at 30 hari.
7. Balas data berisi token, token_type, expires_at, dan buyer.

Kesengajaan desain: pesan kesalahan sama untuk nomor yang tidak terdaftar dan
password yang salah, sehingga endpoint tidak dapat dipakai memastikan apakah
sebuah nomor punya akun.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data.token | string | plain text token |
| data.token_type | string | Bearer |
| data.expires_at | string | ISO8601, 30 hari |
| data.buyer | objek | hasil BuyerResource |
| (tanpa message) | - | endpoint login tidak mengirim message, berbeda dari register |

## 8. Error dan edge case

| Kasus | HTTP | Code | Pesan |
| --- | --- | --- | --- |
| Nomor tidak terdaftar atau password salah | 401 | INVALID_CREDENTIALS | Nomor atau password salah. |
| Akun dinonaktifkan | 403 | ACCOUNT_INACTIVE | Akun pembeli dinonaktifkan. |
| Nomor dengan spasi atau tanda hubung yang tidak dapat dinormalkan | 401 | INVALID_CREDENTIALS | sama seperti di atas |
| Lima percobaan per menit terlampaui | 429 | TOO_MANY_REQUESTS | Terlalu banyak percobaan login. Silakan coba lagi beberapa saat. |

## 9. Efek samping

Baris token baru pada personal_access_tokens.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client/auth |
| Controller | Api/Client/AuthController@login |
| FormRequest | Requests/Api/Client/LoginRequest.php |
| Normalisasi | Support/PhoneFormatter.php, cast Attribute pada app/Models/Buyer.php |
| Test | tests/Feature/ClientApiAuthTest.php test_buyer_can_login_with_phone_and_password, test_login_returns_generic_error_for_invalid_credentials, test_inactive_buyer_cannot_login, test_client_auth_rate_limiting_triggers_429 |
| UI | resources/js/pwa/screens/client/Login.tsx |

## 11. Skenario uji

1. Given pembeli aktif dengan nomor +62812xxxx, When login dikirim dengan nomor 0812xxxx dan password benar, Then HTTP 200 dan token diterbitkan.
2. Given password salah, When login dikirim, Then HTTP 401 dengan pesan yang identik dengan kasus nomor tidak terdaftar.
3. Given pembeli nonaktif, When login dikirim, Then HTTP 403 ACCOUNT_INACTIVE.
4. Given lima percobaan gagal dalam satu menit, When percobaan keenam dikirim, Then HTTP 429.

## 12. Yang perlu dikonfirmasi

1. Apakah pembeli yang lupa password punya jalur pemulihan? Saat ini satu-satunya jalur adalah klaim tautan (FR-AUTH-010) yang hanya berlaku bila kolom password masih kosong.

