# FR-ISS-007 — Kelayakan dan pembuatan paket pengganti

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-007 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Kelayakan dan pembuatan paket pengganti |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Aturan bisnis | BR-007, BR-008, BR-014 |
| Berkas kode | app/Services/Client/ReplacementEligibility.php, app/Support/ReplacementPackageFactory.php |

## 1. Ringkasan

Aturan siapa yang boleh mendapat pengiriman ulang dan bagaimana paket penggantinya
dibentuk. Eligibility memutuskan, factory yang membuat.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | sistem dan admin |
| Pemanggil | ResendDecisionHandler (keputusan admin), RequestReplacement (aplikasi pelanggan) |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Keputusan RESEND | admin memilih kirim ulang |
| Opsi 2 pelanggan | jawaban WhatsApp pada menu penyelesaian |
| Tombol pengiriman ulang | aplikasi pelanggan pada paket yang sudah retur |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket asal berstatus RETURNED atau CLOSED, keputusan kendala terakhir RETURN, dan belum ada paket anak |
| Postcondition | paket pengganti berstatus AWAITING_CONFIRMATION dengan resend_of_package_id menunjuk paket asal |

## 5. Input dan validasi

Tidak ada input pengguna; seluruh data disalin dari paket asal. Paket asal
menentukan buyer_id, created_by, kategori, berat, layanan, koli, instruksi, item
dan jendela pengiriman.

## 6. Proses langkah demi langkah

1. Eligibility mencari paket anak dengan resend_of_package_id sama dengan paket asal. Bila ada, hasilnya tidak layak dengan childPackage terisi.
2. Bila status paket asal bukan RETURNED atau CLOSED, hasilnya tidak layak dengan alasan status belum selesai dikembalikan.
3. Bila keputusan kendala terakhir bukan RETURN, hasilnya tidak layak dengan alasan keputusan bukan pengembalian.
4. Bila ketiganya lolos, hasilnya layak.
5. Factory membuka transaksi.
6. Jendela baru dihitung: jadwal asal ditambah satu hari; bila hasilnya sudah lewat, dipakai waktu sekarang ditambah satu hari pada jam yang sama.
7. Paket baru dibuat berstatus CREATED dengan penyalinan data asal dan resend_of_package_id.
8. Refresh agar tracking_number terisi, lalu salin seluruh item.
9. Transisi CREATED ke AWAITING_CONFIRMATION dengan aktor yang dikirim (admin pada keputusan, null pada permintaan pelanggan).
10. Buat konfirmasi PENDING.
11. Antrekan notifikasi schedule_request ke pembeli berisi jendela baru dan tautan klien.
12. Antrekan notifikasi resend_requested ke setiap admin aktif berisi resi asal dan resi pengganti.
13. Bila auto-approve dimatikan pada konfigurasi, factory tidak dipanggil dan admin hanya menerima notifikasi permintaan.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| isEligible | boolean | hasil kelayakan |
| childPackage | Package atau null | paket pengganti bila sudah ada |
| reason | string atau null | alasan tidak layak |
| paket pengganti | Package | hasil factory |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Paket anak sudah ada | tidak layak, tetapi permintaan mengembalikan paket anak sebagai sukses senyap |
| Balapan permintaan | QueryException 23505 pada index uq_packages_one_replacement ditangkap dan paket anak dikembalikan |
| Status belum terminal | ReplacementNotAllowed dengan alasan status |
| Keputusan bukan RETURN | ReplacementNotAllowed dengan alasan keputusan |
| Auto-approve dimatikan | tanpa paket baru, hanya notifikasi ke admin |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Paket baru dan item | packages, package_items |
| Konfirmasi | schedule_confirmations PENDING |
| Log status | status_logs CREATED ke AWAITING_CONFIRMATION |
| Notifikasi | schedule_request ke pembeli dan resend_requested ke admin |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Service | Services/Client/ReplacementEligibility.php, kelas EligibilityResult |
| Support | Support/ReplacementPackageFactory.php |
| Action | Actions/Client/RequestReplacement.php, Actions/Issues/Decisions/ResendDecisionHandler.php |
| Migrasi | 0001_01_01_000022_make_replacement_index_unique.php |
| Konfigurasi | config/coldchain.php client.resend_auto_approve |
| Test | tests/Unit/ClientUnitTest.php test_replacement_eligibility_enforces_business_rules, tests/Feature/ClientFlowTest.php test_issue_replacement_creates_child_package_and_handles_double_tap, tests/Feature/WhatsAppNotificationFlowTest.php test_resend_option_creates_replacement_only_when_eligible, test_resend_option_creates_replacement_when_package_is_returned |

## 11. Skenario uji

1. Given paket RETURNED dengan keputusan RETURN dan belum punya pengganti, When penggantian diminta, Then paket baru AWAITING_CONFIRMATION dibuat dengan resend_of_package_id terisi.
2. Given paket yang masih IN_DELIVERY, When penggantian diminta, Then ReplacementNotAllowed dan tidak ada paket baru.
3. Given paket yang sudah punya pengganti, When penggantian diminta lagi, Then paket anak yang ada dikembalikan tanpa paket baru.

## 12. Yang perlu dikonfirmasi

1. Jendela pengganti selalu satu hari setelah jadwal asal, tanpa mempertimbangkan ketersediaan armada. Apakah perlu pemilihan jendela?
2. Bila auto-approve dimatikan, tidak ada endpoint untuk menyetujui permintaan penggantian secara manual. Apakah alur manual memang diperlukan?
3. Apakah paket pengganti perlu diberi penanda harga atau kompensasi?

