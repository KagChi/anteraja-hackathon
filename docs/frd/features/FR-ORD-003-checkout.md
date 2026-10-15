# FR-ORD-003 — Checkout pesanan pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-ORD-003 |
| Modul | ORD — Katalog dan Pemesanan Pelanggan |
| Judul | Checkout pesanan pelanggan |
| Status | Live |
| Berkas modul | docs/frd/10-katalog-pesanan.md |
| Aturan bisnis | BR-002, BR-003, BR-005, BR-014, BR-021 |
| Berkas kode | app/Actions/Client/CreateClientOrder.php, app/Http/Requests/Api/Client/StoreOrderRequest.php |

## 1. Ringkasan

Pesanan mandiri dari aplikasi pelanggan. Berbeda dari paket buatan hub, paket ini
memiliki sumber CLIENT_APP, tanpa pembuat staf, dan blok penerimanya sekaligus
menjadi profil pengiriman pelanggan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | pembeli terautentikasi |
| Middleware | auth:sanctum, abilities:client, client.buyer, throttle 60,1 |
| Aktor transisi | null, karena sumbernya aplikasi pelanggan |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | layar Checkout aplikasi pelanggan |
| Endpoint | POST /api/client/orders |
| Nama route | api.client.orders.store |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | minimal satu item dikirim dan produknya masih tersedia |
| Postcondition | paket berstatus AWAITING_CONFIRMATION dengan sumber CLIENT_APP dan notifikasi permintaan konfirmasi terantre |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| recipient.name | string | required, min 2, max 120 |
| recipient.phone | string | required, max 20, dinormalkan E.164 |
| recipient.address | string | required, min 5, max 255 |
| recipient.area | string | nullable, max 120 |
| recipient.address_type | string | nullable, harus nilai AddressType |
| recipient.latitude | numeric | nullable, antara -90 dan 90 |
| recipient.longitude | numeric | nullable, antara -180 dan 180, required_with latitude |
| service_type | string | required, harus nilai ServiceType; UI aplikasi pelanggan selalu mengirim INSTANT_FROZEN karena seluruh katalog beku |
| scheduled_date | string | required, format Y-m-d, tidak sebelum hari ini Jakarta |
| window_start, window_end | string | required, format H:i, window_end setelah window_start |
| handling_instructions | string | nullable, max 300 |
| items | array | required, min 1, max 20 |
| items.*.product_id | integer | required, exists products.id |
| items.*.quantity | integer | required, min 1, max 20 |

## 6. Proses langkah demi langkah

1. Data penerima ditulis ke baris buyers: name, phone, address, area, address_type, dan koordinat bila dikirim.
2. Produk diambil ulang dari database dengan syarat is_available true, sehingga klien tidak dapat menentukan harga atau berat.
3. Kuantitas digabung per produk dan dibatasi 1 sampai 20; bila tidak ada produk yang tersisa, balas 422 PRODUCT_UNAVAILABLE.
4. Berat dihitung dari berat produk dikali kuantitas, dibulatkan dua desimal, minimal 0,1 kg.
5. Jendela diparsing di zona Jakarta, disimpan UTC, dan dibatasi pada jam mulai.
6. Paket dibuat berstatus CREATED dengan created_by null dan source CLIENT_APP.
7. Refresh agar tracking_number terisi, lalu salin item.
8. Buat konfirmasi PENDING berisi proposed_scheduled_at.
9. Transisi CREATED ke AWAITING_CONFIRMATION dengan aktor null.
10. Antrekan notifikasi schedule_request ke pembeli berisi jadwal dan penanda sumber CLIENT_APP.
11. Balas HTTP 201 dengan struk pesanan.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | menyebut resi baru |
| data.tracking_number | string | resi AJ-FRZ- |
| data.status, status_label | string | CREATED lalu AWAITING_CONFIRMATION |
| data.source, service_type, service_label | string | sumber dan layanan |
| data.recipient | objek | nama, telepon, alamat, area |
| data.scheduled_label, scheduled_date, window_start, window_end | string | jadwal |
| data.weight_kg, item_count, total_idr | campuran | ringkasan belanja |
| data.items | array | nama, kuantitas, satuan, harga, total baris |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Seluruh produk tidak tersedia | 422 PRODUCT_UNAVAILABLE, tidak ada paket dibuat |
| Tanggal lampau | 422 dengan pesan bahwa tanggal tidak boleh di masa lalu |
| window_end tidak setelah window_start | 422 |
| latitude tanpa longitude | 422 |
| items lebih dari 20 baris | 422 |
| Pelanggan tanpa token | 401 UNAUTHENTICATED |
| Akun nonaktif | 403 ACCOUNT_INACTIVE |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Profil pengiriman pembeli | buyers |
| Paket, item, konfirmasi | packages, package_items, schedule_confirmations |
| Log status | status_logs CREATED lalu AWAITING_CONFIRMATION dengan changed_by null |
| Notifikasi | schedule_request ke pembeli |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client orders |
| Controller | Api/Client/OrderController@store |
| Action | Actions/Client/CreateClientOrder |
| FormRequest | Requests/Api/Client/StoreOrderRequest.php |
| Resource | Resources/Client/OrderReceiptResource.php |
| Enum | Enums/PackageSource.php, ServiceType.php, AddressType.php |
| Test | tests/Feature/ClientStorefrontTest.php test_a_signed_in_customer_can_check_out_and_the_hub_sees_the_order, test_checkout_requires_a_session_and_a_basket, tests/Feature/PwaShellTest.php test_checkout_sends_a_guest_to_register, test_checkout_reads_the_basket_prefills_the_recipient_and_posts_the_order |
| UI | resources/js/pwa/screens/client/Checkout.tsx, OrderSuccess.tsx |
| Peta titik penerima | resources/js/components/LocationPicker.tsx, dipakai inline oleh Checkout.tsx |

## 11. Skenario uji

1. Given pelanggan login dengan satu produk tersedia, When checkout dikirim, Then HTTP 201 dengan resi, paket bersumber CLIENT_APP, dan notifikasi schedule_request terantre.
2. Given produk sudah tidak tersedia, When checkout dikirim, Then HTTP 422 PRODUCT_UNAVAILABLE tanpa paket baru.
3. Given pelanggan tanpa token, When checkout dikirim, Then HTTP 401.

## 12. Yang perlu dikonfirmasi

1. Total harga dihitung di server untuk struk, tetapi tidak ada catatan pembayaran. Apakah harga hanya informasi?
2. Alamat penerima menimpa profil pelanggan. Apakah pelanggan perlu dapat mengirim ke alamat berbeda tanpa mengubah profil utamanya?
3. Tidak ada pemeriksaan kapasitas harian saat checkout pelanggan, padahal paket hub diperiksa manual.

