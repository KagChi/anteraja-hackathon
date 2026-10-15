# FRD Modul ORD — Katalog dan Pemesanan Pelanggan

Modul ini membuka jalur pesanan mandiri pelanggan: menjelajah katalog tanpa akun,
checkout dengan akun, lalu memantau pesanan.

Aturan bisnis terkait: BR-005, BR-021, BR-024.

---

### FR-ORD-001 — Daftar katalog publik

- Deskripsi: Sistem HARUS menampilkan produk yang tersedia untuk dibeli tanpa mengharuskan pelanggan masuk lebih dulu.
- Aktor & Otorisasi: publik, dengan force.json dan client.headers. Header keamanan klien ikut terpasang.
- Trigger: GET /api/client/catalog dari layar awal aplikasi pelanggan atau tab katalog.
- Precondition: tidak ada.
- Input & Validasi: tanpa input.
- Proses / Business Rules: hanya produk dengan is_available true yang dikirim, diurutkan memakai scope inCatalogOrder yaitu sort_order menaik lalu id menaik. Setiap produk menjadi kartu berisi id, name, slug, category, description, price_idr, price_label, unit, weight_kg, weight_label, image_url, dan handling_notes.
- Output / Response: HTTP 200 dengan data berupa koleksi kartu produk dan meta berisi count dan standard_temp bertuliskan "Beku -18°C".
- Error & Edge Cases: katalog kosong mengembalikan data kosong dengan count nol, bukan error. Produk yang disembunyikan (is_available false) tidak muncul.
- Side Effects: tidak ada.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Api/Client/CatalogController@index, Resources/Client/ProductResource.php, app/Models/Product.php scopeInCatalogOrder, database/seeders/DemoDataSeeder.php seedCatalog, tests/Feature/ClientStorefrontTest.php test_the_catalog_lists_the_seeded_frozen_goods, app/NativeComponents/Client/Catalog.php dan Landing.php.
- Acceptance Criteria:
  - Given delapan produk tersedia dan satu produk disembunyikan, When katalog diminta, Then data memuat delapan kartu dan produk yang disembunyikan tidak ada.
  - Given katalog kosong, When diminta, Then HTTP 200 dengan data kosong dan meta.count bernilai 0.
- Status: Live

---

### FR-ORD-002 — Detail produk publik

- Deskripsi: Sistem HARUS menyajikan satu produk berdasarkan slug untuk layar detail.
- Aktor & Otorisasi: publik.
- Trigger: GET /api/client/catalog/{slug}.
- Precondition: tidak ada.
- Input & Validasi: slug pada path dibatasi pola huruf kecil, angka, dan tanda hubung, serta produk harus tersedia.
- Proses / Business Rules: produk dicari dengan slug dan is_available true.
- Output / Response: HTTP 200 dengan data satu kartu produk.
- Error & Edge Cases: slug tidak dikenal atau produk tidak tersedia menghasilkan 404 dengan message "Produk tidak ditemukan." dan code PRODUCT_NOT_FOUND.
- Side Effects: tidak ada.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Api/Client/CatalogController@show, tests/Feature/ClientStorefrontTest.php test_a_product_can_be_opened_by_slug, tests/Feature/ClientNativeScreensTest.php test_an_unknown_product_shows_the_empty_state, app/NativeComponents/Client/ProductDetail.php.
- Acceptance Criteria:
  - Given slug produk yang tersedia, When detail diminta, Then HTTP 200 dengan nama, harga, dan berat produk.
  - Given slug yang tidak ada, When detail diminta, Then HTTP 404 PRODUCT_NOT_FOUND dan layar menampilkan keadaan kosong.
- Status: Live

---

### FR-ORD-003 — Checkout pesanan pelanggan

