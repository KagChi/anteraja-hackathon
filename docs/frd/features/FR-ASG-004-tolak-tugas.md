# FR-ASG-004 — Menolak tugas

| Properti | Nilai |
| --- | --- |
| ID | FR-ASG-004 |
| Modul | ASG — Penugasan Kurir dan Siklus Tugas |
| Judul | Menolak tugas |
| Status | Live |
| Berkas modul | docs/frd/03-penugasan-kurir.md |
| Aturan bisnis | BR-002, BR-003, BR-004 |
| Berkas kode | app/Actions/Courier/RejectTask.php |

## 1. Ringkasan

Kurir melepas tugas sebelum ia menerimanya. Paket kembali ke antrean hub, dan
seluruh admin aktif diberi tahu agar dapat menugaskan kurir lain.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tombol tolak pada kartu tugas baru |
| Endpoint | POST /api/courier/tasks/{tracking_number}/reject |
| Nama route | api.courier.tasks.reject |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus COURIER_ASSIGNED dan belum diterima (courier_accepted_at null) |
| Postcondition | paket kembali ke CONFIRMED tanpa kurir dan siap ditugaskan ulang |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| reason | string | nullable, max 200 |

## 6. Proses langkah demi langkah

1. PackagePolicy memastikan kepemilikan paket.
2. Action membuka DB::transaction dan mengunci baris paket.
3. Bila courier_id bukan kurir ini atau status bukan COURIER_ASSIGNED, lempar TaskNotActionable::notAssignable.
4. Bila courier_accepted_at sudah terisi, lempar TaskNotActionable::alreadyAccepted sehingga kurir tidak bisa menolak setelah menerima.
5. Transisi ke CONFIRMED dengan aktor kurir dan deskripsi alasan yang dikirim, atau teks bawaan "Kurir menolak penugasan".
6. Kosongkan courier_id dan courier_accepted_at.
7. Antrekan notifikasi task_rejected ke setiap admin aktif berisi resi, nama kurir, dan alasan.
8. Kembalikan paket.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Tugas {resi} telah ditolak." |
| package | objek | hasil fresh dengan statusRef |
| package.status | string | CONFIRMED |
| package.courier_id | null | sudah dilepas |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Menolak setelah menerima | TaskNotActionable::alreadyAccepted | belum dipetakan ke kode khusus |
| Paket milik kurir lain | 404 | PackagePolicy |
| Alasan lebih dari 200 karakter | 422 | validasi |
| Alasan kosong | 200 | teks bawaan dipakai |
| Tidak ada admin aktif | 200 | tidak ada notifikasi yang dibuat |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Log status | status_logs COURIER_ASSIGNED ke CONFIRMED |
| courier_id dan courier_accepted_at dikosongkan | packages |
| Notifikasi | notifications template task_rejected per admin aktif |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier tasks |
| Controller | Courier/RejectTaskController |
| Action | Actions/Courier/RejectTask |
| FormRequest | Requests/Courier/RejectTaskRequest.php |
| Support | NotificationQueue::queueTaskRejected, PackageStatusTransitioner |
| Test | tests/Feature/CourierFlowTest.php test_courier_reject_task_flow |
| UI | app/NativeComponents/Courier/Tasks.php |

## 11. Skenario uji

1. Given tugas belum diterima, When kurir menolak dengan alasan, Then HTTP 200, paket CONFIRMED, courier_id null, dan admin menerima notifikasi task_rejected.
2. Given tugas yang sudah diterima, When kurir menolak, Then permintaan ditolak dan status tidak berubah.
3. Given alasan 250 karakter, When tolak dikirim, Then HTTP 422.

## 12. Yang perlu dikonfirmasi

1. Penolakan tidak dihitung atau dibatasi; kurir dapat menolak berapa pun tugas. Apakah perlu pencatatan performa kurir?
2. Apakah paket yang ditolak berulang kali perlu ditandai agar hub tahu ada masalah pada rute atau alamat?

