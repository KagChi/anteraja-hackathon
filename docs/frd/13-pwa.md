# FRD Modul PWA — Aplikasi Web Terpasang

Dua aplikasi web terpasang (PWA) disajikan dari satu aplikasi Laravel: **Anteraja
Frozen** untuk pengirim di `/client/*` dan **Anteraja Kurir** untuk kurir di
`/courier/*`. Keduanya satu bundel React sisi klien yang berbicara ke JSON API
yang sama dengan konsol admin, memakai bearer token per realm. Tidak ada
NativePHP, tidak ada APK, dan tidak ada web view.

---

### FR-PWA-001 — Shell dan rute dua aplikasi

- Deskripsi: Sistem HARUS menyajikan satu shell publik untuk setiap aplikasi dan menyerahkan seluruh rute di dalamnya kepada SPA.
- Aktor & Otorisasi: shell bersifat publik; otorisasi data sepenuhnya dilakukan API lewat bearer token realm masing-masing.
- Trigger: permintaan GET ke /client, /courier, dan setiap jalur di bawahnya, termasuk tautan dalam seperti /client/tracking/{resi} dan /courier/delivery/{resi}.
- Precondition: bundel PWA terbangun (resources/js/pwa/main.tsx) dan terdaftar pada vite.config.js.
- Input & Validasi: tidak ada; catch-all menerima sisa jalur sebagai parameter opsional.
- Proses / Business Rules: routes/web.php mendaftarkan `client/{path?}` dan `courier/{path?}` dengan pola `.*` dan merender resources/views/pwa.blade.php dengan `appKey`. Blade memilih identitas dari config/pwa.php: judul, theme-color, tautan manifest, ikon apple-touch, dan meta apple-mobile-web-app. SPA membaca `data-app` dari body, lalu memilih tabel rute realm itu (resources/js/pwa/App.tsx). Gerbang /client mengarahkan pengunjung bertoken ke /client/orders dan lainnya ke /client/landing; /courier mengarahkan ke /courier/tasks atau /courier/login. Rute tak dikenal di dalam realm diarahkan kembali ke gerbang realm.
- Output / Response: HTML shell (tanpa data) dan navigasi sisi klien.
- Error & Edge Cases: jalur lama seperti /courier/map/{resi} tidak lagi punya rute sendiri dan jatuh ke shell, yang mengembalikan pengunjung ke halaman utama aplikasi.
- Side Effects: tidak ada.
- Postcondition: setiap tautan dalam dapat dibuka langsung dan tetap mem-boot aplikasi yang benar.
- Traceability: routes/web.php, resources/views/pwa.blade.php, config/pwa.php, resources/js/pwa/App.tsx, tests/Feature/PwaShellTest.php.
- Acceptance Criteria:
  - Given aplikasi kurir dibuka dari tautan /courier/delivery/AJ-FRZ-0012345, When halaman dimuat, Then shell membawa `data-app="courier"` dan manifest kurir.
  - Given jalur yang tidak dikenal di dalam realm, When SPA memuatnya, Then pengunjung diarahkan ke gerbang realm.
- Status: Live

---

### FR-PWA-002 — Token realm dan penanganan sesi berakhir

