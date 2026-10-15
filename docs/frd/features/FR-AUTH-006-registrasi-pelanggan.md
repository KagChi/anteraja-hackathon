# FR-AUTH-006 — Registrasi pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-006 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Registrasi pelanggan |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Aturan bisnis | BR-021, BR-024 |
| Berkas kode | app/Http/Requests/Api/Client/RegisterRequest.php, app/Http/Controllers/Api/Client/AuthController.php |

## 1. Ringkasan

Pelanggan menjelajah katalog sebelum punya akun, jadi registrasi adalah momen
ketika pesanan akhirnya bisa dibuat. Endpoint ini mengembalikan bentuk respons
yang sama dengan login, sehingga aplikasi dapat langsung melanjutkan ke checkout.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | publik |
| Middleware | api, force.json, client.headers, throttle client-auth |
| Rate limit | 5 per menit per kombinasi phone dan IP |
| Header respons | X-Robots-Tag noindex, Referrer-Policy no-referrer, Cache-Control private no-store, X-Content-Type-Options nosniff |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | layar Register aplikasi pelanggan, resources/js/pwa/screens/client/Register.tsx |
| Endpoint | POST /api/client/auth/register |
| Nama route | api.client.auth.register |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | nomor HP belum terdaftar di tabel buyers |
| Postcondition | satu baris buyers baru dengan password, is_active true, dan satu token ability client |

## 5. Input dan validasi

| Field | Tipe | Aturan | Catatan |
| --- | --- | --- | --- |
| name | string | required, min 2, max 120 | - |
| phone | string | required, max 20, unique buyers.phone | dinormalkan ke E.164 lebih dulu |
| password | string | required, min 8, confirmed | di-hash otomatis oleh cast model |
| password_confirmation | string | harus sama dengan password | - |
| address | string | required, min 5, max 255 | - |
| area | string | nullable, max 120 | - |
| address_type | string | nullable, harus nilai AddressType | prepareForValidation menerima label "Rumah" atau "Kantor" |

prepareForValidation melakukan dua hal: menormalkan nomor memakai PhoneFormatter
supaya pemeriksaan unik berjalan pada bentuk yang sama dengan kolom database, dan
mengubah label bahasa Indonesia menjadi nilai enum RESIDENTIAL atau COMMERCIAL.

## 6. Proses langkah demi langkah

1. Normalisasi nomor telepon dan address_type.
2. Jalankan validasi di atas.
3. Bentuk baris Buyer::create dengan name, phone, password, address, area, address_type (bawaan RESIDENTIAL), dan is_active true.
4. Hash password berlaku otomatis melalui cast model.
5. Buat token bernama client-mobile dengan ability client dan expires_at 30 hari.
6. Balas HTTP 201 dengan message, token, dan objek buyer.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Akun berhasil dibuat." |
| data.token | string | plain text token |
| data.token_type | string | Bearer |
| data.expires_at | string | ISO8601, 30 hari |
| data.buyer.id | integer | id pembeli |
| data.buyer.name | string | nama |
| data.buyer.phone | string | nomor dalam E.164 |
| data.buyer.address | string | alamat |
| data.buyer.area | string atau null | area |
| data.buyer.address_type | string | RESIDENTIAL atau COMMERCIAL |

## 8. Error dan edge case

| Kasus | HTTP | Bentuk respons |
| --- | --- | --- |
| Nomor sudah terdaftar | 422 | errors.phone berisi "Nomor HP ini sudah terdaftar. Silakan masuk." |
| password dan konfirmasi berbeda | 422 | errors.password berisi "Konfirmasi password tidak sama." |
| Password kurang dari 8 karakter | 422 | pesan bawaan min |
| address_type bukan nilai sah dan bukan label yang dikenali | 422 | pesan enum |
| Lima percobaan per menit terlampaui | 429 | code TOO_MANY_REQUESTS |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Baris pembeli baru | buyers |
| Baris token baru | personal_access_tokens |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client/auth |
| Controller | Api/Client/AuthController@register |
| FormRequest | Requests/Api/Client/RegisterRequest.php |
| Resource | Resources/Client/BuyerResource.php |
| Normalisasi | Support/PhoneFormatter.php, Enums/AddressType::fromInput |
| Test | tests/Feature/ClientStorefrontTest.php test_register_creates_a_buyer_and_returns_a_token, test_register_rejects_a_phone_that_already_has_an_account |
| UI | resources/js/pwa/screens/client/Register.tsx |

## 11. Skenario uji

1. Given nomor baru dan password sah dengan konfirmasi, When register dikirim, Then HTTP 201, baris buyers terbuat dengan nomor berawalan +62, dan token ability client diterbitkan.
2. Given nomor yang sudah dipakai akun lain, When register dikirim, Then HTTP 422 pada errors.phone dan tidak ada baris baru.
3. Given password tanpa konfirmasi yang cocok, When register dikirim, Then HTTP 422 pada errors.password.
4. Given nomor ditulis 0812 dan nomor yang sama ditulis +62812, When register dikirim dua kali, Then percobaan kedua ditolak karena keduanya dinormalkan ke bentuk yang sama.

## 12. Yang perlu dikonfirmasi

1. Tidak ada verifikasi nomor lewat OTP, sehingga nomor siapa pun dapat dipakai mendaftar. Apakah itu keputusan produk?
2. Apakah registrasi perlu memaksa pengisian area, mengingat area dipakai untuk menampilkan kota pada pelacakan publik?

