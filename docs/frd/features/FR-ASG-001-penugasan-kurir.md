# FR-ASG-001 — Penugasan kurir

| Properti | Nilai |
| --- | --- |
| ID | FR-ASG-001 |
| Modul | ASG — Penugasan Kurir dan Siklus Tugas |
| Judul | Penugasan kurir |
| Status | Live |
| Berkas modul | docs/frd/03-penugasan-kurir.md |
| Aturan bisnis | BR-002, BR-003, BR-004 |
| Berkas kode | app/Actions/Packages/AssignCourier.php |

## 1. Ringkasan

Satu-satunya jalan paket berpindah dari CONFIRMED ke COURIER_ASSIGNED. Aksi ini
mengunci baris paket, memastikan kurir yang dipilih benar-benar kurir aktif, lalu
mengirim ringkasan tugas ke kurir tersebut.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |
| Aktor transisi | admin yang menekan tombol |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | CourierPickerDialog dari modal detail atau baris dengan availableAction assign_courier |
| Endpoint | POST /api/admin/shipments/{tracking_number}/assign |
| Nama route | api.admin.shipments.assign |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus CONFIRMED dan courier_id masih null |
| Postcondition | paket berstatus COURIER_ASSIGNED dengan courier_id terisi dan courier_accepted_at null |

## 5. Input dan validasi

| Field | Tipe | Aturan | Keterangan |
| --- | --- | --- | --- |
| courier_id | integer | required, exists staff.id dengan role COURIER dan is_active true | aturan ditulis sebagai Rule::exists dengan klausa where |
| tracking_number | string path | dicari firstOrFail | - |

## 6. Proses langkah demi langkah

1. Controller mencari paket dan kurir, lalu memanggil Action dengan keduanya dan admin.
2. Action membuka DB::transaction.
3. Baris paket dikunci dengan lockForUpdate (BR-004).
4. Bila status bukan CONFIRMED atau courier_id sudah terisi, lempar PackageNotAssignable.
5. Bila staf yang dipilih bukan kurir aktif menurut isCourier, lempar PackageNotAssignable dengan pesan bahwa staf bukan kurir aktif.
6. Transisi ke COURIER_ASSIGNED dengan atribut courier_id dan courier_accepted_at null.
7. Muat relasi items.product agar ringkasan barang dapat disusun.
8. Susun ringkasan item berupa daftar nama dan kuantitas, dan label jendela memakai DisplayTime::windowLabel.
9. Antrekan notifikasi template task_assigned untuk kurir berisi tracking_number, scheduled_window, handling_instructions, dan items.
10. Kembalikan paket.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Kurir {nama} berhasil ditugaskan untuk resi {resi}." |
| package | objek | hasil fresh dengan courier dan statusRef |
| package.status | string | COURIER_ASSIGNED |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| courier_id bukan kurir aktif | 422 | aturan exists gagal |
| courier_id bukan staf | 422 | aturan exists gagal |
| Paket sudah punya kurir | 200 dari controller tetapi Action melempar PackageNotAssignable | perlu dipetakan |
| Paket berstatus selain CONFIRMED | sama seperti di atas | perlu dipetakan |
| Resi tidak ada | 404 | firstOrFail |
| Dua admin menekan bersamaan | satu berhasil, satu gagal | karena lockForUpdate pada baris paket |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| courier_id dan courier_accepted_at | packages |
| Log status | status_logs CONFIRMED ke COURIER_ASSIGNED |
| Notifikasi | notifications template task_assigned untuk kurir |
| Job | DispatchNotificationToN8n setelah commit |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin shipments |
| Controller | Admin/ShipmentAssignmentController |
| Action | Actions/Packages/AssignCourier |
| FormRequest | Requests/Admin/AssignCourierRequest.php |
| Model | Package, Staff method isCourier |
| Support | PackageStatusTransitioner, NotificationQueue, DisplayTime |
| Test | tests/Feature/AdminShipmentTest.php test_admin_can_assign_courier |
| UI | resources/js/components/CourierPickerDialog.tsx |

## 11. Skenario uji

1. Given paket CONFIRMED tanpa kurir dan satu kurir aktif, When assign dipanggil, Then HTTP 200, paket COURIER_ASSIGNED, dan notifikasi task_assigned terantre.
2. Given courier_id milik staf nonaktif, When assign dipanggil, Then HTTP 422 dan paket tidak berubah.
3. Given paket yang sudah COURIER_ASSIGNED, When assign dipanggil lagi, Then permintaan ditolak dan courier_id tidak berubah.

## 12. Yang perlu dikonfirmasi

1. Pesan kegagalan PackageNotAssignable belum dipetakan ke kode HTTP 422, sehingga frontend mungkin menampilkan error umum. Perlu dipetakan?
2. Apakah penugasan perlu memperhitungkan beban kerja kurir (jumlah tugas aktif) atau zona alamat?
3. Notifikasi task_assigned dikirim lewat n8n ke nomor kurir. Bila kurir tidak punya nomor WhatsApp, apakah ada jalur lain?

