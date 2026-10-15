# Pengujian Aplikasi Client (Anteraja Frozen)

Skenario pengujian shell Android `com.anteraja.frozen`, beserta prosedur mengulangnya dan prioritas tiap skenario.

Prioritas: **P0** menghalangi pemakaian sehari-hari, **P1** penting tetapi ada jalan lain, **P2** penyempurnaan.

## 1. Prosedur

1. Bangun dan pasang aplikasinya: `bash scripts/build-apk.sh client`
2. Reset ke kondisi instalasi baru: Pengaturan Android, Aplikasi, Anteraja Frozen, Hapus data. Menutup aplikasi saja tidak cukup karena token disimpan di keychain perangkat.
3. Siapkan data uji: `php artisan migrate:fresh --seed`, lalu masuk memakai pembeli demo `+6281298765432` dengan password `password123`.
4. Jalankan uji otomatis: `php artisan test --filter=ClientNativeScreensTest`. Butuh PostgreSQL `anteraja_frozen` hidup.
5. Untuk bug masukan yang peka urutan waktu, ulangi dengan cara ini: isi kolom nomor, isi kolom password, lalu langsung tekan Masuk tanpa memindahkan fokus ke kolom lain. Kalau fokus sempat berpindah lebih dulu, nilainya sudah tersinkron dan gejalanya hilang.
6. Pisahkan masalah backend dari masalah tampilan dengan curl:

```bash
# masuk, ambil token dari respons
curl -s -X POST http://localhost:8000/api/client/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone":"+6281298765432","password":"password123"}'

# tanpa token harus 401
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/client/me -H "Accept: application/json"
```

## 2. Skenario dan Prioritas

| ID | Pri | Skenario | Hasil yang diharapkan | Status |
|---|---|---|---|---|
| SC-01 | P0 | Instalasi baru, lalu buka aplikasi | Berakhir di layar Beranda | Sesuai desain, perlu keputusan |
| SC-02 | P0 | Isi nomor dan password yang benar, tekan Masuk sekali | Sekali tekan langsung masuk ke tab Pesanan | Gagal, lihat F-04 |
| SC-03 | P0 | Keluar dari tab Profil saat data profil termuat | Kembali ke layar masuk | Belum diuji |
| SC-04 | P0 | Keluar saat pemuatan data profil gagal | Tombol Keluar tetap tersedia | Gagal, lihat F-02 |
| SC-05 | P0 | Token kedaluwarsa saat aplikasi dipakai | Diarahkan ke layar masuk | Gagal, lihat F-03 |
| SC-06 | P1 | Tutup lalu buka ulang aplikasi | Sesi bertahan, langsung ke tab Pesanan | Belum diuji |
| SC-07 | P1 | Masuk dengan password salah | Pesan kesalahan, tetap di layar masuk | Belum diuji |
| SC-08 | P1 | Belum masuk lalu membuka tab Profil | Ada jalan masuk atau keluar | Belum diuji, berisiko kena F-02 |
| SC-09 | P1 | Belanja tanpa akun sampai halaman checkout | Diminta mendaftar sebelum pesanan dibuat | Belum diuji |
| SC-10 | P1 | Buka tautan WhatsApp ke sebuah paket tanpa login | Rincian paket tampil | Belum diuji |
| SC-11 | P1 | Konfirmasi jadwal pengantaran dari aplikasi | Jadwal terkonfirmasi dan tidak bisa diubah lagi | Belum diuji |
| SC-12 | P2 | Ajukan jadwal lain | Permintaan tercatat untuk admin | Belum diuji |
| SC-13 | P2 | Baca kendala lalu ajukan kirim ulang | Keputusan tampil dan resi pengganti dibuat | Belum diuji |
| SC-14 | P2 | Ganti tema aplikasi | Pilihan menempel setelah aplikasi dibuka ulang | Belum diuji |
| SC-15 | P2 | Klaim akun dari tautan lalu tetapkan password | Password tersimpan dan bisa dipakai masuk | Belum diuji |

### SC-02 rincian

Langkah: buka layar masuk, isi nomor dan password yang benar, tekan Masuk.

