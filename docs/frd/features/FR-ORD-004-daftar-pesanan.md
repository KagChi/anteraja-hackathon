# FR-ORD-004 — Daftar pesanan dengan tab dan pencarian

| Properti | Nilai |
| --- | --- |
| ID | FR-ORD-004 |
| Modul | ORD — Katalog dan Pemesanan Pelanggan |
| Judul | Daftar pesanan dengan tab dan pencarian |
| Status | Live |
| Berkas modul | docs/frd/10-katalog-pesanan.md |
| Berkas kode | app/Queries/ClientOrderListQuery.php |

## 1. Ringkasan

Layar Pesanan dan Riwayat pelanggan. Empat tab dengan hitungan masing-masing,
pencarian pada resi dan nama produk, serta paginasi.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | pembeli terautentikasi |
| Middleware | auth:sanctum, abilities:client, client.buyer |
| Pembatasan data | seluruh kueri memakai buyer_id milik pengguna |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tab Pesanan dan tab Riwayat aplikasi pelanggan |
| Endpoint | GET /api/client/orders |
| Nama route | api.client.orders.index |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | token valid |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Aturan |
| --- | --- | --- |
| tab | string | nullable, in active, completed, issue, all, bawaan active |
| q | string | nullable, max 100 |
| per_page | integer | nullable, min 1, max 50, bawaan 10 |
| page | integer | nullable, min 1 |

## 6. Proses langkah demi langkah

1. Hitung hitungan tab dengan satu kueri agregat memakai FILTER: aktif adalah status di luar RECEIVED, RETURNED, dan CLOSED; selesai adalah RECEIVED atau CLOSED; kendala adalah ISSUE_REPORTED atau RETURNED; total adalah seluruh paket pembeli.
2. Bangun kueri daftar dengan eager load items.product, buyer, courier, delivery, scheduleConfirmations, dan conditionChecks.issueReport.
3. Terapkan tab: active memakai whereNotIn tiga status terminal, completed memakai whereIn RECEIVED dan CLOSED, issue memakai whereIn ISSUE_REPORTED dan RETURNED, all tanpa filter.
4. Terapkan pencarian ILIKE pada nomor resi atau nama produk.
5. Urutkan scheduled_at menurun dengan NULLS LAST lalu created_at menurun.
6. Paginasi sesuai per_page dan page.
7. Ubah setiap baris memakai OrderSummaryResource yang memakai OrderPresenter.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data[].resi | string | resi |
| data[].status | objek | code, label, tone |
| data[].product, product_meta | string | ringkasan isi dan ukuran |
| data[].recipient, recipient_area | string | penerima |
| data[].eta_date, eta_time | string | perkiraan kedatangan |
| data[].action_required | string atau null | langkah yang menunggu |
| data[].next_screen | string | layar berikutnya |
| data[].completed_label, completed_at | string atau null | penyelesaian |
| meta.active_count, completed_count | integer | hitungan |
| meta.standard_temp, sla_rate | string | teks tetap |
| meta.tab_counts | objek | active, completed, issue, total |
| meta.pagination | objek | current_page, last_page, per_page, total, has_more |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| tab kosong | daftar kosong dengan hitungan benar |
| per_page di luar rentang | 422 |
| Pencarian resi milik pembeli lain | tidak muncul apa pun karena pembatasan buyer_id |
| sla_rate | konstanta 96 persen, bukan hasil hitung |

## 9. Efek samping

Tidak ada penulisan. Hitungan tab dihitung ulang setiap pemanggilan tanpa cache.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup client orders |
| Controller | Api/Client/OrderController@index |
| Query | Queries/ClientOrderListQuery |
| FormRequest | Requests/Api/Client/OrderIndexRequest.php |
| Resource | Resources/Client/OrderSummaryResource.php |
| Service | Services/Client/OrderPresenter.php |
| Test | tests/Feature/ClientApiAuthTest.php test_buyer_orders_list_with_tabs_and_counts, test_order_ownership_isolation_returns_404_for_other_buyer |
| UI | app/NativeComponents/Client/Orders.php, History.php, app/Support/ClientPayload.php method order |

## 11. Skenario uji

1. Given tiga paket aktif dan dua selesai, When tab active diminta, Then data memuat tiga baris dan tab_counts.active bernilai 3.
2. Given per_page 100, When diminta, Then HTTP 422.
3. Given pencarian yang cocok dengan resi pembeli lain, When diminta, Then tidak ada baris yang dikembalikan.

## 12. Yang perlu dikonfirmasi

1. sla_rate adalah konstanta; apakah perlu dihapus atau dihitung?
2. Apakah perlu filter pencarian juga pada nama penerima, bukan hanya resi dan nama produk?

