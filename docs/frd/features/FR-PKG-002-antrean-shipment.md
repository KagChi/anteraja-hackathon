# FR-PKG-002 — Antrean shipment dengan filter

| Properti | Nilai |
| --- | --- |
| ID | FR-PKG-002 |
| Modul | PKG — Paket dan FSM Status |
| Judul | Antrean shipment dengan filter |
| Status | Live |
| Berkas modul | docs/frd/02-paket-fsm.md |
| Aturan bisnis | BR-005 |
| Berkas kode | app/Queries/ShipmentIndexQuery.php, app/Http/Controllers/Admin/ShipmentController.php |

## 1. Ringkasan

Layar kerja utama hub. Menampilkan paket yang belum selesai dengan filter yang
cukup untuk dua cara kerja: menyaring cepat per hari relatif, dan mengikuti tautan
dari laporan yang membawa tanggal eksplisit.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | halaman /admin/shipment dan modal detail |
| Endpoint | GET /api/admin/shipments |
| Nama route | api.admin.shipments.index |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Aturan | Perilaku |
| --- | --- | --- | --- |
| q | string | nullable, max 100 | pencarian ILIKE pada resi, nama pembeli, area, nama kurir |
| status | string | nullable | disamakan persis dengan kolom status |
| courier | string | nullable | nilai "unassigned" berarti courier_id null; angka berarti id kurir |
| schedule | string | nullable, in today, tomorrow, upcoming, all | diabaikan bila date dikirim |
| date | string | nullable, format Y-m-d | rentang hari Jakarta, mengalahkan schedule |
| per_page | integer | nullable, in 10, 25, 50 | bawaan dari DTO ShipmentFilters |
| source | string | nullable, in ADMIN, CLIENT_APP, all | memisahkan pesanan dari aplikasi |

## 6. Proses langkah demi langkah

1. Bangun kueri dasar: join buyers dan left join staff sebagai couriers, dengan whereNotIn status CLOSED dan RETURNED.
2. Terapkan filter jadwal: bila date ada, pakai rentang hari Jakarta memakai DisplayTime::dayRange; bila tidak, pakai schedule relatif today, tomorrow, atau upcoming.
3. Terapkan filter status bila ada.
4. Terapkan filter sumber bila ada.
5. Terapkan filter kurir, termasuk kasus belum ditugaskan.
6. Terapkan pencarian dengan escape karakter persen, garis bawah, dan backslash sebelum pola ILIKE.
7. Urutkan deterministik berdasarkan tracking_number menaik lalu id menaik.
8. Paginasi sesuai per_page, lalu bungkus dengan withQueryString.
9. Controller mengubah setiap baris menjadi bentuk tampilan: label status diganti "Kendala (kategori)" bila status ISSUE_REPORTED dan ada kategori, dan kolom availableAction dihitung Package::availableAdminActions.
10. Sertakan daftar kurir aktif dan statusOptions dari tabel package_statuses.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| shipments | array | baris antrean |
| shipments[].tracking | string | resi |
| shipments[].buyer | string | nama pembeli atau "-" |
| shipments[].location | string | area, atau alamat bila area kosong |
| shipments[].schedule | string | label jendela dalam WIB |
| shipments[].courier | string atau null | nama kurir |
| shipments[].courier_id | integer atau null | id kurir |
| shipments[].status | string | label dari package_statuses |
| shipments[].statusKey | string | nilai status huruf kecil |
| shipments[].tone | string | warna semantik dari enum Tone |
| shipments[].hasIssue | boolean | true bila ISSUE_REPORTED |
| shipments[].availableAction | string | issue_detail, assign_courier, reschedule, atau detail |
| shipments[].source | string | ADMIN atau CLIENT_APP |
| shipments[].sourceLabel | string | "Hub" atau "Aplikasi Pelanggan" |
| shipments[].sourceBadge | string | "HUB" atau "APP" |
| paginator | objek | total, per_page, current_page, last_page, from, to |
| filters | objek | nilai filter yang benar-benar dipakai |
| couriers | array | kurir aktif berisi id, name, phone |
| statusOptions | array | pasangan value dan label dari package_statuses |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| per_page di luar daftar | 422 | aturan in |
| format date salah | 422 | date_format |
| q memuat tanda persen | 200 | karakter di-escape sehingga tidak memperluas hasil |
| Halaman melebihi jumlah halaman | 200 | shipments kosong dengan paginator yang menandai halaman terakhir |

## 9. Efek samping

Tidak ada penulisan. Kueri memakai subquery latestIssue dan relasi statusRef, buyer, courier.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin shipments |
| Controller | Admin/ShipmentController@index |
| Query | Queries/ShipmentIndexQuery |
| DTO | DTOs/ShipmentFilters |
| FormRequest | Requests/Admin/IndexShipmentRequest.php |
| Model | Package termasuk availableAdminActions, PackageStatusRef |
| Support | DisplayTime |
| Test | tests/Feature/AdminShipmentTest.php test_admin_can_view_shipments_index, test_admin_can_filter_shipments, test_admin_can_search_shipments_safely, tests/Feature/AdminConsoleWiringTest.php test_the_shipment_index_carries_the_filter_lists, test_the_date_filter_names_one_delivery_day |
| UI | resources/js/pages/admin/Shipment.tsx |

## 11. Skenario uji

1. Given satu paket CLOSED dan satu paket CONFIRMED, When antrean diminta, Then hanya paket CONFIRMED yang muncul.
2. Given filter schedule today dan jadwal besok, When antrean diminta, Then paket besok tidak muncul.
3. Given filter date yang sama dengan jadwal besok, When antrean diminta, Then paket besok muncul karena tanggal eksplisit mengalahkan filter relatif.
4. Given q berisi tanda persen, When antrean diminta, Then hanya baris yang benar-benar memuat tanda itu yang dikembalikan.

## 12. Yang perlu dikonfirmasi

1. Filter status menerima nilai apa pun tanpa validasi daftar, sehingga nilai yang salah hanya menghasilkan daftar kosong. Apakah perlu dibatasi ke daftar status resmi?
2. Status CLOSED dan RETURNED dikecualikan dari antrean; paket RETURNED hanya terlihat di halaman riwayat. Apakah hub perlu melihat paket retur yang belum ditutup?

