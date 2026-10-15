# FR-AUTH-004 — Login kurir berbasis token

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-004 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Login kurir berbasis token |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Aturan bisnis | BR-020 |
| Berkas kode | app/Http/Controllers/Api/Courier/AuthController.php |

## 1. Ringkasan

Kurir memakai aplikasi Android dan memerlukan bearer token alih-alih cookie sesi.
Endpoint ini menukar email dan password staf dengan token Sanctum ber-ability
courier yang berlaku 30 hari.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | staf role COURIER yang aktif |
| Middleware | api, force.json, throttle courier-auth |
| Rate limit | 5 percobaan per menit per kombinasi email dan IP |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | layar Login aplikasi kurir, app/NativeComponents/Courier/Login.php |
| Endpoint | POST /api/courier/auth/login |
| Nama route | api.courier.auth.login |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | baris staff dengan email tersebut ada dan kolom password terisi |
| Postcondition | satu baris personal_access_tokens baru ber-ability courier dengan masa berlaku 30 hari |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| email | string | required, string, email |
| password | string | required, string |

## 6. Proses langkah demi langkah

1. Validasi email dan password.
2. Cari staf berdasarkan email.
3. Bila staf tidak ada, password kosong, atau Hash::check gagal, balas 401 dengan code INVALID_CREDENTIALS.
4. Bila role bukan COURIER, balas 403 dengan code FORBIDDEN.
5. Bila is_active false, balas 403 dengan code ACCOUNT_INACTIVE.
6. Buat token bernama courier-mobile dengan ability courier dan expires_at 30 hari.
7. Balas data berisi token, token_type Bearer, expires_at ISO8601, dan objek courier.

Urutan langkah 3 sampai 5 penting: akun nonaktif ditolak setelah kredensialnya
benar, sehingga pesan kesalahan membedakan dua kondisi itu.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data.token | string | plain text token Sanctum |
| data.token_type | string | Bearer |
| data.expires_at | string | ISO8601, 30 hari ke depan |
| data.courier.id | integer | id staf |
| data.courier.name | string | nama kurir |
| data.courier.email | string | email |
| data.courier.phone | string | nomor telepon |
| data.courier.status | string | Aktif atau Nonaktif |

## 8. Error dan edge case

| Kasus | HTTP | Code | Pesan |
| --- | --- | --- | --- |
| Email atau password salah | 401 | INVALID_CREDENTIALS | Email atau password salah. |
| Akun berperan ADMIN | 403 | FORBIDDEN | Akun ini tidak memiliki akses aplikasi kurir. |
| Akun nonaktif | 403 | ACCOUNT_INACTIVE | Akun kurir dinonaktifkan. |
| Lima percobaan per menit terlampaui | 429 | TOO_MANY_REQUESTS | Terlalu banyak percobaan login. Silakan coba lagi beberapa saat. |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Baris token baru | personal_access_tokens |
| Token disimpan di perangkat | SecureStorage atau cache, lihat FR-MOB-002 |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier/auth |
| Controller | Api/Courier/AuthController@login |
| Rate limiter | AppServiceProvider RateLimiter for courier-auth |
| Test | tests/Feature/CourierApiAuthTest.php (5 test) |
| UI | app/NativeComponents/Courier/Login.php |

## 11. Skenario uji

1. Given kurir aktif, When login dengan kredensial benar, Then HTTP 200, token ber-ability courier, dan expires_at sekitar 30 hari ke depan.
2. Given akun berperan ADMIN, When login kurir dipanggil, Then HTTP 403 FORBIDDEN dan tidak ada baris token dibuat.
3. Given lima percobaan gagal dari IP dan email yang sama dalam satu menit, When percobaan keenam dikirim, Then HTTP 429 TOO_MANY_REQUESTS.

## 12. Yang perlu dikonfirmasi

1. Tidak ada mekanisme penyegaran token; setelah 30 hari kurir harus login ulang. Apakah itu cukup untuk operasi harian?
2. Setiap login membuat token baru tanpa mencabut yang lama, sehingga satu akun dapat aktif di beberapa perangkat. Apakah itu memang diinginkan?

