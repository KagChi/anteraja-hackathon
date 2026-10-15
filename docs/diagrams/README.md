# Diagram D2 - Anteraja Frozen

Folder ini memuat sumber diagram dalam bahasa D2 beserta hasil rendernya. Isinya cermin dari `docs/flowchart.md`, jadi kalau alur berubah, ubah keduanya.

## Berkas

| Berkas | Isi |
|---|---|
| `alur-lengkap.d2` | Seluruh alur, good case dan edge case, dalam satu diagram berisi 51 node |
| `fsm-status.d2` | Peta transisi status paket, mengikuti tabel `package_status_transitions` |
| `render.sh` | Merender setiap `.d2` menjadi SVG, PNG, dan teks ASCII |
| `lihat.sh` | Membuka hasil render dari terminal WSL |

Berkas hasil render ditimpa ulang setiap kali `render.sh` dijalankan, jadi jangan diedit manual: `alur-lengkap.svg`, `alur-lengkap.png`, `alur-lengkap.txt`, `fsm-status.svg`, `fsm-status.png`, `fsm-status.txt`.

## Render ulang

```bash
bash docs/diagrams/render.sh
```

Layout yang dipakai: `dagre` untuk SVG dan PNG karena hasilnya paling enak dilihat, `tala` untuk teks karena menghasilkan lebar terkecil.

## Melihat hasilnya

### Buka gambarnya, satu perintah dari terminal

```bash
bash docs/diagrams/lihat.sh              # alur-lengkap.svg
bash docs/diagrams/lihat.sh fsm-status   # fsm-status.svg
bash docs/diagrams/lihat.sh alur-lengkap.png
```

Skrip memanggil `explorer.exe` lewat `wslpath`, sehingga gambarnya terbuka di aplikasi Windows. `alur-lengkap.png` berukuran 13626 x 5836 piksel, jadi setelah terbuka pilih fit to window atau zoom out dulu.

### Versi teks di terminal

```bash
bash docs/diagrams/lihat.sh -t fsm-status   # tampilkan sekaligus menyebut lebarnya
less -S docs/diagrams/alur-lengkap.txt      # geser horizontal kalau terminal sempit
```

| Berkas teks | Lebar | Baris |
|---|---|---|
| `fsm-status.txt` | 275 kolom | 68 |
| `alur-lengkap.txt` | 362 kolom | 189 |

Lebar keluaran teks mengikuti bentuk diagram, bukan lebar terminal. Di terminal 80 sampai 120 kolom, pakai `less -S` atau perlebar jendelanya.

### Gambar berwarna langsung di dalam terminal

Belum ada penampil gambar di WSL ini. Kalau dibutuhkan:

```bash
sudo apt install chafa
chafa --size 160x70 docs/diagrams/alur-lengkap.png
```

## Mengedit diagram

Yang diedit adalah berkas `.d2`-nya, bukan hasil rendernya. Semua berkas `.svg`, `.png`, dan `.txt` ditimpa ulang setiap kali render dijalankan, jadi perubahan yang dibuat langsung di sana akan hilang.

```bash
# ubah berkas sumbernya, misalnya menambah node atau mengubah label
$EDITOR docs/diagrams/alur-lengkap.d2

# lalu hasilkan ulang gambar dan teksnya
bash docs/diagrams/render.sh
```

Jalur ini paling murah karena posisi semua node dihitung ulang oleh layout engine, sehingga panah tidak pernah perlu diperbaiki manual.

Kalau lebih nyaman memakai aplikasi gambar, pilihannya bergantung pada seberapa rela Anda merapikan panah sendiri:

| Cara | Bisa diedit? | Catatan |
|---|---|---|
| Berkas `.d2` lalu render ulang | ya, sepenuhnya | Panah menyesuaikan sendiri, ini jalur yang disarankan |
| `.svg` di Inkscape, Figma, atau Illustrator | ya, vektor | Kotak dan teks bisa digeser, tetapi panah menjadi jalur lepas yang harus dirapikan manual |
| `.pdf` di Illustrator | ya, vektor | Sama seperti SVG |
| `.svg` di PowerPoint, lalu Convert to Shape | ya, vektor | Jalan pintas kalau hasilnya langsung untuk slide |
| `.pptx` hasil d2 | tidak | Sudah diperiksa: slide-nya hanya berisi satu PNG di `ppt/media/slide1Image.png`, tanpa shape, tanpa teks, tanpa konektor |
| Mermaid di draw.io | ya, kotak asli | Impor `docs/flowchart.md` lewat Arrange, Insert, Advanced, Mermaid. Panah menempel pada kotak sehingga memindahkan node tidak merusak garis |

Ringkasnya: mengedit lewat berkas teks itu mudah, sedangkan mengedit lewat aplikasi gambar tetap bisa tetapi panahnya menjadi pekerjaan manual, kecuali lewat draw.io.

## Catatan pemeliharaan

- Sumber kebenaran alur tetap `docs/flowchart.md` dan matriks FSM di `database/seeders/ReferenceDataSeeder.php`. Berkas `.d2` di folder ini adalah turunannya.
- Setiap node di `alur-lengkap.d2` wajib punya `class` (`pembeli`, `admin`, `kurir`, `sistem`, `status`, `keputusan`) supaya warnanya konsisten.
- Container `fase1` sampai `fase7` dipakai sebagai pengelompokan visual. Menambah fase berarti menambah container baru di akar berkas.
