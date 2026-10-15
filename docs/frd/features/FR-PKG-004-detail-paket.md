# FR-PKG-004 — Detail paket dan timeline

| Properti | Nilai |
| --- | --- |
| ID | FR-PKG-004 |
| Modul | PKG — Paket dan FSM Status |
| Judul | Detail paket dan timeline |
| Status | Live |
| Berkas modul | docs/frd/02-paket-fsm.md |
| Berkas kode | app/Http/Controllers/Admin/ShipmentController.php, app/Support/PackageTimelineBuilder.php |

## 1. Ringkasan

Modal detail adalah tempat admin memahami satu paket: apa isinya, siapa kurirnya
apa yang sudah terjadi, dan foto bukti apa yang tersimpan. Endpoint ini juga
menyediakan tautan klien agar admin dapat mengirim ulang tautan ke pelanggan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |
| Batasan route | parameter terikat pada kolom tracking_number (route model binding manual) |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | resources/js/components/ShipmentDetailModal.tsx |
| Endpoint | GET /api/admin/shipments/{tracking_number} |
| Nama route | api.admin.shipments.show |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket dengan resi itu ada |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| tracking_number | string pada path | dicari dengan where tracking_number, firstOrFail |

## 6. Proses langkah demi langkah

1. Ambil paket dengan eager load buyer, courier, statusRef, items.product, delivery, conditionChecks beserta issueReport dan decidedByStaff, notifications, dan statusLogs beserta changedByStaff. Tujuannya mencegah N+1.
2. Bangun timeline memakai PackageTimelineBuilder.
3. Ambil condition check pertama yang punya laporan kendala, untuk menyediakan foto kendala.
4. Susun label status: label dari package_statuses, dan bila status ISSUE_REPORTED serta kategori ada, label diganti "Kendala (kategori)".
5. Susun tautan klien memakai ClientLinkService::entry, yang memilih bentuk /api/client/{token} bila token ada atau /api/client/orders/{resi} bila tidak.
6. Ubah foto bukti dan foto kendala menjadi URL sementara melalui MediaStorage, yang jatuh ke /api/media/preview bila disk tidak mendukung URL sementara.
7. Balas seluruh data.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| package | objek | model lengkap termasuk statusLogs dan notifications |
| timeline | array | langkah kronologis |
| client_url | string | tautan berbasis config app.url, bukan host pemanggil |
| scheduleLabel | string | label jendela WIB |
| scheduledDate | string | tanggal format Y-m-d waktu Jakarta |
| statusLabel | string | label, atau "Kendala (kategori)" |
| tone | string | warna semantik status |
| proofPhotoUrl | string atau null | URL foto bukti |
| issuePhotoUrl | string atau null | URL foto kendala |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Resi tidak ada | 404 | dari firstOrFail |
| Paket tanpa bukti pengantaran | 200 | proofPhotoUrl null |
| Paket tanpa kendala | 200 | issuePhotoUrl null dan label status normal |
| Foto tersimpan di disk lokal | 200 | URL berbentuk /api/media/preview?path=... |

## 9. Efek samping

Tidak ada penulisan. Respons menyertakan seluruh notifications paket, sehingga
payload notifikasi ikut terlihat oleh admin.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin shipments |
| Controller | Admin/ShipmentController@show |
| Support | PackageTimelineBuilder, MediaStorage, DisplayTime, ClientLinkService |
| Model | Package, ConditionCheck, IssueReport, StatusLog, Notification, Delivery |
| Test | tests/Feature/AdminConsoleWiringTest.php test_the_shipment_detail_carries_the_proof_photos_and_labels, test_a_stored_photo_resolves_to_the_preview_route |
| UI | resources/js/components/ShipmentDetailModal.tsx |

## 11. Skenario uji

1. Given paket RECEIVED dengan bukti pengantaran, When detail diminta, Then proofPhotoUrl terisi dan bukan null.
2. Given resi yang tidak ada, When detail diminta, Then HTTP 404.
3. Given paket ISSUE_REPORTED dengan kategori kemasan_bocor, When detail diminta, Then statusLabel berbunyi "Kendala (Kemasan bocor)".

## 12. Yang perlu dikonfirmasi

1. Respons memuat seluruh baris notifications beserta payload, termasuk nomor telepon penerima. Apakah itu perlu untuk modal, atau cukup ringkasan template dan status?
2. timeline berasal dari PackageTimelineBuilder yang belum diuji khusus; perlu dipastikan urutannya sesuai harapan admin.

