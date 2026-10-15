# FR-ISS-008 — Layar kendala pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-008 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Layar kendala pelanggan |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Aturan bisnis | BR-019, BR-024 |
| Berkas kode | app/Services/Client/IssueViewService.php |

## 1. Ringkasan

Menjelaskan kepada pelanggan apa yang terjadi pada paketnya dan apa yang akan
dilakukan, tanpa membuka data bebas yang ditulis staf atau kurir.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | pembeli pemilik paket, atau guest pemegang tautan token paket itu |
| Middleware | client.owned untuk jalur akun, client.package untuk jalur tautan |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | layar kendala pelanggan, app/NativeComponents/Client/Issue.php |
| Endpoint | GET /api/client/orders/{tracking_number}/issue, GET /api/guest/{token}/issue, GET /api/client/{token}/issue |
| Nama route | api.client.orders.issue.show, api.guest.issue.show, api.client.issue.show |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket dapat diakses pemanggil |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Periksa apakah paket punya condition check dengan laporan kendala.
2. Bila tidak ada, balas HTTP 200 dengan success true dan redirect_url ke tautan tracking, bukan error.
3. Ambil laporan kendala terakhir dan kategorinya; nilai tak dikenal memakai kunci default.
4. Terjemahkan kategori ke pesan ringkasan memakai lang client.issue.summary.
5. Terjemahkan keputusan ke pesan memakai lang client.issue.decision, dengan pesan pending bila keputusan belum ada.
6. Bangun ringkasan cold chain dari pemeriksaan terakhir.
7. Hitung kelayakan penggantian dan tautan jadwal paket pengganti bila sudah ada.
8. Bangun timeline khusus kendala.
9. Sertakan kanal layanan pelanggan dari konfigurasi.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| success | boolean | true |
| data.trackingNumber | string | resi |
| data.statusLabel | string | label status dari lang |
| data.buyerName, data.courierName | string dan null | nama pembeli dan kurir |
| data.productNames | array | nama produk |
| data.issueCategoryLabel, issueSummary | string | ringkasan masalah |
| data.decisionMessage, decisionCode | string dan null | pesan dan kode keputusan |
| data.qualityInspectionNote | string atau null | catatan mutu admin |
| data.coldChainChip | string atau null | chip suhu bila terukur |
| data.timeline | array | riwayat khusus kendala |
| data.canRequestReplacement | boolean | kelayakan penggantian |
| data.replacementTrackingNumber, replacementUrl | string atau null | paket pengganti |
| data.customerService | objek | telepon dan tautan WhatsApp |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Tanpa laporan kendala | HTTP 200 dengan redirect_url, bukan data kendala |
| Keputusan belum ada | pesan pending dari lang |
| Suhu belum terukur | chip suhu null |
| Resi milik pembeli lain | 404 PACKAGE_NOT_FOUND dari middleware |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Controller | Api/Client/IssueController@show, Client/IssueController@show |
| Service | Services/Client/IssueViewService, ColdChainSummaryService, ClientTimelineBuilder, ReplacementEligibility |
| DTO | DTOs/Client/IssueView.php |
| Lang | resources/js terkait kunci client.issue dan client.status (lang id) |
| Test | tests/Feature/ClientFlowTest.php, tests/Feature/ClientNativeScreensTest.php test_the_issue_screen_shows_the_decision_and_offers_a_resend |
| UI | app/NativeComponents/Client/Issue.php, app/Support/ClientPayload.php method issue |

## 11. Skenario uji

1. Given paket dengan kendala yang sudah diputuskan RETURN, When layar dibuka, Then pesan keputusan terisi dan canRequestReplacement true.
2. Given paket tanpa laporan kendala, When layar dibuka, Then HTTP 200 dengan redirect_url ke tracking.
3. Given kendala yang belum diputuskan, When layar dibuka, Then decisionMessage berisi pesan pending.

## 12. Yang perlu dikonfirmasi

1. Deskripsi asli dari kurir tidak ditampilkan kepada pelanggan; hanya ringkasan per kategori. Apakah itu sesuai harapan kebijakan privasi?
2. Apakah pelanggan perlu melihat siapa yang memutuskan dan kapan keputusan dibuat?

