# FR-AUTH-002 — Logout sesi admin

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-002 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Logout sesi admin |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Berkas kode | app/Http/Controllers/Auth/SessionController.php |

## 1. Ringkasan

Mengakhiri sesi admin dan membersihkan state sesi agar cookie lama tidak dapat
dipakai kembali.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | staf yang sedang memiliki sesi |
| Middleware | api dengan ValidateCsrfToken |
| Tanpa sesi | endpoint tetap menjawab 200 tanpa efek |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tombol keluar pada layout konsol |
| Endpoint | POST /api/auth/logout |
| Nama route | api.auth.logout |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | klien memegang cookie sesi dan token CSRF yang sah |
| Postcondition | sesi tidak valid lagi; permintaan ke halaman admin dialihkan ke login |

## 5. Input dan validasi

Tidak ada body. Satu-satunya syarat adalah token CSRF yang dikirim pada header
X-XSRF-TOKEN atau field _token.

## 6. Proses langkah demi langkah

1. Middleware memverifikasi token CSRF.
2. Auth::logout dijalankan.
3. Session di-invalidate sehingga id sesi lama tidak dapat dipakai.
4. Token CSRF diregenerasi untuk sesi baru.
5. Balas JSON berisi message "Logout berhasil.".

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Logout berhasil." |
| HTTP | - | 200 |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Tanpa token CSRF | 419 | ditolak middleware |
| Tanpa cookie sesi | 419 atau 200 | bergantung apakah token CSRF masih terbaca |

## 9. Efek samping

Sesi lama tidak valid dan cookie sesi lama diabaikan pada permintaan berikutnya.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php, name api.auth.logout |
| Controller | SessionController@destroy |
| Middleware | bootstrap/app.php prepend ValidateCsrfToken |
| Test | tests/Feature/AdminConsoleWiringTest.php, tests/Feature/AdminAccessTest.php |

## 11. Skenario uji

1. Given admin login, When logout dikirim dengan token CSRF sah, Then HTTP 200 dan permintaan berikutnya ke /api/admin/shipments mendapat 401.
2. Given permintaan logout tanpa token CSRF, When dikirim, Then ditolak 419 dan sesi tetap hidup.

## 12. Yang perlu dikonfirmasi

1. Apakah perlu pembersihan token Sanctum milik staf yang sama pada saat logout sesi, bila staf itu juga memakai aplikasi kurir?

