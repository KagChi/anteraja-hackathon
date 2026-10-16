# FR-ISS-002 — Mengirim laporan kendala

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-002 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Mengirim laporan kendala |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Aturan bisnis | BR-002, BR-003, BR-004, BR-022 |
| Berkas kode | app/Actions/Courier/ReportIssue.php |

## 1. Ringkasan

Kurir melaporkan masalah di lapangan dengan foto wajib. Sistem menyimpan
pemeriksaan kondisi dan laporan, memindahkan paket ke ISSUE_REPORTED, memberi tahu
admin, dan mengirim menu penyelesaian ke pelanggan lewat WhatsApp.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize update |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | form kendala pada resources/js/pwa/screens/courier/Issue.tsx |
| Endpoint | POST /api/courier/deliveries/{tracking_number}/issue (multipart) |
| Nama route | api.courier.issues.store |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus COURIER_ASSIGNED yang sudah diterima, CHECKED, atau IN_DELIVERY; belum ada kendala terbuka |
| Postcondition | paket berstatus ISSUE_REPORTED dengan satu condition check bermasalah dan satu laporan kendala |

## 5. Input dan validasi

| Field | Tipe | Aturan | Keterangan |
| --- | --- | --- | --- |
| issue_category | string | required, harus nilai IssueCategory | menerima alias jenis_kendala |
| description | string | required, min 10, max 200 | menerima alias catatan_kendala dan note |
| photo | file | required, image, mimes jpeg, jpg, png, webp, max 5120 KB | - |
| temperature_c | numeric | required_if issue_category suhu_naik, nullable, antara -60 dan 40 | - |
| checklist | array | nullable | dinormalkan |

prepareForValidation memetakan pembayaran_cod_ditolak ke cod_ditolak dan
akses_ditutup ke alamat_tidak_ditemukan untuk kompatibilitas dengan aplikasi lama.

## 6. Proses langkah demi langkah

1. Policy memastikan kepemilikan paket.
2. Foto disimpan MediaStorage::storeProof pada folder issues.
3. Action membuka transaksi dan mengunci baris paket.
4. Bila courier_id bukan kurir ini, lempar DeliveryNotActionable.
5. Bila status di luar tiga yang diizinkan, lempar DeliveryNotActionable dengan pesan status tidak dapat dilaporkan.
6. Periksa kendala terbuka; bila ada, lempar DeliveryNotActionable::issueAlreadyOpen.
7. Bila status COURIER_ASSIGNED, transisi ke CHECKED dengan deskripsi pemeriksaan menemukan kendala.
8. Buat ConditionCheck berisi is_normal false, checklist, catatan label kategori, temperature_c, dan waktu pemeriksaan.
9. Buat IssueReport berisi check_id, kategori, deskripsi, foto, dan waktu laporan.
10. Transisi ke ISSUE_REPORTED dengan deskripsi "Laporan kendala: {label kategori}".
11. Antrekan notifikasi issue_reported ke setiap admin aktif.
12. Bila terjadi kegagalan, hapus foto yang sudah tersimpan lalu lemparkan ulang.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Laporan terkirim, menunggu keputusan admin." |
| package | objek | fresh dengan statusRef |
| package.status | string | ISSUE_REPORTED |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Kategori suhu_naik tanpa suhu | 422 | required_if |
| Foto lebih dari 5 MB atau format lain | 422 | validasi |
| Deskripsi kurang dari 10 karakter | 422 | validasi |
| Kendala terbuka sudah ada | DeliveryNotActionable | belum dipetakan ke kode khusus |
| Status tidak diizinkan | DeliveryNotActionable | sama |
| Paket milik kurir lain | 404 | PackagePolicy |
| Kegagalan setelah foto tersimpan | exception asli | foto dibersihkan |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Berkas foto | disk local, folder issues/{Y}/{M} |
| Pemeriksaan | condition_checks dengan is_normal false |
| Laporan | issue_reports |
| Log status | status_logs ke CHECKED bila perlu, lalu ke ISSUE_REPORTED |
| Notifikasi | issue_reported untuk setiap admin aktif |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier deliveries issue |
| Controller | Courier/IssueReportController@store |
| Action | Actions/Courier/ReportIssue |
| FormRequest | Requests/Courier/ReportIssueRequest.php |
| DTO | DTOs/Courier/ReportIssueData.php |
| Support | ChecklistNormalizer, MediaStorage |
| Test | tests/Feature/CourierFlowTest.php test_courier_report_issue_flow, tests/Feature/WhatsAppNotificationFlowTest.php test_courier_issue_report_notifies_ops_but_not_the_buyer, tests/Feature/AdminConsoleE2ETest.php test_the_kendala_journey_completes_end_to_end |
| UI | resources/js/pwa/screens/courier/Issue.tsx |

## 11. Skenario uji

1. Given paket CHECKED tanpa kendala, When kurir mengirim laporan lengkap, Then HTTP 200, paket ISSUE_REPORTED, satu issue_reports tersimpan, dan admin menerima notifikasi issue_reported.
2. Given kategori suhu_naik tanpa suhu, When laporan dikirim, Then HTTP 422 dan tidak ada issue_reports baru.
3. Given foto hilang, When laporan dikirim, Then HTTP 422 dan status paket tidak berubah.

## 12. Yang perlu dikonfirmasi

1. Satu paket hanya boleh punya satu kendala terbuka, dan setelah diputuskan kurir dapat melaporkan kendala baru. Apakah itu memadai untuk kasus kendala berulang?
2. Deteksi GPS tidak dijalankan pada laporan kendala. Apakah laporan kendala memang sengaja tidak memerlukan bukti lokasi?

