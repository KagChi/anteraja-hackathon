# FR-ASG-003 — Menerima tugas

| Properti | Nilai |
| --- | --- |
| ID | FR-ASG-003 |
| Modul | ASG — Penugasan Kurir dan Siklus Tugas |
| Judul | Menerima tugas |
| Status | Live |
| Berkas modul | docs/frd/03-penugasan-kurir.md |
| Aturan bisnis | BR-004, BR-020 |
| Berkas kode | app/Actions/Courier/AcceptTask.php |

## 1. Ringkasan

Menandai bahwa kurir sudah melihat dan mengambil tugas. Tanpa langkah ini, layar
pengantaran menolak dibuka. Aksi ini tidak mengubah status paket, hanya mengisi
stempel waktu penerimaan.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize update dengan PackagePolicy |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tombol terima pada kartu tugas baru |
| Endpoint | POST /api/courier/tasks/{tracking_number}/accept |
| Nama route | api.courier.tasks.accept |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus COURIER_ASSIGNED dan courier_id sama dengan kurir yang login |
| Postcondition | courier_accepted_at terisi; tugas berpindah dari newTasks ke activeTasks |

## 5. Input dan validasi

Tanpa body.

## 6. Proses langkah demi langkah

1. PackagePolicy memastikan kurir berhak atas paket; paket milik kurir lain menghasilkan 404 agar keberadaannya tidak bocor.
2. Action membuka DB::transaction.
3. Baris paket dikunci dengan lockForUpdate.
4. Bila courier_id bukan kurir ini atau status bukan COURIER_ASSIGNED, lempar TaskNotActionable::notAssignable dengan resi pada pesannya.
5. Bila courier_accepted_at sudah terisi, kembalikan paket tanpa perubahan (idempoten).
6. Set courier_accepted_at dengan Carbon::now dan simpan.
7. Kembalikan paket.

Perlu dicatat: status historis COURIER_ASSIGNED ke CHECKED tercatat di matriks
namun langkah ini tidak memakainya; perpindahan itu terjadi pada saat kurir mulai
mengantar atau melaporkan kendala.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Tugas berhasil diterima." |
| package | objek | hasil fresh dengan statusRef |
| package.courier_accepted_at | string | waktu penerimaan |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| Paket milik kurir lain | 404 | PackagePolicy memakai denyAsNotFound |
| Status bukan COURIER_ASSIGNED | TaskNotActionable | belum dipetakan ke kode HTTP khusus |
| Kurir menekan dua kali | 200 | idempoten, nilai waktu tidak berubah |
| Resi tidak ada | 404 | firstOrFail |

## 9. Efek samping

Kolom packages.courier_accepted_at terisi. Tidak ada notifikasi yang dikirim pada
langkah ini; pembeli baru diberi tahu saat kurir berangkat.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier tasks |
| Controller | Courier/AcceptTaskController |
| Action | Actions/Courier/AcceptTask |
| Policy | app/Policies/PackagePolicy.php |
| Exception | app/Exceptions/TaskNotActionable.php |
| Test | tests/Feature/CourierFlowTest.php test_courier_accept_task_flow, test_access_controls |
| UI | resources/js/pwa/screens/courier/Tasks.tsx |

## 11. Skenario uji

1. Given tugas COURIER_ASSIGNED milik kurir, When accept dipanggil, Then HTTP 200 dan courier_accepted_at terisi.
2. Given tugas yang sudah diterima, When accept dipanggil lagi, Then HTTP 200 tanpa perubahan nilai waktu.
3. Given paket milik kurir lain, When accept dipanggil, Then HTTP 404.

## 12. Yang perlu dikonfirmasi

1. Apakah perlu batas waktu penerimaan tugas sebelum paket otomatis dilepas kembali ke hub?
2. Apakah pembeli perlu diberi tahu bahwa kurir sudah menerima tugas?

