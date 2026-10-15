# FR-ISS-009 — Mencatat pilihan penyelesaian pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-009 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Mencatat pilihan penyelesaian pelanggan |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Aturan bisnis | BR-004, BR-008, BR-025 |
| Berkas kode | app/Actions/Client/RecordIssueChoice.php |

## 1. Ringkasan

Pelanggan menerima menu empat opsi di WhatsApp. Jawabannya dicatat sebagai masukan
keputusan admin, dan bila memilih pengiriman ulang, sistem langsung membuat paket
pengganti asalkan syarat retur terpenuhi.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | guest pemegang tautan token atau pembeli pemilik paket |
| Middleware | client.package untuk jalur tautan, client.owned untuk jalur akun |
| Pemanggil sebenarnya | workflow n8n inbound setelah pelanggan menjawab angka 1 sampai 4 |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | POST /api/guest/{token}/issue/choice dan POST /api/client/{token}/issue/choice |
| Nama route | api.guest.issue.choice, api.client.issue.choice |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket punya laporan kendala |
| Postcondition | kolom buyer_choice dan buyer_responded_at terisi; admin menerima notifikasi; bila opsi 2 dan layak, paket pengganti dibuat |

## 5. Input dan validasi

| Field | Tipe | Aturan | Pemetaan |
| --- | --- | --- | --- |
| choice | integer | required, between 1 dan 4 | 1 RETURN, 2 RESEND, 3 PROCEED, 4 DISCUSS |

Nilai di luar rentang ditolak 422 sebelum Action dipanggil. Pemetaan yang tidak
dikenal jatuh ke DISCUSS di dalam FormRequest.

## 6. Proses langkah demi langkah

1. Action membuka transaksi dan mengunci baris paket.
2. Ambil laporan kendala terbaru untuk paket itu dengan lockForUpdate.
3. Bila laporan tidak ada, lempar IssueNotReported.
4. Bila laporan sudah punya pilihan, kembalikan pilihan lama dengan penanda already_recorded true, dan tetap evaluasi pembuatan pengganti.
5. Bila belum, simpan buyer_choice dan buyer_responded_at.
6. Kirim notifikasi buyer_issue_choice ke setiap admin aktif berisi resi, nilai pilihan, labelnya, dan kategori kendala.
7. Bila pilihan RESEND, periksa kelayakan retur; buat paket pengganti hanya bila layak atau sudah ada anaknya.
8. Kembalikan pilihan, penanda, dan paket pengganti bila ada.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| success | boolean | true |
| message | string | "Pilihan penyelesaian kendala berhasil dicatat." |
| data.choice | string | nilai enum pilihan |
| data.choice_label | string | label Indonesia |
| data.already_recorded | boolean | true bila jawaban berulang |
| data.replacement_tracking_number | string atau null | resi pengganti |
| data.replacement_url | string atau null | tautan jadwal pengganti |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Paket tanpa laporan kendala | permintaan gagal dengan IssueNotReported |
| Opsi di luar 1 sampai 4 | 422 |
| Jawaban berulang | pilihan pertama menang, already_recorded true |
| Opsi 2 pada paket belum retur | pilihan tetap dicatat, paket pengganti tidak dibuat |
| Opsi 2 pada paket yang sudah retur | paket pengganti dibuat atau yang ada dikembalikan |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Pilihan pembeli | issue_reports.buyer_choice dan buyer_responded_at |
| Notifikasi admin | notifications template buyer_issue_choice per admin aktif |
| Paket pengganti | packages dan turunannya bila opsi 2 dan layak |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup guest dan legacy |
| Controller | Api/Client/IssueChoiceController |
| Action | Actions/Client/RecordIssueChoice |
| FormRequest | Requests/Client/IssueChoiceRequest.php |
| Enum | Enums/BuyerIssueChoice.php |
| Service | Services/Client/ReplacementEligibility |
| Test | tests/Feature/WhatsAppNotificationFlowTest.php test_buyer_can_pick_return_option_from_whatsapp, test_first_buyer_choice_wins_when_reply_is_repeated, test_issue_choice_rejects_unknown_option, test_issue_choice_requires_an_issue_report |
| Dokumentasi | deploy/n8n/README.md tabel last_template dan issue_options |

## 11. Skenario uji

1. Given kendala tanpa pilihan, When pelanggan mengirim 2, Then pilihan RESEND tersimpan, already_recorded false, dan admin menerima notifikasi.
2. Given kendala yang sudah memilih RETURN, When pelanggan mengirim 3, Then respons mengembalikan RETURN dengan already_recorded true dan kolom tidak berubah.
3. Given paket tanpa laporan kendala, When pilihan dikirim, Then permintaan gagal.
4. Given opsi 5, When dikirim, Then HTTP 422.

## 12. Yang perlu dikonfirmasi

1. Opsi 3 dan 4 hanya dicatat tanpa efek otomatis; apakah perlu aksi lanjutan untuk diskusi, misalnya penugasan ke admin tertentu?
2. Opsi 2 hanya membuat paket pengganti bila paket sudah retur; bila belum, apakah pelanggan perlu diberi tahu bahwa permintaannya menunggu keputusan admin?

