# Diagram Arsitektur Anteraja Frozen

Folder ini memuat diagram arsitektur dan tech stack dalam format Mermaid beserta
hasil rendernya sebagai PNG. Sumbernya adalah keadaan repositori ini sendiri:
`composer.json`, `package.json`, `Dockerfile`, `deploy/`, `routes/`,
`database/migrations/`, dan `.github/workflows/`.

Diagram alur bisnis dan FSM tidak ada di sini, tempatnya di `docs/diagrams/`
dan `docs/flowchart.md`. Folder ini khusus potret arsitektur.

## Tiga mode

Setiap mode menjawab pertanyaan yang berbeda, jadi ketiganya dipakai bergantian
tergantung konteksnya.

| Mode | Berkas | Dipakai saat | Isi |
|---|---|---|---|
| Presentasi | `mode-presentasi.mmd` | Slide, demo, sesi juri | 10 kotak dalam 3 grup, tanpa detail versi, alur satu arah dari klien sampai WhatsApp. Hasil render 4542 x 1107 px |
| Full picture | `mode-full-picture.mmd` | Penjelasan teknis, onboarding developer baru | 7 lapisan berisi 33 node: klien, edge, permukaan API, aplikasi, data, asinkron, rilis. Hasil render 5490 x 3901 px |
| All in | `mode-allin.mmd` | Audit, rujukan lengkap, lampiran laporan | 10 band berisi 72 node: alat build, klien, runtime, middleware, rute, lapisan aplikasi, domain, asinkron, rilis, dan CI. Hasil render 11734 x 7651 px |

Cara memilihnya sederhana: kalau audiens hanya butuh tahu bentuk sistemnya,
pakai presentasi. Kalau audiens perlu tahu bagian mana yang menangani apa,
pakai full picture. Kalau yang dibutuhkan rujukan yang tidak boleh ada yang
hilang, pakai all in.

Mode presentasi dan full picture enak dibaca sekaligus. Mode all in lebarnya
lebih dari 11.000 px, jadi buka dengan fit to window atau zoom dulu sebelum
membacanya, sama seperti `alur-lengkap.png` di `docs/diagrams/`.

### Bentuk slide untuk mode presentasi

Mode presentasi punya dua bentuk. Versi Mermaid dipakai kalau diagramnya perlu
ditempel ke markdown atau dirender ulang bersama mode lain; versi slide dipakai
kalau tampilannya harus ramah saat dipresentasikan. Isinya alur yang sama.

| Berkas | Isi |
|---|---|
| `mode-presentasi.mmd` bersama `mode-presentasi.css` | Sumber Mermaid bertema lembut: direktif init mengatur font dan palet, CSS memberi sudut membulat |
| `slide-presentasi.html` | Sumber slide 16:9 berisi kartu ber-ikon dan strip perjalanan status paket. Ikon SVG ditanam langsung di berkas, jadi tidak butuh internet untuk membukanya |
| `slide-presentasi.png` | Hasil render 3840 x 2160 px |

Slide itu berkas HTML berdiri sendiri: buka di peramban, ubah teksnya, lalu
tangkap layar pada ukuran 1920 x 1080. Ikonnya dari Lucide dan sudah ditanam
sebagai SVG, jadi mengganti ikon cukup dengan menempelkan markup SVG baru di
dalam `.chip`.

## Deck presentasi

`deck.html` memuat enam slide 1920 x 1080 yang dirender menjadi `deck-01-konteks.png`
sampai `deck-06-teknologi.png`. Susunannya mengikuti pendekatan C4: satu slide
untuk satu pertanyaan, mulai dari konteks, turun ke arsitektur, lalu alur, keputusan,
cara jalan, dan teknologi.

| Slide | Berkas | Pertanyaan yang dijawab |
|---|---|---|
| 1 | `deck-01-konteks.png` | Sistem ini buat apa, dan siapa yang memakai? |
| 2 | `deck-02-arsitektur.png` | Bagian besarnya apa saja, dan saling bicara bagaimana? |
| 3 | `deck-03-alur-paket.png` | Satu paket bergerak bagaimana, dari pesanan sampai diterima? |
| 4 | `deck-04-keputusan.png` | Kenapa status paket dijaga database? |
| 5 | `deck-05-cara-jalan.png` | Kode yang sama ini dijalankan di mana? |
| 6 | `deck-06-teknologi.png` | Teknologi apa yang dipakai? |

