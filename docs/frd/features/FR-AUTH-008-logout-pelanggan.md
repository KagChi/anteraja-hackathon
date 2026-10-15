# FR-AUTH-008 — Logout pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-008 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Logout pelanggan |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Berkas kode | app/Http/Controllers/Api/Client/AuthController.php |

## 1. Ringkasan

Mencabut token klien yang sedang dipakai sehingga perangkat yang dijual atau
dipinjam tidak dapat membuka data pesanan pelanggan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | buyer terautentikasi |
| Middleware | api, force.json, client.headers, auth:sanctum, abilities:client, client.buyer, throttle 60,1 |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tombol keluar pada tab profil pelanggan |
| Endpoint | POST /api/client/auth/logout |
| Nama route | api.client.auth.logout |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | token ability client yang aktif |
| Postcondition | token itu tidak dapat dipakai lagi |

## 5. Input dan validasi

Tanpa body.

## 6. Proses langkah demi langkah

1. Sanctuim mengautentikasi bearer token dan middleware memastikan user bertipe Buyer serta aktif.
2. Controller mengambil user; bila instance Buyer dan currentAccessToken tidak null, hapus token itu.
3. Balas JSON berisi message "Berhasil logout." dan code LOGGED_OUT.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Berhasil logout." |
| code | string | LOGGED_OUT |

## 8. Error dan edge case

| Kasus | HTTP | Code |
| --- | --- | --- |
| Tanpa token | 401 | UNAUTHENTICATED |
| Token ber-ability courier | 403 | FORBIDDEN |
| Akun nonaktif | 403 | ACCOUNT_INACTIVE |

## 9. Efek samping

Baris token dihapus dari personal_access_tokens; layar PWA membersihkan token lokal.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client terautentikasi |
| Controller | Api/Client/AuthController@logout |
| Middleware | EnsureBuyer, bootstrap/app.php render MissingAbilityException |
| Test | tests/Feature/ClientApiAuthTest.php test_buyer_can_logout_and_revokes_token, test_sanctum_ability_isolation |
| UI | resources/js/pwa/screens/client/Profile.tsx |

## 11. Skenario uji

1. Given pembeli login, When logout dipanggil, Then HTTP 200 dan /api/client/me berikutnya 401.
2. Given token ber-ability courier, When logout pelanggan dipanggil, Then HTTP 403 FORBIDDEN.

## 12. Yang perlu dikonfirmasi

1. Apakah perlu tombol "keluar dari semua perangkat"?

