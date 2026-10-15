# FR-ISS-001 — Menyiapkan form laporan kendala

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-001 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Menyiapkan form laporan kendala |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Berkas kode | app/Http/Controllers/Courier/IssueReportController.php |

## 1. Ringkasan

Memberi kurir daftar kategori kendala dan konteks paket sebelum ia mengirim
laporan. Endpoint ini juga menjadi penjaga agar tidak ada dua kendala terbuka pada
satu paket.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize update |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | layar kendala kurir, app/NativeComponents/Courier/Issue.php |
| Endpoint | GET /api/courier/deliveries/{tracking_number}/issue |
| Nama route | api.courier.issues.create |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus COURIER_ASSIGNED yang sudah diterima, CHECKED, atau IN_DELIVERY; belum ada kendala tanpa keputusan |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| tracking_number | string path | dicari firstOrFail |

## 6. Proses langkah demi langkah

1. Ambil paket dengan relasi buyer dan statusRef.
2. Policy memastikan kepemilikan paket.
3. Bila status di luar tiga yang diizinkan, balas 422 dengan pesan paket tidak dalam status yang dapat dilaporkan dan redirect ke layar pengantaran paket itu.
4. Bila status COURIER_ASSIGNED dan courier_accepted_at null, balas 422 dengan redirect ke /courier/tasks.
5. Periksa kendala terbuka memakai join ke condition_checks dengan syarat decision null.
6. Bila kendala terbuka ada, balas 422 dengan pesan kendala sudah dilaporkan.
7. Susun daftar opsi dari seluruh nilai IssueCategory beserta labelnya.
8. Balas issueForm berisi tracking, recipient, reporter, noteLimit 200, options, dan checklistItems dari konfigurasi.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| issueForm.tracking | string | resi |
| issueForm.recipient | string | nama penerima |
| issueForm.reporter | string | nama kurir |
| issueForm.noteLimit | integer | 200 |
| issueForm.options | array | enam kategori dengan value dan label |
| issueForm.checklistItems | objek | empat kunci checklist |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Status di luar daftar | 422 | dengan redirect |
| Tugas belum diterima | 422 | dengan redirect ke daftar tugas |
| Kendala terbuka sudah ada | 422 | mencegah laporan kedua |
| Paket milik kurir lain | 404 | PackagePolicy |

## 9. Efek samping

Tidak ada.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier deliveries issue |
| Controller | Courier/IssueReportController@create |
| Enum | Enums/IssueCategory.php |
| Model | IssueReport, ConditionCheck, Package |
| Konfigurasi | config/coldchain.php checklist_items |
| Test | tests/Feature/CourierFlowTest.php test_courier_report_issue_flow |
| UI | app/NativeComponents/Courier/Issue.php, app/Support/CourierPayload.php method issueForm |

## 11. Skenario uji

1. Given paket IN_DELIVERY tanpa kendala terbuka, When form diminta, Then HTTP 200 dengan enam opsi kategori.
2. Given paket yang sudah punya kendala belum diputuskan, When form diminta, Then HTTP 422.
3. Given paket CONFIRMED, When form diminta, Then HTTP 422 dengan redirect.

## 12. Yang perlu dikonfirmasi

1. Kategori kendala tidak dibatasi per status, sehingga kurir dapat memilih kategori apa pun. Apakah perlu pemetaan kategori yang relevan per situasi?
2. Daftar kategori berbeda dari label pada data contoh aplikasi (misalnya pembayaran_cod_ditolak). Perlu diselaraskan agar kurir tidak bingung.