- Deskripsi: Sistem HARUS membuat paket dari keranjang pelanggan dengan sumber CLIENT_APP, menyimpan alamat sebagai profil pengiriman, membuat konfirmasi jadwal PENDING, dan meminta konfirmasi pelanggan.
- Aktor & Otorisasi: pembeli terautentikasi dengan ability client dan middleware client.buyer.
- Trigger: POST /api/client/orders dari layar Checkout.
- Precondition: minimal satu produk tersedia di dalam keranjang.
- Input & Validasi: recipient wajib objek berisi name 2 sampai 120, phone wajib maksimal 20, address 5 sampai 255, area opsional maksimal 120, address_type opsional nilai AddressType, latitude opsional antara -90 dan 90, longitude opsional antara -180 dan 180 dan wajib bila latitude ada; service_type wajib REGULER atau INSTANT_FROZEN; scheduled_date wajib format Y-m-d dan tidak lampau menurut zona Jakarta; window_start dan window_end format H:i dengan end setelah start; handling_instructions opsional maksimal 300; items wajib 1 sampai 20 baris, product_id harus ada di tabel products, quantity 1 sampai 20. prepareForValidation menormalkan nomor penerima dan address_type.
- Proses / Business Rules: data penerima ditulis ulang ke baris buyers sehingga checkout berikutnya terisi otomatis dan koordinat tersedia untuk geofence. Produk diambil ulang dari database dengan syarat is_available true; hanya produk yang benar-benar tersedia yang dipakai sehingga klien tidak dapat menentukan harga atau berat. Jumlah digabung per produk dan dibatasi 1 sampai 20. Berat paket dihitung dari berat produk dikali kuantitas dan minimal 0,1 kg. Paket dibuat berstatus CREATED dengan created_by null dan source CLIENT_APP, lalu di-refresh agar tracking_number dari PostgreSQL terisi. Item disalin ke package_items, konfirmasi PENDING dibuat, paket dipindahkan ke AWAITING_CONFIRMATION dengan aktor null, lalu notifikasi schedule_request diantrekan ke pembeli.
- Output / Response: HTTP 201 dengan message yang memuat resi dan data berupa OrderReceiptResource berisi tracking_number, status, status_label, source, service_type, service_label, recipient, scheduled_label, scheduled_date, window_start, window_end, weight_kg, item_count, total_idr, handling_instructions, dan items dengan harga per baris serta totalnya.
- Error & Edge Cases: seluruh produk yang dipilih sudah tidak tersedia menghasilkan 422 dengan code PRODUCT_UNAVAILABLE. Keranjang kosong atau lebih dari 20 baris menghasilkan 422. Tanggal lampau menghasilkan 422 pada errors.scheduled_date dengan pesan bahwa tanggal tidak boleh di masa lalu. Koordinat lintang tanpa bujur menghasilkan 422.
- Side Effects: baris buyers diperbarui; baris packages, package_items, dan schedule_confirmations; baris status_logs CREATED lalu AWAITING_CONFIRMATION dengan changed_by null; satu baris notifications QUEUED template schedule_request.
- Postcondition: paket masuk antrean hub dengan penanda sumber APP dan menunggu konfirmasi pelanggan.
- Traceability: routes/api.php, Api/Client/OrderController@store, Actions/Client/CreateClientOrder, Requests/Api/Client/StoreOrderRequest, Resources/Client/OrderReceiptResource.php, Enums/PackageSource.php, tests/Feature/ClientStorefrontTest.php test_a_signed_in_customer_can_check_out_and_the_hub_sees_the_order dan test_checkout_requires_a_session_and_a_basket, app/NativeComponents/Client/Checkout.php.
- Acceptance Criteria:
  - Given pelanggan login dengan keranjang berisi satu produk tersedia, When checkout dikirim, Then HTTP 201 dengan resi AJ-FRZ-, paket bersumber CLIENT_APP, dan notifikasi schedule_request terantre.
  - Given keranjang berisi produk yang sudah tidak tersedia, When checkout dikirim, Then HTTP 422 PRODUCT_UNAVAILABLE dan tidak ada baris packages baru.
- Status: Live

---

### FR-ORD-004 — Daftar pesanan dengan tab dan pencarian

