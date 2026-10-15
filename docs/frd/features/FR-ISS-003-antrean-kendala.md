# FR-ISS-003 — Antrean kendala admin

| Properti | Nilai |
| --- | --- |
| ID | FR-ISS-003 |
| Modul | ISS — Laporan Kendala, Keputusan, dan Penggantian |
| Judul | Antrean kendala admin |
| Status | Live |
| Berkas modul | docs/frd/06-kendala-penggantian.md |
| Berkas kode | app/Http/Controllers/Admin/ExceptionController.php, app/Queries/ExceptionIndexQuery.php |

## 1. Ringkasan

Daftar kendala dengan dua tab. Tab aktif mengurutkan dari yang terlama agar tidak
ada paket yang tertinggal, sedangkan tab selesai mengurutkan dari yang terbaru.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tab kendala pada halaman shipment |
| Endpoint | GET /api/admin/exceptions |
| Nama route | api.admin.exceptions.index |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Perilaku |
| --- | --- | --- |
| tab | string | nilai resolved memilih tab selesai; nilai lain dianggap active |

## 6. Proses langkah demi langkah

1. Bangun kueri IssueReport dengan eager load check.package.buyer, check.package.statusRef, check.courier, dan decidedByStaff.
2. Tab aktif menyaring decision null dan mengurutkan reported_at menaik.
3. Tab selesai menyaring decision bukan null dan mengurutkan reported_at menurun.
4. Paginasi 10 baris dengan withQueryString.
5. Ubah setiap baris: kategori null diganti KEMASAN_BOCOR, waktu laporan diformat jam WIB dan tanggal berlabel, dan resolutionStatus dihitung dari IssueReport::resolutionStatus.
6. Balas tab, issues, dan paginator.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| tab | string | active atau resolved |
| issues[].id | integer | id laporan |
| issues[].tracking | string | resi atau "-" |
| issues[].buyer | string | nama pembeli |
| issues[].courier | string | nama kurir pelapor |
| issues[].category | string | label kategori |
| issues[].categoryKey | string | nilai kategori |
| issues[].description | string | deskripsi dari kurir |
| issues[].decision | string atau null | nilai keputusan |
| issues[].decision_label | string atau null | label keputusan |
| issues[].reported_at | string | jam WIB |
| issues[].reported_date | string | tanggal berlabel |
| issues[].resolutionStatus | string | Menunggu Keputusan, Selesai, Dilanjutkan, Proses Kirim Ulang, Dijadwalkan, atau Pemeriksaan Ulang |
| paginator | objek | total, per_page, current_page, last_page |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Tidak ada kendala | daftar kosong dengan paginator nol |
| Kategori null | diganti kategori bawaan agar tampilan tidak kosong |
| Keputusan PROCEED pada paket IN_DELIVERY | status resolusi Dilanjutkan, bukan Selesai |
| Keputusan RESEND tanpa resi pengganti terkirim | status resolusi Proses Kirim Ulang |

## 9. Efek samping

Tidak ada penulisan. Status resolusi dihitung dengan aturan: RETURN selesai
PROCEED dan RESCHEDULE selesai hanya bila paket RECEIVED atau CLOSED, RESEND
selesai hanya bila paket pengganti RECEIVED atau CLOSED, dan RECHECK selalu
Pemeriksaan Ulang.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin exceptions |
| Controller | Admin/ExceptionController@index |
| Query | Queries/ExceptionIndexQuery |
| Model | IssueReport::resolutionStatus, Enum ResolutionStatus |
| Test | tests/Feature/AdminExceptionTest.php test_admin_can_view_exceptions_list |
| UI | resources/js/pages/admin/Shipment.tsx |

## 11. Skenario uji

1. Given satu kendala belum diputuskan dan satu sudah diputuskan, When tab aktif diminta, Then hanya kendala belum diputuskan yang muncul.
2. Given keputusan RESEND dengan paket pengganti yang masih berjalan, When daftar diminta, Then status resolusinya Proses Kirim Ulang.
3. Given kategori null, When daftar diminta, Then label kategori berbunyi Kemasan bocor.

## 12. Yang perlu dikonfirmasi

1. Fokus pada modal shipment berarti admin tidak memiliki halaman kendala tersendiri. Apakah itu cukup ketika kendala menumpuk?
2. Apakah perlu penanda prioritas berdasarkan kategori, misalnya suhu naik lebih mendesak daripada COD ditolak?

