# FRD Modul AUTH — Autentikasi dan Sesi

Modul ini mencakup empat jalur masuk yang berbeda: sesi admin, token kurir, akun
pelanggan, dan klaim tautan token. Masing-masing memakai identitas yang berbeda
dan tidak boleh saling menembus.

Aturan bisnis terkait: BR-003, BR-017, BR-021, BR-024, BR-025.

---

### FR-AUTH-001 — Login sesi admin

- Deskripsi: Sistem HARUS mengautentikasi staf dengan email dan password, dan HANYA mengizinkan staf berperan ADMIN yang aktif untuk mendapat sesi panel.
- Aktor & Otorisasi: staf role ADMIN. Route tidak memakai middleware role karena pemeriksaan dilakukan di dalam controller.
- Trigger: POST /api/auth/login, dipanggil halaman Inertia Auth/Login.
- Precondition: baris staff dengan email tersebut ada, is_active true, dan role ADMIN.
- Input & Validasi: email wajib string format email; password wajib string; remember boolean opsional. Validasi di dalam controller, bukan FormRequest.
- Proses / Business Rules: Auth::attempt dijalankan dengan remember. Bila gagal, ValidationException dengan pesan auth.failed. Bila user bukan Staff atau bukan admin, sesi langsung dilogout, di-invalidate, token CSRF diregenerasi, lalu ValidationException dengan pesan bahwa hanya admin aktif yang boleh masuk.
- Output / Response: HTTP 200 dengan message "Login berhasil." dan objek user berisi id, name, email, role, phone.
- Error & Edge Cases: kredensial salah dan pengguna bukan admin sama-sama menghasilkan HTTP 422 pada errors.email. Permintaan tanpa token CSRF ditolak 419 karena kelompok api memakai ValidateCsrfToken.
- Side Effects: sesi diregenerasi. Tidak ada baris database tambahan.
- Postcondition: permintaan berikutnya mengenali staf sebagai user sesi.
- Traceability: routes/api.php grup auth login, app/Http/Controllers/Auth/SessionController.php@store, app/Models/Staff.php@isAdmin, tests/Feature/AdminAccessTest.php, resources/js/pages/Auth/Login.tsx.
- Acceptance Criteria:
  - Given staf admin aktif, When password benar, Then HTTP 200 dan body user berisi role ADMIN.
  - Given staf nonaktif atau berperan COURIER, When password benar, Then HTTP 422 dengan pesan penolakan dan tidak ada sesi terbentuk.
- Status: Live

---

### FR-AUTH-002 — Logout sesi admin

