# FRD Modul LAND — Landing Page dan Blog

Modul ini adalah wajah publik produk: halaman pemasaran, panel lacak resi, blog,
dan halaman login staf.

---

### FR-LAND-001 — Halaman landing

- Deskripsi: Sistem HARUS menampilkan halaman pemasaran yang menyajikan produk unggulan dari katalog, layanan, keunggulan, jangkauan, perkiraan tarif, panel lacak resi, dan tautan unduhan dua aplikasi Android.
- Aktor & Otorisasi: publik pada route web GET /, tanpa autentikasi.
- Trigger: kunjungan ke alamat utama situs.
- Precondition: tidak ada.
- Input & Validasi: tanpa input.
- Proses / Business Rules: server mengambil maksimal empat produk dengan is_available true diurutkan menurut urutan katalog, lalu mengirimnya sebagai properti products ke halaman Inertia Landing. Konten pemasaran lainnya (statistik hero, daftar layanan, keunggulan, jangkauan, tarif per kilogram, tautan tiga konsol, dan tautan unduhan) berasal dari resources/js/data/landing.ts dan konfigurasi mobile.apps yang dibagikan middleware HandleInertiaRequests sebagai properti downloads. Panel lacak resi memanggil endpoint publik FR-TRACK-001 sehingga pengunjung dapat melacak tanpa masuk.
- Output / Response: HTML halaman Landing dengan props products dan downloads.
- Error & Edge Cases: tanpa produk tersedia, properti products kosong dan blok produk unggulan tidak dirender. Tautan unduhan kosong membuat kartu unduhan tidak muncul.
- Side Effects: tidak ada.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/web.php route home, app/Models/Product.php scopeInCatalogOrder, Resources/Client/ProductResource.php, Middleware/HandleInertiaRequests.php, config/mobile.php dan mobile/apps.php, resources/js/pages/Landing.tsx, resources/js/data/landing.ts, resources/js/components/site/TrackResiCard.tsx, tests/Feature/LandingPageTest.php test_the_landing_page_shows_catalog_highlights_and_both_apk_links.
- Acceptance Criteria:
  - Given sembilan produk tersedia, When halaman utama dibuka, Then properti products memuat tepat empat produk sesuai urutan katalog.
  - Given konfigurasi unduhan berisi dua aplikasi, When halaman dibuka, Then kedua tautan APK tersedia pada halaman.
- Status: Live

---

### FR-LAND-002 — Blog index dan artikel

- Deskripsi: Sistem HARUS menyediakan daftar artikel dan halaman satu artikel berdasarkan slug.
- Aktor & Otorisasi: publik.
- Trigger: GET /blog dan GET /blog/{slug}.
- Precondition: tidak ada.
- Input & Validasi: slug dibatasi pola huruf kecil, angka, dan tanda hubung.
- Proses / Business Rules: kedua route hanya merender komponen Inertia dan meneruskan slug sebagai properti. Seluruh isi artikel berasal dari module TypeScript resources/js/data/posts.ts yang memuat lima artikel dengan judul, ringkasan, kategori, waktu baca, tanggal terbit, penulis, dan blok bagian berisi paragraf dan daftar butir. Tidak ada penyimpanan konten di database.
- Output / Response: HTML halaman Blog/Index atau Blog/Show dengan props slug.
- Error & Edge Cases: slug yang tidak ada pada module konten menghasilkan halaman artikel yang mencari data dan tidak menemukannya; karena konten hidup di sisi frontend, backend tidak dapat mengembalikan 404 untuk kasus ini.
- Side Effects: tidak ada.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/web.php route blog.index dan blog.show, resources/js/pages/Blog/Index.tsx dan Show.tsx, resources/js/data/posts.ts, resources/js/components/site/PostCard.tsx, tests/Feature/LandingPageTest.php test_the_blog_index_and_an_article_are_reachable.
- Acceptance Criteria:
  - Given lima artikel terdaftar di module konten, When halaman blog dibuka, Then daftar artikel tampil.
  - Given slug yang tidak terdaftar, When halaman artikel dibuka, Then halaman tetap merender tanpa error 500 dari backend.
- Status: Live

---

### FR-LAND-003 — Halaman login admin dan redirect ringkas

- Deskripsi: Sistem HARUS menyediakan halaman login staf pada sisi web dan mengalihkan path pendek ke halaman kerja yang sesungguhnya.
- Aktor & Otorisasi: publik untuk halaman login; pengguna yang sudah login diarahkan ke antrean shipment.
- Trigger: GET /login, GET /admin, GET /admin/exception, GET /courier, GET /client, GET /client/tracking.
- Precondition: tidak ada.
- Input & Validasi: tanpa input. Parameter query date dan status diteruskan ke halaman admin/Shipment, q dan status ke admin/History.
- Proses / Business Rules: halaman login hanya merender komponen Auth/Login karena autentikasi sesungguhnya dilakukan API JSON yang menjawab 401 dan tidak dapat merender layar. Bila pengguna sudah terautentikasi, permintaan ke /login dialihkan ke /admin/shipment. Redirect ringkas memetakan /admin ke /admin/shipment, /courier ke /courier/tasks, /client dan /client/tracking ke /client/orders, serta /admin/exception ke /admin/shipment karena detail kendala kini hidup pada modal shipment.
- Output / Response: HTML halaman login, atau respons pengalihan HTTP 302.
- Error & Edge Cases: staf yang belum login membuka halaman di bawah prefix /admin akan diarahkan ke halaman login oleh middleware EnsureStaffRole, bukan ditolak dengan halaman kesalahan.
- Side Effects: tidak ada.
- Postcondition: tamu sampai di halaman login; pengguna yang sudah login sampai di halaman kerja.
- Traceability: routes/web.php route login dan redirect, Middleware/EnsureStaffRole.php, resources/js/pages/Auth/Login.tsx, tests/Feature/AdminConsoleWiringTest.php test_the_login_page_renders, test_a_guest_is_sent_to_the_login_page, test_the_date_filter_names_one_delivery_day, tests/Feature/LandingPageTest.php test_the_landing_page_still_routes_staff_to_the_admin_login.
- Acceptance Criteria:
  - Given tamu membuka halaman admin, When middleware berjalan, Then tamu diarahkan ke halaman login dan bukan menerima halaman kesalahan.
  - Given admin yang sudah login membuka /login, When halaman diminta, Then permintaan dialihkan ke /admin/shipment.
- Status: Live