Slide 2 adalah halaman arsitektur utamanya, dan sengaja dibuat seperti diagram
arsitektur pada umumnya: setiap komponen digambar sebagai ikon, dihubungkan garis
siku berpanah, dengan label di sisi yang tidak dilewati garis. Node diagram itu
diposisikan dengan koordinat tetap di atas kanvas 1760 x 850, sedangkan garisnya
adalah satu lapis SVG dengan `viewBox` yang sama, sehingga menambah atau menggeser
komponen cukup dengan mengubah dua angka di `style`-nya dan satu jalur `path`.

Cara merender: buka `deck.html` di peramban selebar 1920 px, lalu tangkap tiap slide
pada ukuran 1920 x 1080. Keenam slide ditumpuk vertikal, jadi satu tangkapan penuh
setinggi 6480 px bisa dipotong rata menjadi enam berkas.

## Berkas hasil render

Berkas PNG ditimpa ulang setiap kali `render.sh` dijalankan, jadi jangan
diedit manual: `mode-presentasi.png`, `mode-full-picture.png`,
dan `mode-allin.png`. Berkas `slide-presentasi.png` berasal dari
`slide-presentasi.html` dan tidak disentuh oleh `render.sh`.

## Render ulang

```bash
bash docs/architecture/render.sh
```

Skrip memakai `npx @mermaid-js/mermaid-cli` dengan versi Mermaid yang dipatok,
sehingga hasilnya bisa direproduksi tanpa menambah dependensi ke `package.json`
proyek. Kalau ingin merender satu berkas saja:

```bash
npx -y @mermaid-js/mermaid-cli@11 -i docs/architecture/mode-allin.mmd \
  -o docs/architecture/mode-allin.png -b white -s 2
```

Mermaid juga bisa dibaca tanpa render: GitHub, VS Code, dan Obsidian
menampilkan blok Mermaid di markdown, dan isi berkas `.mmd` di sini bisa
ditempel apa adanya ke dalam blok itu.

### Tema per diagram

Sebuah diagram boleh punya berkas CSS dengan nama yang sama, misalnya
`mode-presentasi.mmd` dan `mode-presentasi.css`. Kalau ada,
`render.sh` mengirimkannya ke mermaid-cli lewat opsi `-C`.

Yang diatur di CSS hanya bentuk, misalnya `rx` untuk sudut membulat dan
tebal garis. Ukuran font serta palet diatur dari direktif `%%{init: ...}%%` di
dalam berkas `.mmd` supaya Mermaid tetap mengukur ulang lebar kotaknya dan
teks tidak meluber keluar kotak.

## Mengedit diagram

Yang diedit adalah berkas `.mmd`, bukan PNG-nya. Setelah mengubah sumber,
jalankan `render.sh`. Aturan penamaan yang dipakai di ketiga berkas:

- Setiap band atau lapisan adalah satu `subgraph` dengan judul bernomor supaya
  urutannya tetap terbaca di layar maupun di teks.
- Prefiks id node mengikuti bandnya (K klien, B build, E edge, M middleware,
  R rute, A aplikasi, D data, S asinkron, V rilis, O operasi, C CI), jadi
  menambah node tidak menabrak id yang sudah ada.
- Garis penuh berarti alur normal, garis putus-putus berarti jalur balik atau
  jalur yang dipicu dari luar.

## Catatan pemeliharaan

Kalau arsitekturnya berubah, urutan yang benar: ubah kodenya dulu, baru
perbarui berkas `.mmd`, lalu render ulang dan perbarui README ini bila daftar
mode atau jumlah node berubah. Ketiga mode itu satu keluarga, jadi menambah
komponen di `mode-allin.mmd` hampir selalu berarti menambahkannya juga ke
`mode-full-picture.mmd` kalau komponen itu cukup menonjol.
