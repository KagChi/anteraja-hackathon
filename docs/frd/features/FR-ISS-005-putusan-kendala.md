# FR-ISS-005 — Putusan kendala

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-005 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Putusan kendala |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Aturan bisnis | BR-004, BR-012, BR-013, BR-026 |
| Berkas kode | app/Actions/Issues/ResolveIssue.php |

## 1. Ringkasan

Menetapkan keputusan atas satu kendala. Aksi ini memvalidasi bahwa keputusan
diizinkan kategorinya, menolak keputusan kedua, lalu mendelegasikan efeknya ke
handler sesuai jenis keputusan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |
| Aktor transisi | admin yang memutuskan |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | panel keputusan pada modal detail kendala |
| Endpoint | POST /api/admin/exceptions/{tracking_number}/decision |
| Nama route | api.admin.exceptions.decision |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus ISSUE_REPORTED dan laporan kendala belum punya keputusan |
| Postcondition | laporan memuat decision, decision_reason, quality_inspection_note, decided_by, dan decided_at |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| decision | string | required, in RETURN, RESEND, PROCEED, RESCHEDULE, RECHECK |
| decision_reason | string | required, min 10, max 2000 |
| quality_inspection_note | string | nullable, max 500 |

## 6. Proses langkah demi langkah

1. Controller mencari paket dengan relasi conditionChecks.issueReport.
2. Controller mencari laporan pertama yang decision-nya masih null. Bila tidak ada, balas 422 dengan message "Kendala sudah diputuskan sebelumnya." tanpa memanggil Action.
3. Action membuka transaksi dan mengunci baris paket, lalu mengunci baris laporan kendala.
4. Bila status paket bukan ISSUE_REPORTED atau laporan sudah punya keputusan, lempar IssueAlreadyDecided yang menyebut nama pemutus sebelumnya bila ada.
5. Ambil kategori dengan fallback, lalu periksa apakah keputusan ada di allowedDecisions kategori itu. Bila tidak, lempar InvalidArgumentException dengan pesan dari lang coldchain.errors.invalid_decision.
6. Isi lima kolom keputusan sekaligus: decision, decision_reason, quality_inspection_note, decided_by, dan decided_at waktu UTC.
7. Pilih handler sesuai keputusan dan jalankan.
8. Balas message dan paket yang sudah di-refresh.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Keputusan kendala untuk resi {resi} berhasil disimpan." |
| package | objek | fresh dengan statusRef |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Keputusan tidak diizinkan kategori | InvalidArgumentException | misalnya RECHECK untuk pembeli_tidak_ada |
| Alasan kurang dari 10 karakter | 422 | validasi |
| Kendala sudah diputuskan | 422 | dari controller |
| Balapan dua permintaan | satu berhasil, satu IssueAlreadyDecided | karena lockForUpdate pada paket dan laporan |
| Resi tidak ada | 404 | firstOrFail |
| Kategori tidak dikenal | 200 | fallback ke kemasan_bocor |

## 9. Efek samping

Bergantung handler (lihat FR-ISS-006): perubahan status, notifikasi, dan bila
RESEND juga paket pengganti baru.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin exceptions |
| Controller | Admin/ExceptionDecisionController |
| Action | Actions/Issues/ResolveIssue |
| Handler | Actions/Issues/Decisions/*.php |
| FormRequest | Requests/Admin/ResolveIssueRequest.php |
| DTO | DTOs/ResolveIssueData.php |
| Enum | Enums/IssueDecision.php, Enums/IssueCategory.php |
| Exception | app/Exceptions/IssueAlreadyDecided.php |
| Test | tests/Feature/AdminExceptionTest.php test_admin_can_resolve_issue_with_return_decision, tests/Feature/AdminConsoleE2ETest.php test_the_kendala_journey_completes_end_to_end |
| UI | resources/js/components/ShipmentDetailModal.tsx |

## 11. Skenario uji

1. Given kendala kemasan_bocor tanpa keputusan, When admin memilih RETURN dengan alasan memadai, Then HTTP 200 dan kelima kolom keputusan terisi.
2. Given kendala kategori pembeli_tidak_ada, When admin memilih RECHECK, Then permintaan ditolak dan keputusan tidak tersimpan.
3. Given kendala yang sudah diputuskan, When admin memutuskan lagi, Then HTTP 422 tanpa perubahan data.

## 12. Yang perlu dikonfirmasi

1. Apakah admin perlu dapat mengubah keputusan yang sudah dibuat (misalnya salah pilih)? Saat ini tidak bisa.
2. quality_inspection_note bersifat opsional meski merupakan catatan mutu; apakah sebaiknya wajib untuk kategori suhu naik dan kemasan bocor?

