# FR-AUTH-009 — Profil pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-AUTH-009 |
| Modul | AUTH — Autentikasi dan Sesi |
| Judul | Profil pelanggan |
| Status | Live |
| Berkas modul | docs/frd/01-auth.md |
| Berkas kode | app/Http/Resources/Client/BuyerProfileResource.php |

## 1. Ringkasan

Layar profil pelanggan memerlukan identitas, jumlah pesanan, dan kanal dukungan.
Data yang sama juga dipakai layar checkout untuk mengisi alamat penerima otomatis.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | buyer dengan token ability client yang aktif |
| Middleware | auth:sanctum, abilities:client, client.buyer |
| Kepemilikan | hanya membaca baris buyers milik user sendiri |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tab Profil, dan langkah pengisian penerima pada checkout |
| Endpoint | GET /api/client/me |
| Nama route | api.client.me |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | token valid, pembeli aktif |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Hitung pesanan aktif: paket dengan buyer_id milik user dan status di luar RECEIVED, RETURNED, dan CLOSED.
2. Hitung pesanan selesai: status RECEIVED atau CLOSED.
3. Baca nomor dukungan dari config coldchain.customer_service, susun tautan wa.me dengan pesan siap kirim, dan sertakan email dukungan.
4. Susun respons memakai BuyerProfileResource.

Perlu dicatat: angka on_time_rate dikirim sebagai konstanta 96 persen, bukan hasil
hitungan dari data pengiriman. Angka itu tidak boleh dipakai sebagai KPI.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data.name | string | nama pembeli |
| data.phone | string | nomor E.164 |
| data.address | string | alamat utama |
| data.area | string atau null | area |
| data.stats.active_count | integer | jumlah paket aktif |
| data.stats.completed_count | integer | jumlah paket selesai |
| data.stats.on_time_rate | string | konstanta "96%" |
| data.support.whatsapp_url | string | tautan wa.me dengan pesan siap kirim |
| data.support.email | string | email dukungan dari konfigurasi |

## 8. Error dan edge case

| Kasus | HTTP | Code | Keterangan |
| --- | --- | --- | --- |
| Tanpa token | 401 | UNAUTHENTICATED | dari render AuthenticationException |
| Akun nonaktif | 403 | ACCOUNT_INACTIVE | middleware client.buyer |
| Token ber-ability courier | 403 | FORBIDDEN | CheckAbilities |
| Konfigurasi customer_service.email tidak ada | 200 | - | memakai bawaan cs@anteraja.id |

## 9. Efek samping

Tidak ada. Dua kueri hitung dijalankan setiap pemanggilan, tanpa cache.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client terautentikasi |
| Controller | Api/Client/ProfileController@show |
| Resource | Resources/Client/BuyerProfileResource.php |
| Konfigurasi | config/coldchain.php customer_service |
| Test | tests/Feature/ClientApiAuthTest.php test_buyer_profile_endpoint_returns_stats_and_support |
| UI | app/NativeComponents/Client/Profile.php, app/Support/ClientPayload.php method profile |

## 11. Skenario uji

1. Given pembeli dengan tiga paket aktif dan dua paket selesai, When profil diminta, Then stats berisi 3 dan 2 serta support.whatsapp_url memuat nomor konfigurasi.
2. Given pembeli tanpa pesanan, When profil diminta, Then kedua angka bernilai 0 dan permintaan tetap HTTP 200.
3. Given akun dinonaktifkan setelah token diterbitkan, When profil diminta, Then HTTP 403 ACCOUNT_INACTIVE.

## 12. Yang perlu dikonfirmasi

1. Apakah on_time_rate harus dihitung dari deliveries dan status_logs, atau dihapus dari respons agar tidak menyesatkan?
2. Apakah pelanggan perlu dapat mengubah profil (nama, alamat, area) dari aplikasi? Saat ini tidak ada endpoint tulis profil.

