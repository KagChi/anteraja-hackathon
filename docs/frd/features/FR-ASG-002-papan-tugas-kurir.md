# FR-ASG-002 — Papan tugas kurir

| Properti | Nilai |
| --- | --- |
| ID | FR-ASG-002 |
| Modul | ASG — Penugasan Kurir dan Siklus Tugas |
| Judul | Papan tugas kurir |
| Status | Live |
| Berkas modul | docs/frd/03-penugasan-kurir.md |
| Aturan bisnis | BR-020 |
| Berkas kode | app/Queries/CourierTaskQuery.php |

## 1. Ringkasan

Layar pertama aplikasi kurir. Memisahkan tugas yang belum diterima dari tugas yang
sedang berjalan, dan memberi tahu seberapa mendesak jendela pengantarannya.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir terautentikasi |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Pembatasan data | hanya paket dengan courier_id sama dengan id kurir yang login |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tab tugas aplikasi kurir, app/NativeComponents/Courier/Tasks.php |
| Endpoint | GET /api/courier/tasks |
| Nama route | api.courier.tasks.index |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | token kurir valid |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Saring paket dengan courier_id milik kurir dan status salah satu COURIER_ASSIGNED, CHECKED, IN_DELIVERY, ISSUE_REPORTED.
2. Tambahkan dua subquery korelatif: id log terakhir dengan to_status COURIER_ASSIGNED dan waktu log itu, agar usia penugasan tidak bergantung pada kolom paket.
3. Eager load buyer, statusRef, items.product, dan latestMilestone.
4. Urutkan berdasarkan scheduled_at menaik dengan NULLS LAST, lalu id menaik.
5. Untuk setiap paket, hitung ScheduleTiming::forWindow terhadap scheduled_end_at sehingga labelnya Tepat Waktu, Segera, atau Terlambat.
6. Hitung deliveryStage dari milestone terakhir.
7. Tandai isNew bila status COURIER_ASSIGNED dan courier_accepted_at null.
8. Pisahkan hasil menjadi dua koleksi: newTasks berisi yang bertanda isNew, activeTasks sisanya.
9. Balas newTasks, activeTasks, dan identitas kurir.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| newTasks | array | tugas belum diterima |
| activeTasks | array | tugas yang sudah berjalan |
| courier.name | string | nama kurir |
| courier.status | string | Aktif atau Nonaktif |
| task.id | string | bentuk KRR- diikuti id log penugasan |
| task.package_id | integer | id paket asli |
| task.age | string | usia penugasan dalam bahasa Indonesia |
| task.tracking | string | resi |
| task.service | string | label layanan |
| task.serviceKey | string | REGULER atau INSTANT_FROZEN |
| task.window | string | label jendela WIB |
| task.windowStatus | string | Tepat Waktu, Segera, atau Terlambat |
| task.windowStatusTone | string | scheduled, warning, atau alert |
| task.recipient | string | nama pembeli |
| task.phone | string | nomor pembeli |
| task.placeType | string | label tipe alamat |
| task.address | string | alamat |
| task.instruction | string atau null | instruksi penanganan |
| task.metrics | array | jumlah koli, kategori, berat |
| task.buyerCoordinates | objek | latitude dan longitude, boleh null |
| task.status, task.statusLabel | string | status dan labelnya |
| task.stage, task.stageLabel | string | tahap perjalanan |
| task.hasIssue | boolean | true bila ISSUE_REPORTED |
| task.isNew | boolean | penanda tugas baru |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Kurir tanpa tugas | dua array kosong, bukan error |
| Pembeli tanpa koordinat | buyerCoordinates berisi null sehingga rute berbasis koordinat tidak tersedia |
| Paket tanpa log penugasan | id tampilan jatuh ke id paket, usia memakai created_at |
| Paket RECEIVED atau RETURNED | tidak muncul karena status tidak termasuk empat yang disaring |

## 9. Efek samping

Tidak ada penulisan. Dua subquery korelatif per baris dijalankan untuk mendapatkan data penugasan.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier |
| Controller | Courier/TaskController@index |
| Query | Queries/CourierTaskQuery |
| Enum | Enums/ScheduleTiming, Enums/DeliveryStage |
| Support | DisplayTime, RelativeTime |
| Test | tests/Feature/CourierFlowTest.php test_courier_tasks_index_splits_new_and_active_tasks, test_courier_cannot_access_another_couriers_package |
| UI | app/NativeComponents/Courier/Tasks.php |

## 11. Skenario uji

1. Given kurir dengan satu tugas COURIER_ASSIGNED yang belum diterima dan satu tugas IN_DELIVERY, When papan diminta, Then newTasks memuat satu baris dan activeTasks memuat satu baris.
2. Given kurir lain membuka papan tugasnya, When diminta, Then paket kurir pertama tidak muncul.
3. Given paket dengan scheduled_end_at satu jam lalu, When papan diminta, Then windowStatus bernilai Terlambat.

## 12. Yang perlu dikonfirmasi

1. Papan tugas tidak memfilter berdasarkan tanggal, sehingga tugas lama yang belum selesai tetap menumpuk. Perlukah batas waktu atau penanda khusus?
2. Apakah kurir perlu melihat riwayat tugas yang gagal atau ditolak sebelumnya?

