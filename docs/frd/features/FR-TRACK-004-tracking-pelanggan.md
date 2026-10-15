# FR-TRACK-004 — Data tracking pelanggan

| Properti | Nilai |
| --- | --- |
| ID | FR-TRACK-004 |
| Modul | TRACK — Pelacakan Publik dan Tautan Tamu |
| Judul | Data tracking pelanggan |
| Status | Live |
| Berkas modul | docs/frd/09-pelacakan.md |
| Berkas kode | app/Services/Client/TrackingViewService.php |

## 1. Ringkasan

Layar utama pelanggan selama paket berjalan: status, perkiraan kedatangan, kartu
cold chain, timeline, dan akses menghubungi kurir.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | pembeli pemilik paket atau guest pemegang tautan |
| Middleware | client.owned atau client.package |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Endpoint | GET /api/client/orders/{tracking_number}/tracking, GET /api/guest/{token}/tracking, GET /api/client/{token}/tracking |
| UI | app/NativeComponents/Client/Tracking.php |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket dapat diakses pemanggil |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Susun label status dari lang client.status.
2. Tentukan apakah tombol menghubungi kurir tersedia: hanya bila status IN_DELIVERY dan kurir ada. Bila ya, sertakan nomor kurir.
3. Tentukan kartu cold chain: disembunyikan untuk CREATED, AWAITING_CONFIRMATION, RESCHEDULE_REQUESTED, dan CONFIRMED; selainnya dihitung dari pemeriksaan terakhir.
4. Susun banner kendala untuk ISSUE_REPORTED dan RETURNED beserta tautan ke layar kendala.
5. Susun banner jadwal ulang untuk RESCHEDULE_REQUESTED sebagai teks biasa.
6. Susun perkiraan kedatangan dari jadwal dalam zona Jakarta, memakai tanggal berbahasa Indonesia dan jendela jam.
7. Susun ringkasan produk: nama produk pertama ditambah jumlah lainnya, serta koli dan berat dalam gram.
8. Bangun timeline memakai ClientTimelineBuilder untuk mode tracking.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| data.trackingNumber | string | resi |
| data.status, statusLabel, statusTone | string | status dan tampilannya |
| data.estimatedDelivery | string | perkiraan kedatangan |
| data.buyerName, buyerArea | string | identitas penerima |
| data.courierName | string atau null | nama kurir |
| data.coolerBoxCode | string atau null | kode cooler box |
| data.coldChain | objek atau null | ringkasan suhu dan keadaan rantai |
| data.timeline | array | riwayat perjalanan |
| data.canContactCourier, courierPhone | boolean dan string atau null | akses menghubungi |
| data.issueBanner, rescheduleBanner | objek dan string atau null | pemberitahuan |
| data.productName, productMeta | string | ringkasan isi paket |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Status pra-kurir | coldChain null, layar harus menangani ketiadaan kartu |
| Paket tanpa jadwal | estimatedDelivery kosong |
| Paket tanpa kurir | tombol kontak tidak tersedia |
| Kendala sudah diputuskan | banner kendala tetap muncul selama status masih terminal kendala |

## 9. Efek samping

Tidak ada penulisan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Service | Services/Client/TrackingViewService.php, ColdChainSummaryService.php, ClientTimelineBuilder.php |
| DTO | DTOs/Client/TrackingView.php, TimelineEntry.php, ColdChainSummary.php |
| Test | tests/Feature/ClientFlowTest.php test_tracking_returns_formatted_data_and_client_status_labels, tests/Unit/ClientUnitTest.php test_cold_chain_summary_evaluates_measured_and_target_states, tests/Feature/ClientNativeScreensTest.php test_tracking_shows_the_cold_chain_reading_and_the_journey, test_tracking_calls_the_courier, test_tracking_offers_the_action_its_state_calls_for |
| UI | app/NativeComponents/Client/Tracking.php, resources/js/pages/client/Tracking.tsx |

## 11. Skenario uji

1. Given paket IN_DELIVERY dengan kurir dan suhu optimal, When layar diminta, Then canContactCourier true dan kartu cold chain berisi suhu serta keadaan Terjaga.
2. Given paket CONFIRMED, When layar diminta, Then coldChain null dan canContactCourier false.
3. Given suhu di luar rentang -25 sampai -18, When layar diminta, Then keadaan rantai PerluPerhatian.

## 12. Yang perlu dikonfirmasi

1. Kartu cold chain hanya memakai pemeriksaan terakhir. Apakah pelanggan perlu melihat riwayat suhu, bukan hanya satu angka?
2. Nomor kurir hanya tersedia saat IN_DELIVERY. Apakah pelanggan perlu jalur menghubungi hub sebelum pengantaran dimulai?