- Deskripsi: Sistem HARUS menyimpan bearer token secara terpisah per realm di penyimpanan browser dan HARUS mengeluarkan pengguna dari aplikasi saat token ditolak.
- Aktor & Otorisasi: aplikasi kurir memakai realm courier; aplikasi pelanggan memakai realm client.
- Trigger: login, register, logout, dan setiap permintaan API yang menerima 401.
- Precondition: penyimpanan browser tersedia; bila tidak, aplikasi tetap dapat dipakai untuk sesi berjalan tanpa persistensi.
- Input & Validasi: token string dari `data.token` respons login/register disimpan pada kunci `anteraja.client.token` atau `anteraja.courier.token`.
- Proses / Business Rules: lib/token.ts membaca, menulis, dan menghapus token dengan penanganan kegagalan penyimpanan. Interceptor lib/api.ts menempelkan header `Authorization: Bearer` pada setiap permintaan realm itu. Bila respons 401 datang sementara token ada, token dihapus dan event `anteraja:unauthorized` dipancarkan; App.tsx menangkapnya dan mengarahkan ke halaman masuk realm tersebut dengan parameter `next` berisi jalur saat ini. Parameter `next` hanya diterima bila tetap berada di dalam realm yang sama. Logout memanggil endpoint logout realm lalu membersihkan token dan kembali ke halaman masuk.
- Output / Response: header Authorization pada setiap permintaan; navigasi ke halaman masuk saat token ditolak.
- Error & Edge Cases: token kedaluwarsa atau dicabut tidak ditampilkan sebagai galat layar melainkan sebagai sesi berakhir. Jalur `next` di luar realm diabaikan sehingga tautan berbahaya tidak dapat memindahkan browser ke luar aplikasi.
- Side Effects: nilai pada localStorage browser.
- Postcondition: permintaan berikutnya membawa token realm yang benar atau pengguna diminta masuk lagi.
- Traceability: resources/js/pwa/lib/token.ts, resources/js/pwa/lib/api.ts, resources/js/pwa/App.tsx, resources/js/pwa/screens/client/Profile.tsx, resources/js/pwa/screens/courier/Profile.tsx, tests/Feature/CourierApiAuthTest.php, tests/Feature/ClientApiAuthTest.php.
- Acceptance Criteria:
  - Given token kurir tersimpan, When daftar tugas dimuat, Then permintaan membawa token itu.
  - Given token yang sudah dicabut dipakai, When API menjawab 401, Then aplikasi kembali ke halaman masuk dengan `next` jalur saat ini.
- Status: Live

---

### FR-PWA-003 — Klien API realm

