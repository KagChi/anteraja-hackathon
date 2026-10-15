# FR-AUTH-003 — Profil sesi admin

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-003 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Profil sesi admin |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Berkas kode | app/Http/Controllers/Auth/SessionController.php |

## 1. Ringkasan

Konsol memerlukan identitas staf yang sedang login untuk menampilkan nama dan
peran, serta untuk memutuskan apakah halaman boleh dirender.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | staf dengan sesi |
| Pengunjung | menerima 401 dengan code UNAUTHENTICATED |
| Middleware | api |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | pemuatan awal konsol |
| Endpoint | GET /api/auth/me |
| Nama route | api.auth.me |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | sesi valid dan user bertipe Staff |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Ambil user dari request.
2. Bila user bukan instance Staff, balas 401 dengan message "Unauthenticated.".
3. Bila Staff, susun objek user berisi id, name, email, role (nilai enum), dan phone.
4. Balas HTTP 200.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| user.id | integer | id staf |
| user.name | string | nama |
| user.email | string | email |
| user.role | string | ADMIN atau COURIER |
| user.phone | string | nomor telepon |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Tanpa sesi | 401 | code UNAUTHENTICATED dari render AuthenticationException |
| Sesi milik buyer | 401 | karena bukan instance Staff |

## 9. Efek samping

Tidak ada.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php, name api.auth.me |
| Controller | SessionController@me |
| Render exception | bootstrap/app.php withExceptions |
| Test | tests/Feature/AdminAccessTest.php |

## 11. Skenario uji

1. Given admin login, When /api/auth/me dipanggil, Then HTTP 200 dengan role ADMIN.
2. Given tanpa sesi, When /api/auth/me dipanggil, Then HTTP 401 dengan code UNAUTHENTICATED.

## 12. Yang perlu dikonfirmasi

1. Endpoint ini tidak membedakan admin dan kurir; keduanya mendapat 200 selama bertipe Staff. Apakah konsol perlu memvalidasi peran dari sisi server juga?

