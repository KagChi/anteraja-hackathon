# FR-WA-003 — Lookup resi untuk bot

| Properti | Nilai |
| --- | --- |
| ID | FR-WA-003 |
| Modul | WA — Percakapan WhatsApp (n8n, WAHA, LLM) |
| Judul | Lookup resi untuk bot |
| Status | Live |
| Berkas modul | docs/frd/08-whatsapp.md |
| Aturan bisnis | BR-021, BR-022 |
| Berkas kode | app/Http/Controllers/Api/Internal/WhatsappLookupController.php |

## 1. Ringkasan

Menghubungkan nomor pengirim dengan paketnya agar bot dapat menyebutkan status dan
langkah yang tertunda. Pencocokan memerlukan resi dan nomor yang sama-sama cocok
sehingga satu pelanggan tidak dapat membuka paket orang lain.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | n8n |
| Middleware | api, force.json, internal.token, throttle 60,1 |
| Rahasia | header X-Anteraja-Token sama dengan config coldchain.n8n.token |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | POST /api/internal/whatsapp/lookup |
| Nama route | api.internal.whatsapp.lookup |
| Pemanggil | workflow inbound saat pelanggan menyebut nomor resi |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | token internal sudah dikonfigurasi |
| Postcondition | tidak ada perubahan state di database aplikasi; n8n menyimpan state percakapan |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| phone | string | required, string, max 32 |
| resi | string | required, string, max 32 |

Nomor dinormalkan ke E.164 dan resi diubah ke huruf besar serta dipangkas.

## 6. Proses langkah demi langkah

1. Middleware memeriksa token. Token kosong pada konfigurasi menghasilkan 503; header tidak cocok menghasilkan 401.
2. Validasi phone dan resi.
3. Cari paket dengan tracking_number sama dengan resi DAN pemiliknya pembeli dengan nomor tersebut.
4. Bila tidak cocok, balas HTTP 200 dengan found false; ini jawaban normal, bukan error.
5. Bila cocok, hitung template langkah yang tertunda dari OrderPresenter::resolveActionRequired dengan pemetaan: confirm_schedule ke schedule_request, request_replacement ke issue_decision, track ke in_delivery, lainnya none.
6. Susun label status dari lang dan jendela pengantaran.
7. Balas found true beserta resi, client_token, tautan klien, template, label status, dan jendela.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| found | boolean | hasil pencocokan |
| tracking_number | string | resi |
| client_token | string | token tautan paket |
| client_url | string | tautan berbasis config app.url |
| template | string | langkah yang tertunda |
| status_label | string | label status dari lang |
| window | string | label jendela WIB |

## 8. Error dan edge case

| Kasus | HTTP | Kode |
| --- | --- | --- |
| Header token tidak ada atau salah | 401 | UNAUTHENTICATED |
| Token belum disetel pada konfigurasi | 503 | INTERNAL_TOKEN_UNCONFIGURED |
| phone atau resi kosong | 422 | - |
| Resi ada tetapi nomor berbeda | 200 | found false |
| Resi tidak ada | 200 | found false |

## 9. Efek samping

Tidak ada penulisan pada database aplikasi.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup internal |
| Controller | Api/Internal/WhatsappLookupController |
| Middleware | app/Http/Middleware/VerifyInternalToken.php |
| Service | Services/Client/OrderPresenter, ClientLinkService |
| Support | PhoneFormatter, DisplayTime |
| Test | tests/Feature/WhatsappLookupTest.php (13 test termasuk token, format, kepemilikan, pemetaan status, dan tautan yang tidak mewarisi host) |
| Dokumentasi | deploy/n8n/README.md bagian resolusi resi |

## 11. Skenario uji

1. Given resi milik pembeli dengan nomor yang dikirim, When lookup dipanggil dengan token sah, Then found true dan template sesuai langkah yang menunggu.
2. Given resi yang ada tetapi nomor pengirim berbeda, When lookup dipanggil, Then found false tanpa membocorkan data paket.
3. Given token tidak dikirim, When lookup dipanggil, Then HTTP 401; bila token belum disetel pada konfigurasi, HTTP 503.
4. Given paket IN_DELIVERY, When lookup dipanggil, Then template bernilai in_delivery.

## 12. Yang perlu dikonfirmasi

1. Endpoint mengirim client_token pada respons. Karena respons hanya dilihat n8n, apakah token itu memang diperlukan, mengingat pesan ke pelanggan tidak pernah memuat tautan?
2. Rate limit 60 per menit bersifat global per IP; apakah perlu pembatasan khusus per nomor untuk mencegah enumerasi resi?

