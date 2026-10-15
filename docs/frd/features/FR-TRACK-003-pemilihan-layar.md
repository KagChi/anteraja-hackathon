# FR-TRACK-003 — Pemilihan layar otomatis

| Properti | Nilai |
| --- | --- |
| ID | FR-TRACK-003 |
| Modul | TRACK — Pelacakan Publik dan Tautan Tamu |
| Judul | Pemilihan layar otomatis |
| Status | Live |
| Berkas modul | docs/frd/09-pelacakan.md |
| Berkas kode | app/Services/Client/ClientScreenResolver.php, app/Services/Client/OrderPresenter.php |

## 1. Ringkasan

Pemegang tautan tidak perlu memilih menu. Sistem menentukan apakah ia harus
mengonfirmasi jadwal, melihat kendala, atau hanya memantau perjalanan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | guest pemegang tautan dan pembeli pemilik paket |
| Middleware | client.package atau client.owned |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | GET /api/guest/{token}, GET /api/client/{token} |
| Controller | Api/Client/LinkController@resolve, Client/EntryController |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket sudah diresolusi middleware |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Bila status AWAITING_CONFIRMATION, layar yang dipilih adalah schedule.
2. Bila status ISSUE_REPORTED atau RETURNED dan paket punya laporan kendala, layar yang dipilih adalah issue.
3. Selain ketentuan di atas, layar yang dipilih adalah tracking.
4. OrderPresenter menambahkan penentuan aksi tertunda: confirm_schedule untuk AWAITING_CONFIRMATION tanpa konfirmasi READY atau RESCHEDULE_REQUESTED, request_replacement untuk RETURNED atau CLOSED yang layak penggantian, view_issue untuk status kendala yang punya laporan, dan track untuk IN_DELIVERY.
5. Jalur guest mengembalikan next_screen beserta ringkasan pesanan; jalur legacy mengembalikan screen beserta redirect_url ke tautan layar yang sesuai.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| next_screen atau screen | string | schedule, issue, atau tracking |
| order | objek | OrderSummaryResource pada jalur guest |
| redirect_url | string | tautan layar pada jalur legacy |
| action_required | string atau null | langkah yang tertunda, dikirim di dalam ringkasan pesanan |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| RETURNED tanpa laporan kendala | diarahkan ke tracking, bukan issue |
| RETURNED dengan keputusan bukan RETURN | tidak ada aksi penggantian |
| Paket sudah punya pengganti | penggantian tidak ditawarkan lagi |
| AWAITING_CONFIRMATION tetapi konfirmasi sudah dijawab | aksi confirm_schedule tidak lagi ditawarkan |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Service | Services/Client/ClientScreenResolver.php, Services/Client/OrderPresenter.php |
| Enum | Enums/Client/ClientScreen.php |
| Controller | Api/Client/LinkController@resolve, Client/EntryController |
| Test | tests/Unit/ClientUnitTest.php test_screen_resolver_maps_statuses_correctly, tests/Feature/ClientFlowTest.php test_entry_resolves_appropriate_screen_and_redirect_url, tests/Feature/WhatsappLookupTest.php test_it_maps_every_status_to_the_pending_step |

## 11. Skenario uji

1. Given paket AWAITING_CONFIRMATION, When tautan dibuka, Then next_screen bernilai schedule.
2. Given paket ISSUE_REPORTED tanpa laporan kendala, When tautan dibuka, Then next_screen bernilai tracking.
3. Given paket RETURNED dengan keputusan RETURN dan belum ada pengganti, When tautan dibuka, Then action_required bernilai request_replacement.

## 12. Yang perlu dikonfirmasi

1. Tidak ada layar khusus untuk paket CLOSED; apakah perlu layar ringkasan selesai?
2. Apakah pemilihan layar perlu memperhitungkan kendala yang sudah diputuskan agar pelanggan diarahkan ke ringkasan keputusan, bukan tracking biasa?