- Deskripsi: Sistem HARUS menampilkan pesanan milik pembeli dengan tab aktif, selesai, kendala, atau semua, beserta hitungan per tab, pencarian, dan paginasi.
- Aktor & Otorisasi: pembeli terautentikasi, hanya paket dengan buyer_id miliknya.
- Trigger: GET /api/client/orders dari tab Pesanan dan Riwayat.
- Precondition: token ability client valid.
- Input & Validasi: tab salah satu active, completed, issue, all dengan bawaan active; q opsional maksimal 100; per_page 1 sampai 50 dengan bawaan 10; page minimal 1.
- Proses / Business Rules: hitungan tab dihitung dengan SATU kueri agregat memakai FILTER, bukan empat kueri: aktif adalah status di luar RECEIVED, RETURNED, dan CLOSED; selesai adalah RECEIVED atau CLOSED; kendala adalah ISSUE_REPORTED atau RETURNED; total adalah seluruh paket pembeli. Daftar memuat relasi items.product, buyer, courier, delivery, scheduleConfirmations, dan conditionChecks.issueReport untuk mencegah N+1. Pencarian memakai ILIKE pada resi atau nama produk. Urutan berdasarkan scheduled_at menurun dengan NULLS LAST lalu created_at menurun. Setiap baris disusun OrderSummaryResource memakai OrderPresenter untuk ringkasan produk, jadwal, status, penyelesaian, action_required, dan next_screen.
- Output / Response: HTTP 200 dengan data (koleksi ringkasan pesanan) dan meta berisi active_count, completed_count, standard_temp, sla_rate, tab_counts (active, completed, issue, total), dan pagination (current_page, last_page, per_page, total, has_more).
- Error & Edge Cases: tab kosong mengembalikan data kosong dengan hitungan yang benar. per_page di luar rentang menghasilkan 422. sla_rate bernilai konstanta 96 persen, bukan hasil hitung.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Api/Client/OrderController@index, Queries/ClientOrderListQuery, Requests/Api/Client/OrderIndexRequest, Resources/Client/OrderSummaryResource.php, Services/Client/OrderPresenter.php, tests/Feature/ClientApiAuthTest.php test_buyer_orders_list_with_tabs_and_counts dan test_order_ownership_isolation_returns_404_for_other_buyer, app/NativeComponents/Client/Orders.php dan History.php.
- Acceptance Criteria:
  - Given pembeli dengan tiga paket aktif dan dua paket selesai, When tab active diminta, Then data memuat tiga baris dan meta.tab_counts.active bernilai 3.
  - Given pencarian memakai resi milik pembeli lain, When daftar diminta, Then tidak ada baris yang dikembalikan karena pembatasan buyer_id.
- Status: Live

---

### FR-ORD-005 — Detail pesanan pelanggan

- Deskripsi: Sistem HARUS menyajikan ringkasan satu pesanan milik pembeli, dan HARUS menolak akses ke pesanan milik pembeli lain dengan 404.
- Aktor & Otorisasi: pembeli terautentikasi; middleware client.owned mencari paket dengan syarat buyer_id sama dengan pengguna.
- Trigger: GET /api/client/orders/{tracking_number}.
- Precondition: paket dengan resi itu dimiliki pembeli yang login.
- Input & Validasi: tracking_number pada path, diselesaikan middleware menjadi objek paket.
- Proses / Business Rules: middleware mencari paket dengan buyer_id dan tracking_number sekaligus. Bila tidak ditemukan, permintaan dihentikan dengan 404 PACKAGE_NOT_FOUND sehingga keberadaan paket tidak bocor. Respons memakai OrderSummaryResource yang sama dengan daftar, sehingga bentuk data konsisten.
- Output / Response: HTTP 200 dengan data ringkasan pesanan.
- Error & Edge Cases: resi milik pembeli lain menghasilkan 404, bukan 403. Pembeli nonaktif ditolak 403 ACCOUNT_INACTIVE sebelum sampai ke middleware kepemilikan.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Api/Client/OrderController@show, Middleware/ResolveOwnedPackage.php, tests/Feature/ClientApiAuthTest.php test_order_ownership_isolation_returns_404_for_other_buyer.
- Acceptance Criteria:
  - Given resi milik pembeli yang login, When detail diminta, Then HTTP 200 dengan data ringkasan pesanan.
  - Given resi yang ada tetapi milik pembeli lain, When detail diminta, Then HTTP 404 PACKAGE_NOT_FOUND.
- Status: Live

