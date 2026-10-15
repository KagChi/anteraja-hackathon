# FR-DEL-006 — Riwayat pengantaran kurir

| Properti | Nilai |
| --- | --- |
| ID | FR-DEL-006 |
| Modul | DEL — Pengantaran dan Bukti Serah Terima |
| Judul | Riwayat pengantaran kurir |
| Status | Live |
| Berkas modul | docs/frd/04-pengantaran-bukti.md |
| Aturan bisnis | BR-005 |
| Berkas kode | app/Http/Controllers/Courier/HistoryController.php, app/Queries/HistoryQuery.php |

## 1. Ringkasan

Tab riwayat pada aplikasi kurir: daftar paket yang sudah selesai atau dikembalikan
beserta ringkasan berapa yang selesai hari ini.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir terautentikasi |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Pembatasan data | filter courierId diisi id kurir yang login |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tab riwayat aplikasi kurir |
| Endpoint | GET /api/courier/history |
| Nama route | api.courier.history.index |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | token kurir valid |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Aturan | Keterangan |
| --- | --- | --- | --- |
| q | string | opsional | dibaca dari query, bukan FormRequest |
| status | string | opsional | disamakan persis |
| page | integer | minimal 1 | dibaca dari query |

## 6. Proses langkah demi langkah

1. Susun DTO HistoryFilters berisi q, status, page, dan courierId kurir yang login.
2. Query membatasi status hanya RECEIVED, RETURNED, dan CLOSED.
3. Kolom turunan completed_at dihitung sebagai COALESCE deliveries.delivered_at dan waktu log terakhir menuju RETURNED.
4. Paginasi 15 baris dengan withQueryString.
5. Ubah setiap baris: label status diganti "Terkirim" untuk RECEIVED dan "Dikembalikan" untuk RETURNED.
6. Hitung completedToday: paket RECEIVED milik kurir yang updated_at-nya hari ini.
7. Balas daftar, ringkasan, dan paginasi.

Angka onTimeRate berupa konstanta "97% Tepat Waktu" dan tidak dihitung.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| history[].resi | string | resi |
| history[].recipient | string | nama penerima |
| history[].completedAt | string | waktu selesai dalam bahasa Indonesia |
| history[].status | string | Terkirim, Dikembalikan, atau label lain |
| summary.completedToday | string | jumlah dengan satuan pengiriman |
| summary.completedCount | integer | angka selesai hari ini |
| summary.onTimeRate | string | konstanta |
| pagination | objek | currentPage, lastPage, perPage, total |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Kurir tanpa riwayat | daftar kosong, summary berisi 0 |
| Paket RETURNED tanpa bukti pengantaran | completed_at diambil dari log RETURNED |
| Paket yang masih berjalan | tidak muncul |
| status di luar tiga nilai | daftar kosong karena penyaringan tetap memakai tiga status terminal |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier |
| Controller | Courier/HistoryController@index |
| Query | Queries/HistoryQuery |
| DTO | DTOs/HistoryFilters |
| Support | DisplayTime |
| Test | tests/Feature/CourierFlowTest.php test_courier_history_and_profile, tests/Feature/PwaShellTest.php test_the_history_tab_lists_completed_deliveries |
| UI | resources/js/pwa/screens/courier/History.tsx |

## 11. Skenario uji

1. Given kurir dengan dua paket RECEIVED hari ini, When riwayat diminta, Then daftar memuat dua baris berlabel Terkirim dan completedCount bernilai 2.
2. Given paket RETURNED, When riwayat diminta, Then status berbunyi Dikembalikan dan completedAt terisi dari log retur.

## 12. Yang perlu dikonfirmasi

1. Angka tepat waktu 97 persen adalah konstanta. Apakah perlu dihitung dari perbandingan delivered_at dengan scheduled_end_at?
2. Query memakai kolom created_at pada model HistoryQuery tetapi kueri di controller adalah is_active boolean; perlu dipastikan tidak ada kesalahan tipe pada kueri jumlah selesai hari ini.

