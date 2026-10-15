# FR-ORD-002 — Detail produk publik

| Properti | Nilai |
| --- | --- |
| ID | FR-ORD-002 |
| Modul | ORD — Katalog dan Pemesanan Pelanggan |
| Judul | Detail produk publik |
| Status | Live |
| Berkas modul | docs/frd/10-katalog-pesanan.md |
| Berkas kode | app/Http/Controllers/Api/Client/CatalogController.php |

## 1. Ringkasan

Satu produk berdasarkan slug untuk layar detail, tempat pelanggan memilih jumlah
sebelum menambah ke keranjang.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | publik |
| Middleware | api, force.json, client.headers, throttle 60,1 |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | app/NativeComponents/Client/ProductDetail.php |
| Endpoint | GET /api/client/catalog/{slug} |
| Nama route | api.client.catalog.show |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Aturan |
| --- | --- | --- |
| slug | string path | pola huruf kecil, angka, dan tanda hubung; produk harus tersedia |

## 6. Proses langkah demi langkah

1. Cari produk dengan slug dan is_available true.
2. Bila tidak ada, balas 404 dengan message "Produk tidak ditemukan." dan code PRODUCT_NOT_FOUND.
3. Bila ada, balas data berupa satu kartu produk memakai ProductResource.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data | objek | kartu produk lengkap dengan harga dan catatan penanganan |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Slug tidak ada | 404 PRODUCT_NOT_FOUND |
| Produk ada tetapi is_available false | 404, sama seperti tidak ada |
| Slug dengan huruf besar | tidak cocok pola route sehingga ditolak sebagai 404 |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client catalog |
| Controller | Api/Client/CatalogController@show |
| Resource | Resources/Client/ProductResource.php |
| Test | tests/Feature/ClientStorefrontTest.php test_a_product_can_be_opened_by_slug, tests/Feature/ClientNativeScreensTest.php test_an_unknown_product_shows_the_empty_state, test_the_product_detail_picks_a_quantity_and_adds_it |
| UI | app/NativeComponents/Client/ProductDetail.php |

## 11. Skenario uji

1. Given slug produk yang tersedia, When detail diminta, Then HTTP 200 dengan nama, harga, dan berat.
2. Given slug yang tidak ada, When detail diminta, Then HTTP 404 PRODUCT_NOT_FOUND dan layar menampilkan keadaan kosong.

## 12. Yang perlu dikonfirmasi

1. Apakah perlu endpoint serupa berdasarkan id, mengingat keranjang menyimpan id produk dan bukan slug?

