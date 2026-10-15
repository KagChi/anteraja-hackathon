# FR-ISS-004 — Detail kendala admin

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-004 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Detail kendala admin |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Berkas kode | app/Http/Controllers/Admin/ExceptionController.php |

## 1. Ringkasan

Satu kendala ditampilkan lengkap: kondisi paket, checklist yang sudah dinormalkan
foto, timeline, dan daftar keputusan yang diizinkan kategori tersebut. Ini dasar
bagi admin untuk memutuskan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | modal shipment saat baris berstatus kendala dibuka |
| Endpoint | GET /api/admin/exceptions/{tracking_number} |
| Nama route | api.admin.exceptions.show |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket punya minimal satu condition check dengan laporan kendala |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| tracking_number | string path | dicari firstOrFail |

## 6. Proses langkah demi langkah

1. Ambil paket dengan eager load buyer, courier, statusRef, items.product, conditionChecks beserta issueReport.decidedByStaff dan courier, serta statusLogs.changedByStaff.
2. Cari condition check pertama yang punya laporan kendala. Bila tidak ada, batalkan dengan 404 dan pesan bahwa paket tidak memiliki laporan kendala.
3. Ambil kategori dengan IssueCategory::fromOrFallback; nilai tak dikenal jatuh ke kemasan_bocor dan menulis log peringatan.
4. Susun daftar keputusan yang diizinkan dari IssueCategory::allowedDecisions beserta label dan helpText.
5. Bangun checklist dengan menggabungkan kunci dari konfigurasi dan nilai jsonb tersimpan; detail berbunyi "Terbukti" bila jawaban true dan "Normal" bila tidak.
6. Bangun timeline memakai PackageTimelineBuilder.
7. Ubah foto kendala menjadi URL sementara, atau route pratinjau bila disk tidak mendukung.
8. Balas seluruh data termasuk label status, tone, status resolusi, dan tone resolusi.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| package | objek | paket lengkap dengan relasi |
| issueReport | objek | laporan kendala |
| conditionCheck | objek | pemeriksaan terkait |
| statusLabel, tone | string | label dan warna status paket |
| resolutionStatus, resolutionTone | string | keadaan penyelesaian |
| category | objek | key dan label kategori |
| allowedDecisions | array | value, label, helpText |
| checklist | array | key, label, answered, detail |
| timeline | array | langkah kronologis |
| reportedAtFormatted | string | jam laporan WIB |
| photoTemporaryUrl | string atau null | URL foto kendala |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Resi tanpa laporan kendala | 404 | pesan khusus |
| Kategori tidak dikenal | 200 | fallback ke kemasan_bocor ditambah log peringatan |
| Checklist kosong | 200 | seluruh item dianggap tidak terjawab |
| Foto null | 200 | photoTemporaryUrl null |

## 9. Efek samping

Log peringatan Laravel bila kategori tidak dikenal.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin exceptions |
| Controller | Admin/ExceptionController@show |
| Enum | Enums/IssueCategory.php termasuk fromOrFallback dan allowedDecisions |
| Support | PackageTimelineBuilder, MediaStorage |
| Test | tests/Feature/AdminExceptionTest.php test_admin_can_view_exception_detail, tests/Feature/AdminConsoleWiringTest.php test_the_exception_detail_carries_the_decisions_and_resolution |
| UI | resources/js/components/ShipmentDetailModal.tsx |

## 11. Skenario uji

1. Given kendala kategori kemasan_bocor, When detail diminta, Then allowedDecisions memuat lima keputusan termasuk RECHECK.
2. Given kendala kategori pembeli_tidak_ada, When detail diminta, Then allowedDecisions hanya memuat RESCHEDULE dan RETURN.
3. Given resi tanpa laporan kendala, When detail diminta, Then HTTP 404.

## 12. Yang perlu dikonfirmasi

1. Checklist hanya menampilkan item konfigurasi saat ini; bila kunci konfigurasi berubah, jawaban lama tidak akan terlihat. Apakah perlu menyimpan snapshot label?
2. Apakah admin perlu melihat riwayat kendala sebelumnya pada paket yang sama?

