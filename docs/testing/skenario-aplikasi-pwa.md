# Pengujian Aplikasi PWA (Anteraja Frozen & Anteraja Kurir)

Skenario pengujian kedua aplikasi web terpasang — `/client` untuk pelanggan dan
`/courier` untuk kurir — beserta prosedur mengulangnya dan prioritas tiap
skenario.

Prioritas: **P0** menghalangi pemakaian sehari-hari, **P1** penting tetapi ada
jalan lain, **P2** penyempurnaan.

## 1. Prosedur

1. Jalankan backend: `php artisan serve` (geolokasi dan kamera bekerja di
   `localhost`; host lain harus HTTPS).
2. Siapkan data uji: `php artisan migrate:fresh --seed`.
3. Uji otomatis yang relevan:

   ```bash
   php artisan test --filter=PwaShellTest     # shell, manifest, service worker, ikon
   php artisan test                           # seluruh suite, 201 test
   bun run lint && bun run typecheck && bun run build
   ```

4. Reset ke keadaan pemasangan baru: hapus data situs untuk origin ini
   (Chrome: Pengaturan situs → Hapus data; Safari: Pengaturan → Lanjutan →
   Hapus data situs). Ini yang membersihkan token, keranjang, dan cache service
   worker sekaligus — menutup tab saja tidak cukup.
5. Pasang aplikasinya: buka `/client` atau `/courier`, lalu pilih “Pasang
   aplikasi” dari menu browser (iPhone: Bagikan → Tambahkan ke Layar Utama).
6. Untuk memisahkan masalah backend dari masalah tampilan, pakai curl seperti
   biasa (endpoint tidak berubah):

   ```bash
   curl -s -X POST http://localhost:8000/api/client/auth/login \
     -H "Accept: application/json" -H "Content-Type: application/json" \
     -d '{"phone":"+6281298765432","password":"password"}'

   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/client/me \
     -H "Accept: application/json"
   ```

Akun demo: pelanggan `+6281298765432` / `password`; kurir `budi@anteraja.id` /
`password`; admin `dimas@anteraja.id` / `password`.

## 2. Skenario dan Prioritas

### Aplikasi pelanggan (`/client`)

| ID | Pri | Skenario | Hasil yang diharapkan | Status |
|---|---|---|---|---|
| SC-01 | P0 | Pemasangan baru, buka ikon dari layar utama | Berakhir di Beranda (belum masuk) dengan alamat `/client/landing` | Belum diuji di perangkat |
| SC-02 | P0 | Isi nomor dan password yang benar, tekan Masuk sekali | Sekali tekan masuk ke tab Pesanan | Belum diuji di perangkat |
| SC-03 | P0 | Keluar dari tab Profil | Kembali ke halaman masuk | Belum diuji di perangkat |
| SC-04 | P0 | Token dicabut di server, lalu buka tab Pesanan | Diarahkan ke halaman masuk dengan `next` ke Pesanan | Belum diuji di perangkat |
| SC-05 | P1 | Tutup lalu buka ulang aplikasi | Sesi bertahan lewat localStorage, langsung ke Pesanan | Belum diuji di perangkat |
| SC-06 | P1 | Masuk dengan password salah | Pesan kesalahan tampil, tetap di halaman masuk | Belum diuji di perangkat |
| SC-07 | P1 | Belanja tanpa akun sampai Checkout | Diminta mendaftar dulu, lalu lanjut checkout | Belum diuji di perangkat |
| SC-08 | P1 | Geser pin ke alamat penerima di Checkout | Koordinat muncul dan pesanan dapat dibuat | Belum diuji di perangkat |
| SC-09 | P1 | Konfirmasi jadwal dari aplikasi | Jadwal terkonfirmasi dan tombolnya terkunci | Belum diuji di perangkat |
| SC-10 | P2 | Ajukan jadwal lain | Permintaan tercatat dan status berubah | Belum diuji di perangkat |
| SC-11 | P2 | Baca kendala lalu ajukan kirim ulang | Resi pengganti muncul dan lanjut ke jadwalnya | Belum diuji di perangkat |
| SC-12 | P2 | Tambah produk, keluar, buka lagi | Keranjang bertahan dan lencana di bilah atas cocok | Belum diuji di perangkat |

### Aplikasi kurir (`/courier`)

| ID | Pri | Skenario | Hasil yang diharapkan | Status |
|---|---|---|---|---|
| SC-20 | P0 | Masuk dengan akun kurir | Sampai di tab Tugas | Belum diuji di perangkat |
| SC-21 | P0 | Terima tugas | Tombol terkunci dan tautan detail pengantaran muncul | Belum diuji di perangkat |
| SC-22 | P0 | Berangkat, lalu tiba di lokasi | Aksi terkirim beserta jendela fix GPS | Belum diuji di perangkat |
| SC-23 | P0 | Serah terima dengan foto | Foto wajib, unggahan berhasil, pengiriman selesai | Belum diuji di perangkat |
| SC-24 | P0 | Serah terima jauh dari titik penerima | Ditolak dengan alasan pertama dan tombol minta tinjauan hub | Belum diuji di perangkat |
| SC-25 | P1 | Laporkan kendala dengan foto | Laporan terkirim, tugas ditandai kendala | Belum diuji di perangkat |
| SC-26 | P1 | Laporkan kendala suhu naik | Kolom suhu wajib muncul dan terkirim | Belum diuji di perangkat |
| SC-27 | P1 | Tolak tugas | Tugas ditolak dan toast konfirmasi tampil | Belum diuji di perangkat |
| SC-28 | P2 | Buka peta lokasi | Titik penerima, radius, dan posisi sendiri terlihat | Belum diuji di perangkat |