Kenyataan: tekan pertama menampilkan pesan wajib isi. Tekan kedua baru berhasil dan masuk ke tab Pesanan.

## 3. Temuan

Cacat:

- **F-04, P0.** Kolom di layar masuk diikat dengan `native:model.blur` pada `resources/views/native/client/login.blade.php:19` dan `:27`, sehingga nilai baru dikirim ke PHP saat kolom kehilangan fokus. Sentuhan pada tombol Masuk bisa diproses sebelum sinkronisasi itu tiba, jadi `submit()` membaca kolom yang masih kosong dan menampilkan pesan wajib isi, lalu tekan kedua berhasil karena nilainya sudah tersinkron. Perbaikannya menghapus `.blur`, karena default tanpa modifier adalah `live` setiap ketikan, atau memakai `native:model.debounce.150ms` kalau ingin lebih hemat lalu lintas jembatan.
- **F-02, P0.** Tombol Keluar ada di `resources/views/native/client/profile.blade.php:73`, tetapi berada di dalam cabang `@else` pada baris 11, sementara seluruh isi layar dibungkus `@if ($error !== null)` pada baris 9. Begitu data profil gagal dimuat, tombol itu ikut hilang.
- **F-03, P0.** Tidak ada layar client yang mengenali 401 sebagai sesi berakhir. `App\Support\MobileApi` melipat semua kegagalan jadi satu bentuk yang sama, dan `MobileToken::clear()` hanya dipanggil dari aksi keluar milik pengguna. Token mati tidak pernah dibuang, sehingga SC-05 dan SC-04 saling menguatkan.

Keputusan:

- **F-01.** Pintu masuk di `app/NativeComponents/Client/Start.php:18` sengaja mengirim pengunjung tanpa token ke layar toko, bukan layar masuk, dan perilaku itu dikunci uji di `tests/Feature/ClientNativeScreensTest.php:53`, `:429`, `:441`. Pertahankan atau ubah, keduanya perlu keputusan.

Dampak F-04 lebih luas dari layar masuk. Pola `native:model.blur` yang sama dipakai 23 kolom di 8 layar, termasuk pendaftaran, checkout, bukti serah terima kurir, dan login kurir, jadi bug tekan dua kali berpotensi muncul di semua formulir itu.

Cakupan ujinya juga belum menjangkau jalur ini. Uji `test_the_login_screen_stores_the_token_and_enters_the_app` pada `tests/Feature/ClientNativeScreensTest.php:412` menulis nilai langsung lewat `->set('phone', ...)` sebelum memanggil `submit`, sehingga sinkronisasi dari kolom native ke PHP tidak pernah teruji.

## 4. Cara Membaca Status

SC-01 dan SC-02 berasal dari laporan pengujian di perangkat, sedangkan SC-04 dan SC-05 dari pembacaan kode. Skenario lainnya belum dijalankan karena sesi ini tidak memakai emulator, dan hasil yang diharapkan di tabel baru diturunkan dari kode. Perbaikan F-02 dan F-04 berada di `resources/views/`, area frontend yang dilarang disentuh `AGENTS.md` bagian 1.1, jadi dokumen ini hanya mengusulkannya. F-03 berada di `app/`, area backend.

## 5. Status perbaikan

F-04 sudah diperbaiki atas izin pemilik repo: `native:model.blur` dihapus dari 24
kolom di 6 layar — checkout pelanggan 9, pendaftaran 7, masuk pelanggan 2, masuk
kurir 2, laporan kendala kurir 2, bukti serah terima kurir 2 — sehingga pengikatan
kembali ke mode `live` bawaan NativePHP. Diverifikasi in-process: pohon layar
checkout kini membawa sembilan `sync_mode: live` dan tidak ada lagi `blur`. SC-02
masih perlu diulang di perangkat untuk menutup temuannya.

F-02 dan F-03 belum diperbaiki.

Catatan lingkungan: APK NativePHP hanya memuat ABI `arm64-v8a`
(`abiFilters.add("arm64-v8a")` pada template Gradle di paket Mobile), jadi
pengujian perangkat harus memakai HP fisik Android 13 ke atas (minSdk 33);
emulator x86 di mesin pengembang tidak dapat memasang paket itu.
