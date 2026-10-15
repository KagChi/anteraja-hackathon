# FR-ORD-005 — Detail pesanan pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-ORD-005 |
| Modul | ORD — Katalog dan Pemesanan Pelanggan |
| Judul | Detail pesanan pelanggan |
| Status | Live |
| Berkas modul | docs/frd/10-katalog-pesanan.md |
| Aturan bisnis | BR-020 |
| Berkas kode | app/Http/Controllers/Api/Client/OrderController.php, app/Http/Middleware/ResolveOwnedPackage.php |

## 1. Ringkasan

Detail satu pesanan dengan pembatasan kepemilikan pada lapisan middleware, bukan di
controller. Resi milik orang lain dijawab seolah tidak ada.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | pembeli terautentikasi |
| Middleware | auth:sanctum, abilities:client, client.buyer, client.owned, throttle 60,1 |
| Pembatasan | middleware mencari paket dengan buyer_id sama dengan pengguna |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | GET /api/client/orders/{tracking_number} |
| Nama route | api.client.orders.show |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket dengan resi itu milik pembeli yang login |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| tracking_number | string path | dibaca middleware sebagai package atau tracking_number atau resi |

## 6. Proses langkah demi langkah

1. Middleware memastikan user bertipe Buyer; selain itu 403 dengan code FORBIDDEN.
2. Middleware mengambil parameter route, menormalkan menjadi string resi bila perlu.
3. Middleware mencari paket dengan buyer_id sama dengan user dan tracking_number sama dengan resi.
4. Bila tidak ditemukan, balas 404 dengan code PACKAGE_NOT_FOUND sehingga keberadaan paket tidak bocor.
5. Bila ditemukan, middleware menyimpan paket pada atribut client_package.
6. Controller membalas data memakai OrderSummaryResource yang sama dengan daftar.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data | objek | ringkasan pesanan: resi, status, produk, penerima, jadwal, aksi tertunda, layar berikutnya, penyelesaian |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Resi milik pembeli lain | 404 PACKAGE_NOT_FOUND |
| Resi tidak ada | 404, sama seperti milik orang lain |
| User bukan Buyer | 403 FORBIDDEN |
| Akun nonaktif | 403 ACCOUNT_INACTIVE dari middleware client.buyer |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client orders |
| Controller | Api/Client/OrderController@show |
| Middleware | app/Http/Middleware/ResolveOwnedPackage.php |
| Resource | Resources/Client/OrderSummaryResource.php |
| Test | tests/Feature/ClientApiAuthTest.php test_order_ownership_isolation_returns_404_for_other_buyer |

## 11. Skenario uji

1. Given resi milik pembeli yang login, When detail diminta, Then HTTP 200 dengan ringkasan pesanan.
2. Given resi milik pembeli lain, When detail diminta, Then HTTP 404 PACKAGE_NOT_FOUND.

## 12. Yang perlu dikonfirmasi

1. Pesan 404 menyamakan paket milik orang lain dengan paket yang tidak ada. Apakah itu kebijakan yang diinginkan untuk aplikasi pelanggan?

