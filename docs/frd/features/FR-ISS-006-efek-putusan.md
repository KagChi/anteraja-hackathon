# FR-ISS-006 — Efek samping lima jenis putusan

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-006 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Efek samping lima jenis putusan |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Aturan bisnis | BR-002, BR-003, BR-014 |
| Berkas kode | app/Actions/Issues/Decisions/*.php |

## 1. Ringkasan

Setiap keputusan memindahkan paket ke tempat yang berbeda dan memberi tahu pihak
yang berbeda. Lima handler ini adalah satu-satunya tempat efek itu dijalankan,
sehingga perubahan perilaku cukup dilakukan di satu berkas per keputusan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub, dijalankan di dalam transaksi putusan |
| Titik masuk | ResolveIssue yang memilih handler dengan match |

## 3. Trigger

| Keputusan | Handler |
| --- | --- |
| RETURN | ReturnDecisionHandler |
| RESEND | ResendDecisionHandler |
| PROCEED | ProceedDecisionHandler |
| RESCHEDULE | RescheduleDecisionHandler |
| RECHECK | RecheckDecisionHandler |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | keputusan sudah tersimpan dan paket berstatus ISSUE_REPORTED |
| Postcondition | paket berada pada status lanjutan, atau paket pengganti tercipta untuk RESEND |

## 5. Input dan validasi

Data keputusan yang sama: decision, decision_reason, dan quality_inspection_note.

## 6. Proses langkah demi langkah per keputusan

1. RETURN: transisi ISSUE_REPORTED ke RETURNED; kurir menerima instruksi menarik paket ke DC untuk klaim retur; pembeli menerima pesan bahwa pengiriman dibatalkan dan paket dikembalikan.
2. RESEND: transisi ke RETURNED, lalu ReplacementPackageFactory membuat paket pengganti; kurir diberi tahu bahwa resi pengganti tersedia; pembeli menerima pesan bahwa pengiriman ulang disiapkan beserta resi barunya.
3. PROCEED: transisi ke IN_DELIVERY; kurir menerima instruksi melanjutkan pengantaran; pembeli diberi tahu bahwa pengiriman dilanjutkan.
4. RESCHEDULE: transisi ke AWAITING_CONFIRMATION sambil mengosongkan courier_id dan courier_accepted_at; konfirmasi PENDING baru dibuat; kurir lama diberi tahu bahwa penugasan dibatalkan; pembeli menerima template schedule_request.
5. RECHECK: transisi ke CHECKED; kurir menerima instruksi memeriksa ulang kondisi fisik; pembeli diberi tahu bahwa paket sedang diperiksa ulang.

Pada semua handler, notifikasi ke kurir dilewati bila paket tidak punya kurir.

## 7. Output

Handler tidak mengembalikan respons HTTP. Hasilnya terbaca dari status paket,
baris notifications, baris status_logs, dan pada RESEND dari paket baru.

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Paket tanpa kurir | notifikasi kurir dilewati |
| RESEND saat index unik penggantian dilanggar | QueryException tidak ditangkap di handler, berbeda dengan RequestReplacement; ini temuan |
| RESCHEDULE | courier_id dan courier_accepted_at dikosongkan bersamaan dengan transisi |
| PROCEED pada paket tanpa kurir | pembeli tetap menerima notifikasi |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Log status | status_logs per transisi |
| Notifikasi kurir | notifications template issue_decision |
| Notifikasi pembeli | notifications template schedule_request atau issue_decision_update |
| Konfirmasi baru | schedule_confirmations untuk RESCHEDULE |
| Paket pengganti | packages, package_items, schedule_confirmations untuk RESEND |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Handler | Actions/Issues/Decisions/ReturnDecisionHandler.php, ProceedDecisionHandler.php, RecheckDecisionHandler.php, RescheduleDecisionHandler.php, ResendDecisionHandler.php |
| Support | ReplacementPackageFactory, NotificationQueue, PackageStatusTransitioner |
| Test | tests/Feature/WhatsAppNotificationFlowTest.php test_a_recheck_decision_also_updates_the_buyer, tests/Feature/AdminExceptionTest.php, tests/Feature/ClientFlowTest.php |

## 11. Skenario uji

1. Given keputusan PROCEED, When handler selesai, Then paket IN_DELIVERY dan kurir serta pembeli masing-masing menerima satu notifikasi.
2. Given keputusan RESCHEDULE pada paket yang punya kurir, When handler selesai, Then paket AWAITING_CONFIRMATION, courier_id null, dan ada satu konfirmasi PENDING baru.
3. Given keputusan RECHECK, When handler selesai, Then paket CHECKED dan pembeli menerima pesan pemeriksaan ulang.

## 12. Yang perlu dikonfirmasi

1. Apakah notifikasi ke pembeli untuk setiap keputusan sudah memakai bahasa yang tepat, mengingat sebagian pesan dikirim sebagai satu kalimat tetap dari payload handler?
2. Apakah RESEND perlu penanganan duplikat seperti pada RequestReplacement agar tidak pernah muncul sebagai error database?