### Lintas aplikasi

| ID | Pri | Skenario | Hasil yang diharapkan | Status |
|---|---|---|---|---|
| SC-30 | P0 | Pasang kedua aplikasi dari origin yang sama | Dua ikon terpisah (`Anteraja Frozen`, `Anteraja Kurir`) | Belum diuji di perangkat |
| SC-31 | P0 | Buka aplikasi saat perangkat luring | Halaman luring tampil; data tidak pernah disajikan dari cache | Belum diuji di perangkat |
| SC-32 | P1 | Buka tautan dalam, mis. `/courier/delivery/AJ-FRZ-0012345` | Aplikasi yang benar membuka layar itu | Belum diuji di perangkat |
| SC-33 | P1 | Perangkat di mode gelap | Seluruh layar dan bilah status tetap terang | Belum diuji di perangkat |
| SC-34 | P2 | Buka aplikasi dari tautan landing page | Shell aplikasi terbuka, bukan halaman Inertia | Belum diuji di perangkat |

## 3. Catatan pemasangan

- Manifest per aplikasi disajikan pada `/client.webmanifest` dan
  `/courier.webmanifest`; keduanya sengaja berbeda `id`, `scope`, dan
  `start_url` sehingga satu origin dapat memasang dua aplikasi.
- iOS hanya mendukung pemasangan manual (Bagikan → Tambahkan ke Layar Utama) dan
  tidak memunculkan `beforeinstallprompt`; kartu pemasangan menampilkan
  instruksi itu sebagai gantinya.
- Service worker hanya mendaftar pada build produksi (`bun run build` lalu
  sajikan `public/build`), jadi cache basi tidak pernah menyergap saat
  pengembangan.
- Geolokasi dan kamera memerlukan HTTPS atau `localhost`.

## 4. Yang sudah diverifikasi lokal

Pemeriksaan otomatis di mesin pengembang (headless Chrome terhadap
`php artisan serve` + database hasil seed, 2026-10-15) menutup jalur-jalur ini:

- SC-01, SC-02, SC-05, SC-06 lewat alur masuk sungguhan kedua realm; token
  bertahan setelah muat ulang.
- SC-04 lewat pencabutan token: permintaan berikutnya mengembalikan pengguna ke
  halaman masuk dengan parameter `next`.
- SC-07 dan SC-32 lewat gerbang rute: /courier/tasks, /client/orders,
/courier/delivery/{resi} tanpa token semuanya mendarat di halaman masuk realm
  masing-masing, dan /client/checkout mendarat di pendaftaran.
- SC-08 sebagian: daftar Pesanan, tab Riwayat, profil, daftar tugas kurir, riwayat
  kurir, dan checkout kosong semuanya dirender dari data API sungguhan.
- SC-12 lewat penambahan produk ke keranjang: kunci `anteraja.client.cart`
  terisi dan checkout membaca keranjang itu.
- SC-30 sebagian dan SC-34: kedua realm dapat masuk berdampingan di satu
  peramban tanpa saling menendang, dan kartu aplikasi di landing page membuka
  shell aplikasi (bukan halaman Inertia).
- Manifest, service worker, halaman luring, dan ikon kedua aplikasi disajikan
  dengan benar lewat HTTP (PwaShellTest.php mengunci ini di suite).

Sisanya — pemasangan di layar utama, jalur luring sungguhan, izin kamera dan
geolokasi, serta tampilan di perangkat — masih memerlukan pengujian manual di
pons seperti prosedur di atas.

## 5. Riwayat temuan dari shell native

Dokumen ini menggantikan `skenario-aplikasi-client.md` yang menguji shell
Android. Tiga temuan di sana sudah tidak berlaku lagi karena layarnya ditulis
ulang sebagai SPA:

- **F-04 (tekan dua kali pada formulir).** Terjadi karena pengikatan
  `native:model.blur` mengirim nilai ke PHP secara asinkron. Formulir web
  memakai state React, jadi nilai selalu terbaca saat ditekan.
- **F-02 (tombol Keluar hilang saat pemuatan gagal).** Layar Profil sekarang
  merender tombol Keluar di luar cabang galat.
- **F-03 (401 tidak dikenali).** `lib/api.ts` menghapus token dan memancarkan
  `anteraja:unauthorized`, dan aplikasi mengarahkan ke halaman masuk dengan
  parameter `next`.

Skenario SC-01…SC-15 versi lama dipetakan ke tabel di atas; SC-14 (pemilih tema)
dihapus karena kedua aplikasi memang hanya mode terang.
