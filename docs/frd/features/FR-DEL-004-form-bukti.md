# FR-DEL-004 — Form bukti serah terima

| Properti | Nilai |
| --- | --- |
| ID | FR-DEL-004 |
| Modul | DEL — Pengantaran dan Bukti Serah Terima |
| Judul | Form bukti serah terima |
| Status | Live |
| Berkas modul | docs/frd/04-pengantaran-bukti.md |
| Berkas kode | app/Http/Controllers/Courier/ProofController.php |

## 1. Ringkasan

Menyediakan data yang dibutuhkan layar bukti serah terima: resi, layanan, nama
penerima, daftar hubungan penerima, dan placeholder catatan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize update |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tombol bukti serah terima atau tombol kirim bukti dari layar pengantaran |
| Endpoint | GET /api/courier/deliveries/{tracking_number}/proof |
| Nama route | api.courier.proof.create |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus IN_DELIVERY |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| tracking_number | string path | dicari firstOrFail |

## 6. Proses langkah demi langkah

1. Ambil paket dengan relasi buyer.
2. Policy memastikan kepemilikan.
3. Bila status bukan IN_DELIVERY, balas 422 dengan pesan paket tidak dalam proses pengantaran aktif dan redirect ke layar pengantaran paket itu.
4. Susun proofForm berisi tracking, service, recipient, relations (empat label teks), relationOptions (pasangan value dan label dari RecipientRelation::options), dan notesPlaceholder.

Dua bentuk daftar hubungan disertakan karena layar lama membaca label teks,
sedangkan layar baru membaca pasangan nilai dan label.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| proofForm.tracking | string | resi |
| proofForm.service | string | label layanan |
| proofForm.recipient | string | nama penerima |
| proofForm.relations | array | empat label Indonesia |
| proofForm.relationOptions | array | pasangan value dan label |
| proofForm.notesPlaceholder | string | contoh catatan |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Status bukan IN_DELIVERY | 422 | menyertakan redirect |
| Paket milik kurir lain | 404 | PackagePolicy |
| Pembeli tidak ada | 200 | recipient berupa string kosong |

## 9. Efek samping

Tidak ada.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier deliveries |
| Controller | Courier/ProofController@create |
| Enum | Enums/RecipientRelation termasuk method options dan fromInput |
| Test | tests/Feature/CourierFlowTest.php test_courier_proof_create_and_store_flow, tests/Feature/CourierNativeScreensTest.php test_the_hand_over_screen_shows_the_device_accuracy |
| UI | app/NativeComponents/Courier/Proof.php, app/Support/CourierPayload.php method proofForm |

## 11. Skenario uji

1. Given paket IN_DELIVERY, When form bukti diminta, Then relationOptions memuat empat pasangan nilai dan label.
2. Given paket berstatus COURIER_ASSIGNED, When form diminta, Then HTTP 422 dengan redirect dan tanpa data form.

## 12. Yang perlu dikonfirmasi

1. Daftar hubungan penerima bersifat tetap, tanpa opsi "orang lain" dengan deskripsi bebas. Apakah cukup untuk kasus sekuriti atau tetangga?
2. notesPlaceholder dikirim dari server sebagai teks contoh; apakah lebih baik menjadi teks statis di aplikasi?

