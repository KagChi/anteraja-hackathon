# FRD Modul MOB — Shell Mobile Native

Dua aplikasi Android dibangun dari satu basis kode Laravel memakai NativePHP Mobile.
Setiap layar adalah komponen native; tidak ada web view. Data selalu diambil dari
API backend yang sama dengan yang dipakai konsol dan pelanggan.

---

### FR-MOB-001 — Rute dan grup layar native

- Deskripsi: Sistem HARUS mendaftarkan jalur layar kedua aplikasi: grup tab dengan bilah bawah untuk tujuan utama, dan layar bertumpuk dengan bilah atas sendiri untuk alur yang didorong.
- Aktor & Otorisasi: layar kurir memakai token realm courier; layar pelanggan memakai token realm client. Otorisasi data tetap dilakukan API.
- Trigger: berkas routes/mobile.php dimuat oleh paket NativePHP Mobile, bukan didaftarkan di bootstrap/app.php.
- Precondition: build APK dijalankan untuk aplikasi yang bersangkutan.
- Input & Validasi: tidak ada.
- Proses / Business Rules: grup tab kurir berisi Tasks, History, dan Profile dengan layout CourierTabsLayout. Layar kurir bertumpuk adalah Delivery, Proof, dan Issue yang masing-masing menerima parameter resi, ditambah Start dan Login yang berada di luar grup tab agar tidak memakai bilah bawah. Grup tab pelanggan berisi Landing, Orders, History, dan Profile dengan layout ClientTabsLayout. Layar pelanggan bertumpuk adalah Tracking, Schedule, dan Issue dengan parameter resi, ditambah Catalog, ProductDetail dengan parameter slug, Register, Checkout, OrderSuccess, Start, dan Login. Alias kelas ditulis lengkap karena nama kelas pelanggan dan kurir bertabrakan.
- Output / Response: tabel rute native yang dapat divalidasi perintah native:validate dan dipakai saat membangun APK.
- Error & Edge Cases: nama kelas yang ditulis sebagai import pendek akan salah resolusi saat build, karena itu ditulis lengkap dengan namespace penuh. Layar tanpa token yang dibuka langsung tetap dapat memuat halaman kosong karena pengambilan data akan gagal dan ditampilkan sebagai keadaan coba lagi.
- Side Effects: tidak ada.
- Postcondition: aplikasi dapat dinavigasi antar layar tanpa web view.
- Traceability: routes/mobile.php, app/NativeComponents/Layouts/CourierTabsLayout.php dan ClientTabsLayout.php, tests/Feature/CourierNativeScreensTest.php test_the_courier_tabs_are_routable, test_the_pushed_customer_routes_resolve, test_no_screen_renders_a_web_view, tests/Feature/ClientNativeScreensTest.php test_the_customer_tabs_are_routable.
- Acceptance Criteria:
  - Given aplikasi kurir dibangun, When daftar rute native divalidasi, Then setiap layar kurir dan pelanggan punya rute yang dapat diresolusi.
  - Given layar apa pun dibuka, When isinya diperiksa, Then tidak ada komponen web view yang dipakai.
- Status: Live

---

### FR-MOB-002 — Penyimpanan token per realm

- Deskripsi: Sistem HARUS menyimpan token API secara terpisah per realm, di keychain atau keystore pada perangkat, dan HARUS tetap berfungsi pada lingkungan tanpa jembatan native.
- Aktor & Otorisasi: aplikasi kurir memakai realm courier; aplikasi pelanggan memakai realm client.
- Trigger: pemanggilan MobileToken::get, set, dan clear dari layar login, layar profil, dan MobileApi.
- Precondition: tidak ada.
- Input & Validasi: realm bertipe string dan kunci dibentuk sebagai mobile.{realm}.token.
- Proses / Business Rules: bila fungsi nativephp_call tersedia, nilai disimpan dan dibaca melalui SecureStorage. Sebagai cadangan untuk pengujian komponen dan server pengembangan, nilai yang sama disimpan pada cache store, yang pada shell mobile memakai driver berkas sehingga bertahan antar peluncuran. Penghapusan membersihkan kedua tempat.
- Output / Response: string token atau null.
- Error & Edge Cases: tanpa jembatan native, token tetap terbaca dari cache sehingga layar dapat dikembangkan dan diuji tanpa perangkat. Token yang hilang membuat layar meminta login lagi.
- Side Effects: nilai pada SecureStorage dan cache store.
- Postcondition: permintaan API berikutnya membawa token realm yang benar.
- Traceability: app/Support/MobileToken.php, app/Support/MobileApi.php, .env.mobile.example, tests/Feature/CourierNativeScreensTest.php test_requests_carry_the_stored_token_and_the_configured_host, tests/Feature/ClientNativeScreensTest.php test_the_login_screen_stores_the_token_and_enters_the_app.
- Acceptance Criteria:
  - Given token kurir tersimpan, When permintaan ke API dikirim, Then header Authorization memuat token itu.
  - Given token dihapus, When layar yang membutuhkan data dibuka, Then aplikasi menganggap pengguna belum masuk.
