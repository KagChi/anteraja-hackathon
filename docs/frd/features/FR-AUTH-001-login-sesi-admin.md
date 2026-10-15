# FR-AUTH-001 — Login sesi admin

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-001 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Login sesi admin |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Aturan bisnis | BR-024 |
| Berkas kode | app/Http/Controllers/Auth/SessionController.php |

## 1. Ringkasan

Admin hub masuk ke konsol melalui halaman web /login. Halaman itu hanya kerangka
Inertia; autentikasi sesungguhnya terjadi di endpoint JSON POST /api/auth/login,
karena endpoint API menjawab 401 dan tidak dapat merender layar.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | staf dengan role ADMIN |
| Middleware | api (EncryptCookies, AddQueuedCookiesToResponse, StartSession, ValidateCsrfToken) |
| Pemeriksaan peran | di dalam controller, bukan middleware staff.role |
| Syarat tambahan | staff.is_active harus true |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | form pada resources/js/pages/Auth/Login.tsx |
| Endpoint | POST /api/auth/login |
| Nama route | api.auth.login |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | baris staff dengan email tersebut ada, kolom password terisi, is_active true, role ADMIN |
| Postcondition sukses | sesi baru terbentuk, session id diregenerasi, respons mengembalikan identitas staf |
| Postcondition gagal | tidak ada sesi terbentuk; bila sebelumnya ada sesi, sesi itu dibatalkan dan token CSRF diregenerasi |

## 5. Input dan validasi

| Field | Tipe | Aturan | Pesan bila gagal |
| --- | --- | --- | --- |
| email | string | required, string, email | bawaan Laravel |
| password | string | required, string | bawaan Laravel |
| remember | boolean | opsional, dibaca dengan boolean() | - |

Validasi dilakukan lewat request->validate di dalam controller, tanpa FormRequest.

## 6. Proses langkah demi langkah

1. Ambil dan validasi email, password, dan remember.
2. Jalankan Auth::attempt dengan opsi remember.
3. Bila gagal, lempar ValidationException dengan pesan auth.failed pada key email.
4. Ambil user hasil autentikasi. Bila bukan instance Staff atau isAdmin bernilai false, jalankan Auth::logout, session invalidate, session regenerateToken, lalu lempar ValidationException berisi pesan bahwa hanya admin aktif yang dapat mengakses panel ini.
5. Bila lolos, regenerasi session id dan balas JSON berisi message dan objek user.
6. Objek user memuat id, name, email, role (nilai enum), dan phone.

Catatan: langkah 4 berarti kredensial benar dengan peran salah tetap tidak
menghasilkan sesi, dan sesi lama ikut dibersihkan.

## 7. Output

HTTP 200, bentuk respons:

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Login berhasil." |
| user.id | integer | id staf |
| user.name | string | nama staf |
| user.email | string | email staf |
| user.role | string | ADMIN |
| user.phone | string | nomor telepon staf |

## 8. Error dan edge case

| Kasus | HTTP | Bentuk respons |
| --- | --- | --- |
| Email atau password salah | 422 | errors.email berisi pesan auth.failed |
| Kredensial benar tetapi bukan Staff | 422 | errors.email berisi pesan penolakan admin |
| Kredensial benar tetapi role COURIER | 422 | sama seperti di atas, sesi lama dibatalkan |
| Kredensial benar tetapi is_active false | 422 | sama seperti di atas |
| Tanpa token CSRF | 419 | ditolak middleware ValidateCsrfToken |
| Body bukan JSON | 422 | pesan validasi Laravel |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Sesi baru dan cookie sesi | session store |
| Token CSRF baru | session |
| Tidak ada penulisan tabel | - |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup auth, name api.auth.login |
| Controller | app/Http/Controllers/Auth/SessionController.php@store |
| Model | app/Models/Staff.php method isAdmin |
| Middleware | bootstrap/app.php prepend kelompok api |
| Test | tests/Feature/AdminAccessTest.php test_login_api_authenticates_admin, tests/Feature/AdminConsoleWiringTest.php |
| UI | resources/js/pages/Auth/Login.tsx |

## 11. Skenario uji

1. Given staf admin aktif dan password benar, When login dikirim, Then HTTP 200 dan user.role bernilai ADMIN.
2. Given staf admin nonaktif, When login dikirim dengan kredensial benar, Then HTTP 422 dan permintaan berikutnya ke /api/admin/shipments mendapat 401.
3. Given password salah, When login dikirim, Then HTTP 422 pada errors.email tanpa membedakan apakah email terdaftar.

## 12. Yang perlu dikonfirmasi

1. Endpoint ini TIDAK punya rate limit, berbeda dari login kurir dan login pelanggan yang dibatasi 5 percobaan per menit. Apakah itu disengaja?
2. Tidak ada kebijakan penguncian akun setelah beberapa kegagalan. Apakah perlu ditambahkan?