- Deskripsi: Sistem HARUS mengakhiri sesi, membatalkan sesi, dan meregenerasi token CSRF saat admin keluar.
- Aktor & Otorisasi: staf yang sedang memiliki sesi. Endpoint tetap menjawab 200 walau sesi sudah tidak ada.
- Trigger: POST /api/auth/logout dari tombol keluar konsol.
- Precondition: permintaan datang dari klien yang memegang cookie sesi dan token CSRF.
- Input & Validasi: tanpa body.
- Proses / Business Rules: Auth::logout, session invalidate, session regenerateToken.
- Output / Response: HTTP 200 dengan message "Logout berhasil.".
- Error & Edge Cases: permintaan tanpa token CSRF ditolak middleware ValidateCsrfToken yang dipasang pada kelompok api di bootstrap/app.php.
- Side Effects: sesi lama tidak dapat dipakai lagi.
- Postcondition: permintaan ke halaman admin diarahkan ke halaman login.
- Traceability: routes/api.php, SessionController@destroy, bootstrap/app.php, tests/Feature/AdminConsoleWiringTest.php.
- Acceptance Criteria:
  - Given admin yang login, When logout dipanggil dengan token CSRF sah, Then HTTP 200 dan permintaan berikutnya ke /api/admin/* mendapat 401.
  - Given permintaan logout tanpa token CSRF, When dikirim, Then ditolak 419 dan sesi tetap hidup.
- Status: Live

---

### FR-AUTH-003 — Profil sesi admin

- Deskripsi: Sistem HARUS mengembalikan identitas staf yang sedang login agar konsol dapat menampilkan nama dan perannya.
- Aktor & Otorisasi: staf dengan sesi. Pengunjung menerima 401.
- Trigger: GET /api/auth/me saat halaman konsol dimuat.
- Precondition: sesi valid untuk user bertipe Staff.
- Input & Validasi: tanpa input.
- Proses / Business Rules: user diambil dari request. Bila bukan Staff, kembalikan 401 dengan code UNAUTHENTICATED.
- Output / Response: HTTP 200 dengan objek user berisi id, name, email, role, phone.
- Error & Edge Cases: pengunjung atau sesi kedaluwarsa menghasilkan HTTP 401 dengan message "Unauthenticated.".
- Side Effects: tidak ada.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, SessionController@me, tests/Feature/AdminAccessTest.php.
- Acceptance Criteria:
  - Given admin login, When /api/auth/me dipanggil, Then HTTP 200 dan role bernilai ADMIN.
  - Given tanpa sesi, When /api/auth/me dipanggil, Then HTTP 401 dengan code UNAUTHENTICATED.
- Status: Live

---

### FR-AUTH-004 — Login kurir berbasis token

- Deskripsi: Sistem HARUS menukar email dan password staf COURIER aktif menjadi bearer token Sanctum ber-ability courier dengan masa berlaku 30 hari.
- Aktor & Otorisasi: kurir. Endpoint publik dengan rate limit courier-auth.
- Trigger: POST /api/courier/auth/login dari layar Login aplikasi kurir.
- Precondition: baris staff dengan email tersebut ada dan kolom password terisi.
- Input & Validasi: email wajib format email; password wajib. Validasi inline di controller, bukan FormRequest.
- Proses / Business Rules: password dicocokkan Hash::check. Role harus COURIER, selain itu 403 FORBIDDEN. is_active harus true, selain itu 403 ACCOUNT_INACTIVE. Token dibuat dengan nama courier-mobile, ability courier, dan expires_at 30 hari ke depan.
- Output / Response: HTTP 200 dengan data berisi token, token_type Bearer, expires_at ISO8601, dan courier yang memuat id, name, email, phone, status.
- Error & Edge Cases: kredensial salah menghasilkan 401 INVALID_CREDENTIALS. Admin yang mencoba masuk menghasilkan 403 FORBIDDEN. Lima percobaan per menit per kombinasi email dan IP menghasilkan 429 TOO_MANY_REQUESTS.
- Side Effects: satu baris baru di personal_access_tokens.
- Postcondition: klien memegang token dan setiap permintaan /api/courier/* wajib menyertakannya.
- Traceability: routes/api.php, app/Http/Controllers/Api/Courier/AuthController.php@login, AppServiceProvider rate limiter courier-auth, tests/Feature/CourierApiAuthTest.php, app/NativeComponents/Courier/Login.php.
- Acceptance Criteria:
  - Given kurir aktif, When email dan password benar, Then HTTP 200 dengan token ber-ability courier.
  - Given akun berperan ADMIN, When login kurir dipanggil, Then HTTP 403 FORBIDDEN dan tidak ada token dibuat.
- Status: Live

---

### FR-AUTH-005 — Logout kurir

- Deskripsi: Sistem HARUS mencabut HANYA token yang sedang dipakai kurir.
- Aktor & Otorisasi: kurir terautentikasi melalui middleware AuthenticateCourier, abilities:courier, staff.role:COURIER.
- Trigger: POST /api/courier/auth/logout dari tombol keluar layar profil.
- Precondition: permintaan membawa bearer token valid yang belum kedaluwarsa.
- Input & Validasi: tanpa body.
- Proses / Business Rules: token aktif dihapus dari personal_access_tokens. Token lain milik kurir yang sama tidak terhapus.
- Output / Response: HTTP 200 dengan message "Berhasil logout." dan code LOGGED_OUT.
- Error & Edge Cases: token yang sudah dicabut atau lewat masa berlaku menghasilkan 401 karena AuthenticateCourier menolak token tanpa tokenable atau yang kedaluwarsa.
- Side Effects: baris token terhapus. Layar native membersihkan penyimpanan token lokal.
- Postcondition: permintaan /api/courier/* berikutnya gagal 401 sampai login ulang.
- Traceability: routes/api.php, Api/Courier/AuthController.php@logout, Middleware/AuthenticateCourier.php, tests/Feature/CourierApiAuthTest.php test_logout_revokes_the_token, app/NativeComponents/Courier/Profile.php.
- Acceptance Criteria:
  - Given kurir login, When logout dipanggil dengan token itu, Then HTTP 200 dan permintaan tugas berikutnya 401.
  - Given token yang sama dipakai lagi, When logout dipanggil kedua kali, Then HTTP 401 UNAUTHENTICATED.
- Status: Live

---

### FR-AUTH-006 — Registrasi pelanggan

- Deskripsi: Sistem HARUS membuat akun pembeli dari nomor HP dan password pilihan pelanggan tanpa OTP, lalu mengembalikan bearer token ber-ability client.
- Aktor & Otorisasi: publik, dengan middleware force.json, client.headers, dan throttle client-auth.
- Trigger: POST /api/client/auth/register dari layar Register aplikasi pelanggan.
- Precondition: nomor HP belum terdaftar.
- Input & Validasi: name 2 sampai 120 karakter; phone wajib maksimal 20 karakter dan unik di tabel buyers setelah normalisasi E.164; password minimal 8 karakter dan harus dikonfirmasi; address 5 sampai 255 karakter; area opsional maksimal 120; address_type opsional harus nilai AddressType yang sah. prepareForValidation menormalkan nomor dan menerima label Indonesia seperti "Rumah" atau "Kantor".
- Proses / Business Rules: Buyer::create dengan is_active true. Password di-hash otomatis oleh cast model. Token dibuat dengan nama client-mobile, ability client, dan expires_at 30 hari.
- Output / Response: HTTP 201 dengan message "Akun berhasil dibuat." dan data berisi token, token_type, expires_at, dan buyer.
- Error & Edge Cases: nomor sudah terdaftar menghasilkan 422 dengan pesan "Nomor HP ini sudah terdaftar. Silakan masuk.". Password tidak cocok menghasilkan 422 dengan pesan "Konfirmasi password tidak sama.".
- Side Effects: satu baris buyers baru dan satu baris personal_access_tokens baru.
- Postcondition: pelanggan dapat langsung melanjutkan checkout.
- Traceability: routes/api.php, Api/Client/AuthController.php@register, Requests/Api/Client/RegisterRequest.php, Resources/Client/BuyerResource.php, tests/Feature/ClientStorefrontTest.php, app/NativeComponents/Client/Register.php.
- Acceptance Criteria:
  - Given nomor baru dan password sah, When register dipanggil, Then HTTP 201 dengan token dan baris buyers terbuat.
  - Given nomor yang sudah dipakai akun lain, When register dipanggil, Then HTTP 422 pada errors.phone dan tidak ada baris baru.
- Status: Live

---

### FR-AUTH-007 — Login pelanggan

- Deskripsi: Sistem HARUS mengautentikasi pembeli memakai nomor HP dan password, dengan nomor dinormalkan lebih dulu sehingga format 08xx dan +62xx sama-sama cocok.
- Aktor & Otorisasi: publik, dengan force.json, client.headers, dan throttle client-auth.
- Trigger: POST /api/client/auth/login dari layar Login aplikasi pelanggan.
- Precondition: baris buyers dengan nomor tersebut ada dan kolom password terisi.
- Input & Validasi: phone wajib string; password wajib string.
- Proses / Business Rules: nomor dicari dalam bentuk E.164 lalu bentuk asli sebagai cadangan. Password dicocokkan Hash::check. is_active harus true. Token baru dibuat dengan ability client dan masa berlaku 30 hari.
- Output / Response: HTTP 200 dengan data berisi token, token_type, expires_at, dan buyer.
- Error & Edge Cases: pembeli tidak ada atau password salah sama-sama menghasilkan 401 INVALID_CREDENTIALS dengan pesan generik sehingga tidak membocorkan apakah nomor terdaftar. Akun nonaktif menghasilkan 403 ACCOUNT_INACTIVE. Lima percobaan per menit per kombinasi phone dan IP menghasilkan 429.
- Side Effects: satu baris personal_access_tokens baru.
- Postcondition: klien memegang token ability client.
- Traceability: routes/api.php, Api/Client/AuthController.php@login, Requests/Api/Client/LoginRequest.php, Support/PhoneFormatter.php, tests/Feature/ClientApiAuthTest.php, app/NativeComponents/Client/Login.php.
- Acceptance Criteria:
  - Given pembeli aktif, When nomor dan password benar, Then HTTP 200 dengan token ability client.
  - Given nomor benar tetapi password salah, When login dipanggil, Then HTTP 401 dengan pesan yang identik dengan kasus nomor tidak terdaftar.
- Status: Live

---

### FR-AUTH-008 — Logout pelanggan

- Deskripsi: Sistem HARUS mencabut token klien yang sedang dipakai.
- Aktor & Otorisasi: buyer melalui auth:sanctum, abilities:client, client.buyer.
- Trigger: POST /api/client/auth/logout dari layar profil.
- Precondition: token aktif milik pembeli.
- Input & Validasi: tanpa body.
- Proses / Business Rules: currentAccessToken dihapus bila ada.
- Output / Response: HTTP 200 dengan message "Berhasil logout." dan code LOGGED_OUT.
- Error & Edge Cases: tanpa token menghasilkan 401 UNAUTHENTICATED lewat render AuthenticationException di bootstrap/app.php.
- Side Effects: baris token terhapus dan penyimpanan token di perangkat dibersihkan.
- Postcondition: permintaan terautentikasi berikutnya gagal 401.
- Traceability: routes/api.php, Api/Client/AuthController.php@logout, tests/Feature/ClientApiAuthTest.php test_buyer_can_logout_and_revokes_token, app/NativeComponents/Client/Profile.php.
- Acceptance Criteria:
  - Given pembeli login, When logout dipanggil, Then HTTP 200 dan /api/client/me berikutnya 401.
  - Given tanpa token, When logout dipanggil, Then HTTP 401 UNAUTHENTICATED.
- Status: Live

---

### FR-AUTH-009 — Profil pelanggan

- Deskripsi: Sistem HARUS mengembalikan identitas pembeli beserta jumlah pesanan aktif, jumlah selesai, dan kanal dukungan yang dapat dihubungi.
- Aktor & Otorisasi: buyer terautentikasi dengan ability client.
- Trigger: GET /api/client/me saat tab profil atau langkah checkout dibuka.
- Precondition: token ability client valid dan pembeli aktif.
- Input & Validasi: tanpa input.
- Proses / Business Rules: pesanan aktif dihitung sebagai paket yang bukan RECEIVED, RETURNED, atau CLOSED; pesanan selesai adalah RECEIVED atau CLOSED. Nomor dukungan diambil dari config coldchain.customer_service dan disusun menjadi tautan wa.me dengan pesan siap kirim. Angka on_time_rate dikirim sebagai konstanta 96 persen, bukan hasil hitung.
- Output / Response: HTTP 200 dengan data berisi name, phone, address, area, stats (active_count, completed_count, on_time_rate), dan support (whatsapp_url, email).
- Error & Edge Cases: pembeli nonaktif ditolak 403 ACCOUNT_INACTIVE oleh middleware client.buyer. Token ber-ability courier ditolak 403 FORBIDDEN oleh CheckAbilities.
- Side Effects: tidak ada penulisan.
- Postcondition: tidak ada perubahan state.
- Traceability: routes/api.php, Api/Client/ProfileController@show, Resources/Client/BuyerProfileResource.php, config/coldchain.php customer_service, tests/Feature/ClientApiAuthTest.php, app/NativeComponents/Client/Profile.php.
- Acceptance Criteria:
  - Given pembeli dengan tiga paket non-terminal dan dua paket selesai, When profil diminta, Then stats berisi angka 3 dan 2 serta support.whatsapp_url memuat nomor konfigurasi.
  - Given token ber-ability courier dipakai, When profil pembeli diminta, Then HTTP 403 FORBIDDEN.
- Status: Live

---

### FR-AUTH-010 — Klaim tautan menjadi akun

- Deskripsi: Sistem HARUS mengubah pembeli yang datang lewat tautan token menjadi akun berpassword, dan HARUS menolak klaim kedua pada akun yang sudah memiliki password.
- Aktor & Otorisasi: guest pemegang tautan valid. Middleware client.package menyelesaikan token menjadi paket sebelum controller berjalan.
- Trigger: POST /api/guest/{token}/claim, dan jalur legacy POST /api/client/{token}/claim.
- Precondition: paket dengan client_token tersebut ada dan pembelinya belum memiliki password.
- Input & Validasi: password wajib minimal 8 karakter dan harus dikonfirmasi (ClaimAccountRequest).
- Proses / Business Rules: bila atribut client_package tidak ada, paket dicari ulang dari client_token. Password diset pada pembeli, phone_verified_at diisi waktu sekarang, lalu token Sanctum baru diterbitkan ber-ability client dan berlaku 30 hari.
- Output / Response: HTTP 200 dengan message "Akun berhasil diklaim dan diaktifkan." dan data berisi token, token_type, expires_at, buyer.
- Error & Edge Cases: token tidak valid menghasilkan 404 LINK_INVALID. Pembeli tidak ditemukan menghasilkan 404 BUYER_NOT_FOUND. Akun yang sudah punya password menghasilkan 409 ACCOUNT_ALREADY_CLAIMED dengan saran login langsung.
- Side Effects: password dan phone_verified_at pembeli berubah dan token baru dibuat.
- Postcondition: pelanggan dapat memakai aplikasi dengan akun penuh dan tautan lamanya tetap berfungsi.
- Traceability: routes/api.php, Api/Client/AuthController@claim, Requests/Api/Client/ClaimAccountRequest.php, tests/Feature/ClientApiAuthTest.php test_guest_account_claim_sets_password_and_second_claim_returns_409.
- Acceptance Criteria:
  - Given paket dengan pembeli tanpa password, When claim dengan password sah, Then HTTP 200 dengan token baru dan phone_verified_at terisi.
  - Given akun yang sudah punya password, When claim dipanggil lagi, Then HTTP 409 ACCOUNT_ALREADY_CLAIMED dan password lama tidak berubah.
- Status: Live

