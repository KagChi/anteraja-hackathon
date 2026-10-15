# FR-ORD-001 — Daftar katalog publik

| Properti | Nilai |
| --- | --- |
| ID | FR-ORD-001 |
| Modul | ORD — Katalog dan Pemesanan Pelanggan |
| Judul | Daftar katalog publik |
| Status | Live |
| Berkas modul | docs/frd/10-katalog-pesanan.md |
| Aturan bisnis | BR-024 |
| Berkas kode | app/Http/Controllers/Api/Client/CatalogController.php |

## 1. Ringkasan

Etalase produk beku. Browsing sengaja terbuka agar orang dapat melihat isi katalog
sebelum membuat akun; hanya pemesanan yang memerlukan login.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | publik |
| Middleware | api, force.json, client.headers, throttle 60,1 |
| Header respons | noindex, no-referrer, private no-store, nosniff |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | layar awal dan tab katalog aplikasi pelanggan |
| Endpoint | GET /api/client/catalog |
| Nama route | api.client.catalog.index |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Ambil produk dengan is_available true dan urutkan memakai scope inCatalogOrder, yaitu sort_order menaik lalu id menaik.
2. Ubah setiap produk menjadi kartu memakai ProductResource.
3. Sertakan meta berisi jumlah produk dan teks suhu standar.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data[].id, name, slug, category, description | campuran | identitas produk |
| data[].price_idr, price_label | integer dan string | harga rupiah dan labelnya |
| data[].unit, weight_kg, weight_label | campuran | satuan dan berat |
| data[].image_url | string atau null | path aset gambar |
| data[].handling_notes | string atau null | catatan penanganan |
| meta.count | integer | jumlah produk |
| meta.standard_temp | string | "Beku -18°C" |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Produk tidak tersedia | tidak dikirim |
| Katalog kosong | data kosong dengan count 0 |
| Lebih dari 60 permintaan per menit | 429 dari throttle |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client catalog |
| Controller | Api/Client/CatalogController@index |
| Resource | Resources/Client/ProductResource.php |
| Model | app/Models/Product.php scope inCatalogOrder, priceLabel, weightLabel |
| Seed | database/seeders/DemoDataSeeder.php seedCatalog (delapan produk) |
| Test | tests/Feature/ClientStorefrontTest.php test_the_catalog_lists_the_seeded_frozen_goods, tests/Feature/PwaShellTest.php test_the_catalog_lists_products_and_fills_the_basket |
| UI | resources/js/pwa/screens/client/Catalog.tsx, Landing.tsx |

## 11. Skenario uji

1. Given delapan produk tersedia dan satu produk disembunyikan, When katalog diminta, Then data memuat delapan kartu.
2. Given katalog kosong, When diminta, Then HTTP 200 dengan data kosong dan meta.count 0.

## 12. Yang perlu dikonfirmasi

1. Katalog tidak dapat dikelola dari antarmuka admin; satu-satunya cara menambah produk adalah seed atau database. Apakah perlu antarmuka pengelolaan?
2. Apakah harga perlu disertai status ketersediaan stok?