- Deskripsi: Sistem HARUS menyediakan satu klien HTTP per realm yang menormalkan setiap kegagalan menjadi satu bentuk galat.
- Aktor & Otorisasi: dipakai seluruh layar kurir dan pelanggan.
- Trigger: pemanggilan `clientApi.*` atau `courierApi.*` dari layar.
- Precondition: aplikasi disajikan dari origin yang sama dengan API (`/api`).
- Input & Validasi: jalur relatif terhadap `/api`, parameter kueri, badan JSON, atau FormData untuk unggahan foto.
- Proses / Business Rules: axios dibuat sekali per realm dengan `baseURL: "/api"` dan header Accept JSON. Setiap kegagalan transport, status non-2xx, dan badan galat diubah menjadi `ApiError` yang memuat status, pesan, peta field error 422, dan badan mentah (dipakai layar kurir untuk membaca alasan penolakan geofence). `firstError()` mengambil pesan validasi pertama untuk formulir. Tidak ada kontrak API yang berubah: seluruh endpoint /api/client/* dan /api/courier/* tetap sama seperti yang dipakai shell mobile sebelumnya.
- Output / Response: objek bertipe sesuai respons API (katalog, daftar pesanan, delivery, riwayat, profil).
- Error & Edge Cases: kegagalan koneksi menjadi pesan koneksi yang ramah; 404 pada resi yang bukan milik akun dirender sebagai kartu "tidak ditemukan", bukan kartu coba lagi.
- Side Effects: permintaan HTTP ke backend.
- Postcondition: setiap layar menerima bentuk hasil yang sama untuk setiap kemungkinan.
- Traceability: resources/js/pwa/lib/api.ts, resources/js/pwa/lib/useLoad.ts, tests/Feature/CourierFlowTest.php, tests/Feature/ClientFlowTest.php, tests/Feature/FullEndpointVerificationTest.php.
- Acceptance Criteria:
  - Given katalog dimuat, When permintaan selesai, Then layar menerima daftar produk bertipe.
  - Given server menjawab 422, When formulir dikirim, Then pesan validasi pertama ditampilkan di dekat aksi.
- Status: Live

---

### FR-PWA-004 — Kapabilitas perangkat kurir dan peta

- Deskripsi: Sistem HARUS memakai kamera, geolokasi, dan peta peramban untuk bukti serah terima dan validasi lokasi.
- Aktor & Otorisasi: aplikasi kurir.
- Trigger: tindakan kurir di layar Tasks, Delivery, Proof, dan Issue.
- Precondition: izin kamera dan lokasi diberikan pengguna; aplikasi disajikan melalui HTTPS agar geolokasi tersedia.
- Input & Validasi: foto dari `<input type="file" accept="image/*" capture="environment">` diperkecil ke sisi terpanjang 1600 px dan dikirim sebagai JPEG multipart pada field `photo` (batas server 5 MB). Jendela fix dibangun dari `navigator.geolocation.watchPosition` dengan akurasi tinggi dan dibatasi 60 titik sesuai `FakeGpsDetector::MAX_FIX_WINDOW`.
- Proses / Business Rules: lib/geo.ts menyusun `gps_signals` beserta `latitude`, `longitude`, dan `device_timestamp` yang dikirim bersama aksi depart, arrive, dan proof. Layar Delivery menggambar peta Leaflet sebaris (titik penerima, radius geofence, dan posisi terakhir) sehingga tidak ada lagi web view maupun jembatan cache. Jarak ke titik penerima dihitung di peramban hanya untuk tampilan; server tetap menghitung ulang. Tombol panggil memakai tautan `tel:` dan navigasi rute memakai tautan peta dari API. Laporan kendala meminta kategori, catatan, foto, dan suhu bila kategori suhu naik.
- Output / Response: berkas foto multipart dan array `gps_signals` pada badan permintaan.
- Error & Edge Cases: tanpa izin lokasi, aksi tetap dapat dikirim (GPS best effort, BR-011); tanpa foto, laporan dan bukti ditolak di klien maupun server. Penolakan geofence menampilkan alasan pertama dari server dan menyediakan tombol minta tinjauan hub.
- Side Effects: berkas foto diunggah; server mencatat location_checks.
- Postcondition: bukti dan sinyal lokasi tersedia bagi hub.
- Traceability: resources/js/pwa/lib/geo.ts, resources/js/pwa/lib/photo.ts, resources/js/pwa/lib/useFixWindow.ts, resources/js/pwa/screens/courier/Delivery.tsx, Proof.tsx, Issue.tsx, resources/components/LeafletMap.tsx, tests/Feature/CourierGpsValidationTest.php, tests/Feature/GpsDetectionTest.php.
- Acceptance Criteria:
  - Given kurir menekan Serahkan Paket, When permintaan terkirim, Then lampiran foto dan jendela fix GPS ikut terkirim.
  - Given titik penerima punya koordinat, When layar Delivery dibuka, Then peta menggambar titik penerima beserta radius geofence tanpa web view.
- Status: Live

---

### FR-PWA-005 — Keranjang dan struk pesanan pelanggan

- Deskripsi: Sistem HARUS mempertahankan keranjang belanja dan struk pesanan terakhir di sisi peramban antar layar SPA.
- Aktor & Otorisasi: aplikasi pelanggan.
- Trigger: penambahan produk dari katalog atau detail produk, dan pembukaan layar sukses pesanan setelah checkout.
- Precondition: penyimpanan browser tersedia; bila tidak, keranjang hanya bertahan selama sesi halaman.
- Input & Validasi: id produk integer positif dan kuantitas 1 sampai 20 dengan batas maksimum yang sama di server.
- Proses / Business Rules: keranjang disimpan pada localStorage dengan kunci `anteraja.client.cart` dan hanya memuat pasangan id produk serta kuantitas; nama dan harga selalu dibaca ulang dari katalog sehingga keranjang basi tidak dapat mengutip harga yang tidak diakui server. Produk yang tidak lagi tersedia dibuang dari keranjang saat checkout dimuat. Lencana keranjang di bilah atas ikut berubah melalui event `anteraja:cart`. Struk pesanan disimpan pada sessionStorage dengan kunci `anteraja.client.receipt` dan hanya dibaca layar sukses bila resinya cocok.
- Output / Response: peta id ke kuantitas dan objek struk.
- Error & Edge Cases: kuantitas di atas 20 dipotong ke 20, bukan ditolak. Struk yang tidak ada atau tidak cocok membuat layar sukses menampilkan keadaan ringkas, bukan data pesanan lama.
- Side Effects: nilai pada localStorage dan sessionStorage browser.
- Postcondition: layar berikutnya membaca keranjang dan struk yang sama.
- Traceability: resources/js/pwa/lib/cart.ts, resources/js/pwa/lib/useCart.ts, resources/js/pwa/lib/receipt.ts, resources/js/pwa/screens/client/Checkout.tsx, OrderSuccess.tsx, tests/Feature/ClientStorefrontTest.php.
- Acceptance Criteria:
  - Given produk ditambahkan dua kali, When keranjang dibaca, Then kuantitas produk itu bertambah dan tidak melebihi 20.
  - Given struk tidak ada atau resinya berbeda, When layar sukses dibuka, Then layar menampilkan keadaan ringkas dan bukan resi lama.
- Status: Live

---

### FR-PWA-006 — Installability, manifest, dan offline

- Deskripsi: Sistem HARUS dapat dipasang sebagai dua aplikasi terpisah dari satu origin, menyajikan manifest per aplikasi, dan tetap terbuka saat perangkat luring.
- Aktor & Otorisasi: pengunjung dan pengguna kedua aplikasi.
- Trigger: tautan manifest pada shell, registrasi service worker, dan navigasi saat perangkat tanpa koneksi.
- Precondition: origin disajikan melalui HTTPS (localhost dikecualikan).
- Input & Validasi: tidak ada.
- Proses / Business Rules: manifest disajikan dinamis pada /client.webmanifest dan /courier.webmanifest dari config/pwa.php melalui App\Support\PwaManifest, dengan `id`, `start_url`, dan `scope` berbeda sehingga satu perangkat dapat memasang kedua aplikasi berdampingan (`display: standalone`, `orientation: portrait`, `lang: id`, warna `#f9f9ff`). Ikon 192, 512, dan maskable 512 dibuat dari karya peluncur aplikasi dan disimpan di public/icons. public/sw.js mendaftarkan dirinya hanya pada build produksi: navigasi dilayani network-first dengan cadangan HTML yang pernah dikunjungi lalu /offline.html; aset `/build/*`, `/icons/*`, `/images/*`, dan `/fonts/*` dilayani cache-first; `/api/*` tidak pernah disinggahi cache. Komponen InstallCard menawarkan `beforeinstallprompt` pada Chromium dan instruksi Bagikan → Tambahkan ke Layar Utama pada iOS, dan bilah luring muncul dari event online/offline. Tema tetap satu: terang, tanpa pemilih tema.
- Output / Response: manifest JSON, service worker, halaman luring, dan kartu pemasangan.
- Error & Edge Cases: service worker tidak didaftarkan pada mode pengembangan agar tidak menyajikan bundel basi. Halaman /offline.html berdiri sendiri tanpa aset eksternal sehingga tetap tampil dari cache. Data pesanan yang basi tidak pernah disajikan: API selalu jaringan.
- Side Effects: cache peramban dan pemasangan ikon di layar utama.
- Postcondition: aplikasi dapat dipasang, dibuka dari ikon, dan menjelaskan keadaannya saat luring.
- Traceability: config/pwa.php, app/Support/PwaManifest.php, public/sw.js, public/offline.html, public/icons, resources/js/pwa/lib/service-worker.ts, resources/js/pwa/components/InstallCard.tsx, tests/Feature/PwaShellTest.php.
- Acceptance Criteria:
  - Given kedua manifest dibaca, When keduanya dibandingkan, Then id, scope, dan start_url keduanya berbeda dan setiap ikon yang dirujuk benar-benar ada.
  - Given perangkat luring, When pengguna membuka aplikasi yang pernah dikunjungi, Then shell tampil dan permintaan data gagal dengan pesan yang jelas, bukan data basi.
- Status: Live