- Status: Live

---

### FR-MOB-003 — Klien API mobile

- Deskripsi: Sistem HARUS menyediakan satu pembungkus HTTP untuk semua layar native, yang menambahkan host dan token, serta menormalkan kegagalan menjadi satu bentuk hasil.
- Aktor & Otorisasi: dipakai layar kurir dan pelanggan dengan realm masing-masing.
- Trigger: pemanggilan MobileApi::client() atau courier() dengan get, post, atau postFile dari setiap layar.
- Precondition: host API terkonfigurasi pada config mobile.api_base_url.
- Input & Validasi: path relatif terhadap host, query atau data opsional, dan untuk unggahan berkas path berkas lokal beserta nama field.
- Proses / Business Rules: permintaan memakai Accept JSON dan timeout dari config mobile.timeout dengan bawaan 20 detik. Token realm disertakan sebagai bearer bila tersedia. Kegagalan transport apa pun, termasuk koneksi dan timeout, diubah menjadi ApiResult::failure dengan pesan yang mengarahkan pengguna memeriksa koneksi. Respons non-2xx memakai message dari body bila ada, atau pesan generik yang menyertakan kode status. Respons sukses dibungkus ApiResult::success. ApiResult menyediakan data() yang membuka envelope data bila ada, get(), dan firstError() untuk mengambil pesan validasi pertama pada kegagalan 422.
- Output / Response: objek ApiResult dengan ok, status, raw, dan message.
- Error & Edge Cases: layar tidak pernah menerima exception dari jaringan karena semuanya dibungkus. Berkas yang tidak ada di disk dilewati sehingga permintaan tetap dikirim tanpa lampiran, dan server akan menolaknya dengan 422 bila berkas itu wajib.
- Side Effects: permintaan HTTP ke backend.
- Postcondition: layar memiliki satu bentuk hasil untuk semua kemungkinan.
- Traceability: app/Support/MobileApi.php, app/Support/ApiResult.php, config/mobile.php, tests/Feature/ClientNativeScreensTest.php test_a_failed_call_renders_the_retry_state, tests/Feature/CourierNativeScreensTest.php test_a_failed_action_shows_its_reason_without_losing_the_screen.
- Acceptance Criteria:
  - Given token realm client tersedia, When layar memanggil katalog, Then permintaan mengarah ke host konfigurasi dan membawa bearer token itu.
  - Given server tidak dapat dihubungi, When layar memanggil API, Then ApiResult ok bernilai false dengan pesan koneksi dan layar menampilkan keadaan coba lagi.
- Status: Live

---

### FR-MOB-004 — Kapabilitas perangkat kurir

- Deskripsi: Sistem HARUS memakai kamera dan geolokasi perangkat pada alur bukti serah terima dan validasi lokasi.
- Aktor & Otorisasi: aplikasi kurir.
- Trigger: tindakan kurir di layar Proof, Issue, Tasks, dan Delivery.
- Precondition: izin kamera dan lokasi diberikan pengguna.
- Input & Validasi: berkas foto dari kamera diunggah sebagai multipart; jendela fix GPS disusun dari LocationReceived dan LocationUpdated dengan akurasi tinggi dan jarak minimum lima meter.
- Proses / Business Rules: Camera::getPhoto dipakai untuk mengambil foto bukti dan foto kendala. Geolocation::watchPosition dipakai untuk mengumpulkan jendela fix, dan Geolocation::getCurrentPosition dipakai untuk memperoleh satu posisi saat mengukur jarak. Fix yang terkumpul dikirim bersama aksi depart, arrive, dan proof agar server dapat menjalankan detektor FR-GPS-001. Tautan peta dan panggilan telepon dibuka lewat Browser dan tautan luar, bukan di dalam aplikasi.
- Output / Response: berkas foto pada request multipart dan array gps_signals pada body.
- Error & Edge Cases: tanpa izin lokasi, pengiriman tetap dapat diselesaikan karena GPS bersifat best effort (BR-011); server hanya tidak menerima bukti lokasi. Tanpa izin kamera, kurir tidak dapat mengirim bukti karena foto wajib.
- Side Effects: berkas foto pada perangkat diunggah ke server; baris location_checks dibuat server.
- Postcondition: bukti dan sinyal lokasi tersedia bagi hub.
- Traceability: app/NativeComponents/Courier/Proof.php, Issue.php, Tasks.php, Delivery.php, Native/Mobile/Facades/Camera dan Geolocation, tests/Feature/CourierNativeScreensTest.php test_arriving_ships_the_collected_fix_window, test_departing_from_the_app_posts_the_action_with_the_fix, test_the_hand_over_screen_shows_the_device_accuracy, tests/Feature/CourierGpsValidationTest.php test_the_courier_flow_still_works_without_a_gps_fix.
- Acceptance Criteria:
  - Given kurir mengirim bukti serah terima dari kamera, When permintaan dikirim, Then lampiran foto dan jendela fix GPS ikut terkirim.
  - Given perangkat tanpa izin lokasi, When kurir berangkat, Then aksi tetap berhasil tanpa data lokasi dan tanpa blokir.
