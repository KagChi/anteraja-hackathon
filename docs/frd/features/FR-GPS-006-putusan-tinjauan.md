# FR-GPS-006 — Putusan tinjauan GPS

| Properti | Nilai |
| --- | --- |
| ID | FR-GPS-006 |
| Modul | GPS — Cold Chain dan Validasi Lokasi |
| Judul | Putusan tinjauan GPS |
| Status | Live |
| Berkas modul | docs/frd/05-coldchain-gps.md |
| Aturan bisnis | BR-013, BR-026 |
| Berkas kode | app/Http/Controllers/Admin/GpsCheckController.php |

## 1. Ringkasan

Hub menyetujui atau menolak permintaan kurir. Persetujuan itu yang membuka retry;
insiden asalnya tetap tercatat sebagai evidence.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | panel keputusan pada resources/js/pages/admin/GpsChecks.tsx |
| Endpoint | POST /api/admin/gps-checks/{id}/decision |
| Nama route | api.admin.gps-checks.decision |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | baris gps_lock_requests berstatus PENDING |
| Postcondition | status menjadi APPROVED atau REJECTED dengan decided_by, decision_reason, dan decided_at terisi |

## 5. Input dan validasi

| Field | Tipe | Aturan | Pesan khusus |
| --- | --- | --- | --- |
| decision | string | required, in APPROVED, REJECTED | - |
| decision_reason | string | required, min 10, max 1000 | "Alasan keputusan minimal 10 karakter." |

## 6. Proses langkah demi langkah

1. Ambil baris gps_lock_requests dengan relasi package; id tidak ada menghasilkan 404.
2. Bila status bukan PENDING, balas 422 dengan message bahwa permintaan sudah diputuskan dan code GPS_LOCK_ALREADY_DECIDED.
3. Ubah status menjadi nilai decision, isi decided_by dengan id admin, decision_reason dengan alasan, dan decided_at dengan waktu UTC.
4. Simpan. Constraint ck_gps_lock_requests_decision memastikan decided_at hanya terisi saat status bukan PENDING.
5. Balas message yang berbeda untuk disetujui dan ditolak, beserta ringkasan permintaan.
6. Pada percobaan aksi berikutnya oleh kurir, validator menemukan override APPROVED dan aksi diizinkan; baris location_checks baru mencatat override_id.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Tinjauan GPS untuk {resi} disetujui." atau "ditolak." |
| lock.id | integer | id permintaan |
| lock.status | string | APPROVED atau REJECTED |
| lock.status_label | string | label Indonesia |
| lock.decision_reason | string | alasan admin |
| lock.decided_at | string | ISO8601 |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Alasan kurang dari 10 karakter | 422 | pesan khusus |
| Permintaan sudah diputuskan | 422 | code GPS_LOCK_ALREADY_DECIDED |
| Id tidak ada | 404 | findOrFail |
| Dua admin memutuskan bersamaan | satu berhasil, satu 422 | pemeriksaan status tidak memakai lockForUpdate |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Baris permintaan diperbarui | gps_lock_requests |
| Efek berantai | aksi kurir berikutnya diizinkan, dengan override_id pada location_checks |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin |
| Controller | Admin/GpsCheckController@decide |
| FormRequest | Requests/Admin/GpsDecisionRequest.php |
| Service | Services/Gps/GpsValidator::approvedOverride |
| Enum | Enums/GpsLockStatus.php |
| Test | tests/Feature/AdminGpsConsoleTest.php test_approving_a_request_unlocks_the_shipment, test_a_decision_needs_a_reason, tests/Feature/CourierGpsValidationTest.php test_the_hub_approval_unlocks_the_retry |
| UI | resources/js/pages/admin/GpsChecks.tsx |

## 11. Skenario uji

1. Given permintaan PENDING, When admin menyetujui dengan alasan 10 karakter atau lebih, Then HTTP 200, status APPROVED, dan kurir dapat mengulang aksi itu.
2. Given permintaan yang sudah APPROVED, When admin memutuskan lagi, Then HTTP 422 GPS_LOCK_ALREADY_DECIDED dan keputusan lama tidak berubah.
3. Given alasan 5 karakter, When diputuskan, Then HTTP 422 dengan pesan minimal 10 karakter.

## 12. Yang perlu dikonfirmasi

1. Keputusan tidak memakai lockForUpdate, sehingga dua admin bersamaan dapat saling menimpa. Perlu penguncian seperti pada putusan kendala?
2. Apakah kurir perlu diberi tahu hasil tinjauan lewat notifikasi WhatsApp? Saat ini tidak ada notifikasi pada alur ini.
3. Apakah persetujuan berlaku untuk satu kali percobaan atau untuk semua percobaan berikutnya pada aksi yang sama?

