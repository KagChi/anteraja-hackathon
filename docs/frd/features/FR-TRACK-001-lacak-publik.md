# FR-TRACK-001 — Pelacakan publik berdasarkan resi

| Properti | Nilai |
| --- | --- |
| ID | FR-TRACK-001 |
| Modul | TRACK — Pelacakan Publik dan Tautan Tamu |
| Judul | Pelacakan publik berdasarkan resi |
| Status | Live |
| Berkas modul | docs/frd/09-pelacakan.md |
| Aturan bisnis | BR-019, BR-024 |
| Berkas kode | app/Http/Controllers/Api/Tracking/TrackController.php |

## 1. Ringkasan

Layanan mandiri tanpa login untuk menjawab "di mana paket saya". Karena nomor resi
bersifat berurutan dan dapat ditebak, endpoint ini dirancang untuk tidak
mengungkapkan apa pun tentang penerima.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | publik |
| Middleware | api, force.json, client.headers, throttle track-resi 20 per menit per IP |
| Pembatasan data | hanya status, jendela, kota tingkat kabupaten, dan label tetap |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | panel lacak pada landing page, resources/js/components/site/TrackResiCard.tsx |
| Endpoint | GET /api/track/{resi} |
| Nama route | api.track.show |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Aturan |
| --- | --- | --- |
| resi | string path | pola 4 sampai 32 karakter alfanumerik atau tanda hubung |

Resi dipangkas dan diubah ke huruf besar sebelum pencarian.

## 6. Proses langkah demi langkah

1. Cari paket dengan resi dan eager load buyer, statusRef, statusLogs, dan deliveryMilestones.
2. Bila tidak ada, balas 404 dengan message agar pengunjung memeriksa kembali nomornya dan code TRACKING_NOT_FOUND.
3. Susun timeline dari dua sumber: setiap status_logs diterjemahkan memakai tabel sebelas label tetap, dan setiap milestone DEPARTED atau ARRIVED menjadi entri kurir berangkat atau kurir tiba.
4. Urutkan timeline menaik berdasarkan waktu dan tandai entri terakhir dengan is_accent true.
5. Ambil kota tujuan dari buyer.area dengan memotong bagian setelah koma terakhir.
6. Tentukan updated_label dari status log terakhir bila ada, atau diperlakukan sebagai '-' bila timeline kosong.
7. Balas data pelacakan.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data.resi | string | resi |
| data.status.code, label, tone | string | status publik |
| data.service | string | label layanan |
| data.category | string | kategori paket |
| data.cold_chain | string | teks tetap "Beku -18°C" |
| data.destination_city | string atau null | kota tingkat kabupaten |
| data.schedule_label | string | jendela WIB |
| data.updated_label | string | waktu kejadian terakhir |
| data.timeline | array | label, time, detail, is_accent |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Resi tidak ada | 404 TRACKING_NOT_FOUND |
| Huruf kecil | tetap ditemukan karena dinormalkan |
| Paket tanpa status log | timeline kosong, updated_label '-' |
| Area tanpa koma | nilai area dipakai apa adanya sebagai kota |
| Melebihi 20 permintaan per menit | 429 dengan pesan agar mencoba beberapa saat lagi |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php route track |
| Controller | Api/Tracking/TrackController |
| Rate limiter | AppServiceProvider for track-resi |
| Support | DisplayTime |
| Test | tests/Feature/PublicTrackingTest.php (5 test termasuk privasi dan pemotongan kota) |
| UI | resources/js/components/site/TrackResiCard.tsx |

## 11. Skenario uji

1. Given resi dengan tiga status log, When dilacak, Then HTTP 200 dengan timeline tiga entri berurutan dan entri terakhir bertanda accent.
2. Given resi tidak ada, When dilacak, Then HTTP 404 tanpa membocorkan data apa pun.
3. Given area berbunyi Tebet, Jakarta Selatan, When dilacak, Then destination_city bernilai Jakarta Selatan.
4. Given 21 permintaan dalam satu menit dari satu IP, When permintaan ke-21 dikirim, Then HTTP 429.

## 12. Yang perlu dikonfirmasi

1. Nomor resi berurutan, sehingga seseorang dapat menebak nomor lain. Apakah perlu format resi acak atau penambahan verifikasi?
2. Apakah pelacakan publik perlu menampilkan perkiraan waktu tiba, atau cukup jendela jadwal seperti sekarang?