- Status: Live

---

### FR-MOB-005 — Keranjang dan struk lokal pelanggan

- Deskripsi: Sistem HARUS mempertahankan keranjang belanja dan struk pesanan terakhir antar layar yang terpisah.
- Aktor & Otorisasi: aplikasi pelanggan.
- Trigger: penambahan produk dari katalog atau detail produk, dan pembukaan layar sukses pesanan setelah checkout.
- Precondition: tidak ada.
- Input & Validasi: id produk integer positif dan kuantitas 1 sampai 20 dengan batas maksimum yang sama di server.
- Proses / Business Rules: keranjang disimpan pada cache store dengan kunci mobile.client.cart dan hanya memuat pasangan id produk serta kuantitas; nama dan harga selalu dibaca ulang dari katalog sehingga keranjang basi tidak dapat mengutip harga yang tidak diakui server. Keranjang kosong menghapus kuncinya. Jumlah total potongan dipakai sebagai lencana tab. Struk pesanan terakhir disimpan pada kunci mobile.client.last_receipt selama enam jam karena layar sukses adalah komponen terpisah dan pesanan baru belum masuk daftar pesanan yang terjadwal.
- Output / Response: array id ke kuantitas dan array struk.
- Error & Edge Cases: kuantitas di atas 20 dipotong ke 20, bukan ditolak. Struk lama yang kedaluwarsa tidak lagi dibaca sehingga layar sukses menampilkan keadaan kosong, bukan data pesanan lama.
- Side Effects: nilai pada cache store perangkat.
- Postcondition: layar berikutnya membaca keranjang dan struk yang sama.
- Traceability: app/Support/ClientCart.php, app/Support/ClientReceipt.php, app/NativeComponents/Client/Catalog.php, ProductDetail.php, Checkout.php, OrderSuccess.php, tests/Feature/ClientNativeScreensTest.php test_the_catalog_lists_products_and_fills_the_basket, test_checkout_reads_the_basket_prefills_the_recipient_and_posts_the_order, test_the_order_success_screen_shows_the_new_resi, test_the_success_screen_ignores_a_stale_receipt.
- Acceptance Criteria:
  - Given produk ditambahkan dua kali, When keranjang dibaca, Then kuantitas produk itu bertambah dan tidak melebihi 20.
  - Given struk yang sudah kedaluwarsa, When layar sukses dibuka, Then layar menampilkan keadaan kosong dan bukan resi lama.
- Status: Live

---

### FR-MOB-006 — Pemilih tema aplikasi

- Deskripsi: Sistem HARUS mengizinkan pengguna memilih tema sistem, terang, atau gelap, dan HARUS mempertahankan pilihan itu antar peluncuran.
- Aktor & Otorisasi: aplikasi kurir dan pelanggan.
- Trigger: pemilihan mode pada layar profil.
- Precondition: tidak ada.
- Input & Validasi: indeks mode 0 untuk sistem, 1 untuk terang, 2 untuk gelap. Indeks di luar itu jatuh ke sistem.
- Proses / Business Rules: preferensi disimpan pada cache store dengan kunci native.appearance dan diterapkan ulang setiap kali proses boot dari AppServiceProvider. Mode sistem mengembalikan palet yang ditulis config/native-ui.php sehingga platform memilih terang atau gelap mengikuti pengaturan perangkat. Mode terang atau gelap menyatukan kedua blok palet menjadi satu warna pilihan, karena platform tetap memilih per elemen berdasarkan skema perangkat. Setelah palet berubah, cache parser Tailwind dibersihkan agar warna baru berlaku pada frame berikutnya. Pembacaan preferensi dilakukan dengan penanganan kegagalan agar store yang tidak terjangkau tidak menjatuhkan aplikasi.
- Output / Response: mode tersimpan sebagai string dan palet tema yang berlaku pada proses.
- Error & Edge Cases: preferensi tidak valid dianggap sistem. Palet yang belum dimuat oleh penyedia native-ui menghasilkan pembacaan kosong dan tidak dipin, sehingga aplikasi tetap memakai palet bawaan. Batasan yang diketahui: chrome sistem operasi seperti bilah status dan papan tombol tetap mengikuti perangkat.
- Side Effects: nilai native.appearance pada cache store dan keadaan palet pada proses.
- Postcondition: aplikasi menggambar palet yang dipilih pengguna.
- Traceability: app/Support/Appearance.php, config/native-ui.php, app/Providers/AppServiceProvider.php booted, .env.mobile.example, tests/Feature/ClientNativeScreensTest.php test_the_profile_switches_the_app_theme, test_choosing_the_device_theme_restores_the_authored_palette, tests/Feature/CourierNativeScreensTest.php test_the_profile_switches_the_app_theme.
- Acceptance Criteria:
  - Given pengguna memilih mode gelap, When aplikasi diluncurkan ulang, Then palet gelap kembali diterapkan dari preferensi tersimpan.
  - Given indeks mode yang tidak dikenal, When pemilihan disimpan, Then mode kembali ke sistem dan bukan warna acak.
- Status: Live

