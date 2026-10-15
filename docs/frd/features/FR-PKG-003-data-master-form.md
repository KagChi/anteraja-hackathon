# FR-PKG-003 — Data master form pembuatan paket

| Properti | Nilai |
| --- | --- |
| ID | FR-PKG-003 |
| Modul | PKG — Paket dan FSM Status |
| Judul | Data master form pembuatan paket |
| Status | Live |
| Berkas modul | docs/frd/02-paket-fsm.md |
| Berkas kode | app/Http/Controllers/Admin/ShipmentController.php |

## 1. Ringkasan

Mengisi form pembuatan paket membutuhkan tiga daftar: produk yang boleh dipilih,
pembeli yang sudah ada, dan kurir aktif. Endpoint ini menyediakan ketiganya dalam
satu permintaan agar form siap dipakai tanpa beberapa panggilan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | pembukaan modal pembuatan shipment |
| Endpoint | GET /api/admin/shipments/create |
| Nama route | api.admin.shipments.create |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Ambil seluruh produk diurut nama menaik, tanpa memfilter ketersediaan.
2. Ambil maksimal 50 pembeli diurut nama menaik.
3. Ambil kurir aktif: Staff dengan role COURIER dan is_active true, hanya kolom id, name, dan phone, diurut nama.
4. Balas ketiga daftar sebagai JSON.

Perlu dicatat: daftar produk di sini mengirim seluruh kolom model Product,
sedangkan katalog pelanggan memakai ProductResource. Perbedaan bentuk itu
disengaja karena pemakainya berbeda.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| products | array | seluruh baris products |
| buyers | array | maksimal 50 baris buyers |
| couriers | array | kurir aktif berisi id, name, phone |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Database kosong | 200 | tiga array kosong, bukan error |
| Lebih dari 50 pembeli | 200 | hanya 50 pertama menurut nama |
| Kurir nonaktif | 200 | tidak termasuk dalam daftar |

## 9. Efek samping

Tidak ada.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin shipments |
| Controller | Admin/ShipmentController@create |
| Model | Product, Buyer, Staff |
| Test | tests/Feature/AdminConsoleWiringTest.php test_the_create_master_data_includes_the_courier_roster |
| UI | resources/js/pages/admin/Shipment.tsx, resources/js/components/CourierPickerDialog.tsx |

## 11. Skenario uji

1. Given satu kurir aktif dan satu kurir nonaktif, When data master diminta, Then couriers hanya memuat kurir aktif.
2. Given database kosong, When data master diminta, Then HTTP 200 dengan tiga array kosong.

## 12. Yang perlu dikonfirmasi

1. Batas 50 pembeli cukup untuk hub kecil, tetapi pencarian pembeli tidak tersedia. Apakah perlu endpoint pencarian pembeli terpisah?
2. Produk yang sudah dinonaktifkan tetap muncul di daftar ini, berbeda dengan katalog pelanggan. Apakah itu disengaja?

