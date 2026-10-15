# FR-AUTH-005 — Logout kurir

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-005 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Logout kurir |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Berkas kode | app/Http/Controllers/Api/Courier/AuthController.php |

## 1. Ringkasan

Mencabut token yang sedang dipakai kurir sehingga perangkat yang hilang atau
dipinjam tidak dapat memakai sesi itu lagi.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir terautentikasi |
| Middleware | api, AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Token | bearer token realm courier |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tombol keluar pada layar profil kurir |
| Endpoint | POST /api/courier/auth/logout |
| Nama route | api.courier.auth.logout |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | permintaan membawa bearer token valid yang belum kedaluwarsa |
| Postcondition | token itu tidak dapat dipakai lagi; token lain milik kurir yang sama tetap berlaku |

## 5. Input dan validasi

Tanpa body.

## 6. Proses langkah demi langkah

1. AuthenticateCourier menyelesaikan token menjadi user; bila token tidak ada, sudah dicabut, atau kedaluwarsa, permintaan berhenti dengan 401.
2. Controller mengambil currentAccessToken milik user.
3. Bila token itu instance PersonalAccessToken, hapus barisnya.
4. Balas JSON berisi message "Berhasil logout." dan code LOGGED_OUT.
5. Layar PWA menghapus token lokal dari penyimpanan peramban.

Detail penting pada AuthenticateCourier: token diresolusi ulang setiap permintaan
lewat PersonalAccessToken::findToken, bukan melalui guard sanctum yang menyimpan
user di memori proses. Karena itu token yang dicabut langsung berhenti bekerja
tanpa menunggu proses dijalankan ulang.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Berhasil logout." |
| code | string | LOGGED_OUT |

## 8. Error dan edge case

| Kasus | HTTP | Code | Keterangan |
| --- | --- | --- | --- |
| Tanpa bearer token | 401 | UNAUTHENTICATED | dari AuthenticateCourier |
| Token sudah dicabut | 401 | UNAUTHENTICATED | findToken mengembalikan null |
| Token kedaluwarsa | 401 | UNAUTHENTICATED | expires_at sudah lewat |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Baris token dihapus | personal_access_tokens |
| Token lokal dibersihkan | penyimpanan perangkat |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier |
| Controller | Api/Courier/AuthController@logout |
| Middleware | app/Http/Middleware/AuthenticateCourier.php |
| Test | tests/Feature/CourierApiAuthTest.php test_logout_revokes_the_token |
| UI | resources/js/pwa/screens/courier/Profile.tsx |

## 11. Skenario uji

1. Given kurir login, When logout dipanggil dengan token itu, Then HTTP 200 dan permintaan GET /api/courier/tasks berikutnya mendapat 401.
2. Given token yang sama dipakai lagi, When logout dipanggil kedua kali, Then HTTP 401 UNAUTHENTICATED.

## 12. Yang perlu dikonfirmasi

1. Apakah admin hub perlu dapat mencabut token kurir dari konsol, misalnya saat perangkat hilang?

