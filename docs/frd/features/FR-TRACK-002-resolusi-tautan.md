# FR-TRACK-002 — Resolusi tautan tamu berbatas waktu

| Properti | Nilai |
| --- | --- |
| ID | FR-TRACK-002 |
| Modul | TRACK — Pelacakan Publik dan Tautan Tamu |
| Judul | Resolusi tautan tamu berbatas waktu |
| Status | Live |
| Berkas modul | docs/frd/09-pelacakan.md |
| Aturan bisnis | BR-017, BR-018 |
| Berkas kode | app/Services/Client/ClientAccessService.php, app/Http/Middleware/ResolveClientPackage.php |

## 1. Ringkasan

Tautan berisi client_token adalah kunci paket. Layanan ini mengubah token menjadi
paket, membatasi percobaan gagal, dan menutup akses untuk paket terminal yang
tautannya sudah terlalu tua.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | guest pemegang tautan |
| Middleware | client.package pada grup /api/guest dan /api/client/{token} |
| Rate limit gagal | 20 percobaan per menit per IP |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | seluruh route di bawah prefix /api/guest/{token} dan /api/client/{token} |
| Titik masuk | ResolveClientPackage sebelum controller berjalan |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada |
| Postcondition | atribut client_package terisi objek Package; parameter route token dan package ditimpa dengan objek itu |

## 5. Input dan validasi

| Input | Aturan |
| --- | --- |
| token | pola hex 32 sampai 128 karakter |
| IP pemanggil | dipakai sebagai kunci rate limit |

## 6. Proses langkah demi langkah

1. Periksa penghitung kegagalan untuk IP ini. Bila sudah 20 atau lebih, lempar HttpException 429 dengan pesan sisa waktu tunggu.
2. Bila token tidak cocok pola, catat satu kegagalan dan lempar ClientLinkInvalid.
3. Cari paket berdasarkan client_token. Bila tidak ada, catat kegagalan dan lempar ClientLinkInvalid.
4. Bila paket berstatus RECEIVED, RETURNED, atau CLOSED dan updated_at-nya lebih tua dari config coldchain.client.link_ttl_days (30 hari), lempar ClientLinkExpired.
5. Bila berhasil, bersihkan penghitung kegagalan untuk IP tersebut.
6. Simpan paket pada atribut request dan ganti parameter route token dan package menjadi objek paket.

## 7. Output

| Keluaran | Tipe | Keterangan |
| --- | --- | --- |
| request attribute client_package | Package | dipakai controller |
| route parameter token dan package | Package | memudahkan controller menerima model |

## 8. Error dan edge case

| Kasus | Handler |
| --- | --- |
| Token format salah | ClientLinkInvalid; bila ini permintaan ke-21, 429 lebih dulu |
| Token tidak ditemukan | ClientLinkInvalid |
| Paket terminal lebih tua dari 30 hari | ClientLinkExpired |
| Paket aktif setua apa pun | tetap boleh diakses |
| Nomor resi dikirim sebagai token | ditolak karena bukan pola hex |

## 9. Efek samping

Penghitung rate limit per IP pada cache atau rate limiter store.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Service | Services/Client/ClientAccessService.php |
| Middleware | app/Http/Middleware/ResolveClientPackage.php |
| Exception | app/Exceptions/ClientLinkInvalid.php, app/Exceptions/ClientLinkExpired.php |
| Konfigurasi | config/coldchain.php client.link_ttl_days |
| Migrasi | 0001_01_01_000021_add_client_token_to_packages.php |
| Test | tests/Unit/ClientUnitTest.php (enam test tentang token, rate limit, dan kedaluwarsa), tests/Feature/ClientFlowTest.php |
| UI | seluruh layar pelanggan berbasis tautan |

## 11. Skenario uji

1. Given token sah untuk paket aktif, When tautan dibuka, Then paket tersedia dan controller berjalan.
2. Given token format salah, When dibuka, Then permintaan ditolak dan penghitung kegagalan bertambah.
3. Given 20 percobaan gagal dalam satu menit, When percobaan ke-21 dikirim, Then HTTP 429.
4. Given paket RECEIVED dengan updated_at 40 hari lalu, When tautannya dibuka, Then akses ditolak sebagai kedaluwarsa.

## 12. Yang perlu dikonfirmasi

1. TTL 30 hari dihitung dari updated_at paket, bukan dari waktu tautan dibuat. Perubahan status terakhir memperpanjang umur tautan; apakah itu disengaja?
2. Apakah pelanggan perlu diberi tahu bahwa tautannya sudah kedaluwarsa beserta cara memperoleh tautan baru?

