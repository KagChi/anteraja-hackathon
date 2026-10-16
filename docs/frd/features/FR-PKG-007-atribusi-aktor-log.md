# FR-PKG-007 — Atribusi aktor pada log status

| Properti | Nilai |
| --- | --- |
| ID | FR-PKG-007 |
| Modul | PKG — Paket dan FSM Status |
| Judul | Atribusi aktor pada log status |
| Status | Live |
| Berkas modul | docs/frd/02-paket-fsm.md |
| Aturan bisnis | BR-003 |
| Berkas kode | app/Support/PackageStatusTransitioner.php, migrasi 0001_01_01_000016 |

## 1. Ringkasan

Setiap perubahan status harus dapat dipertanggungjawabkan. Aplikasi menulis aktor
ke sesi PostgreSQL, dan trigger database menyalinnya ke kolom audit
status_logs.changed_by tanpa perlu kode tambahan di setiap Action.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | sistem |
| Sumber nilai | parameter actor pada PackageStatusTransitioner |
| Nilai null | dipakai untuk aksi pembeli dan aksi sistem |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| Trigger database | AFTER INSERT OR UPDATE OF status ON packages, fungsi packages_log_status |
| Titik masuk aplikasi | setiap pemanggilan transitioner |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | tidak ada |
| Postcondition | satu baris status_logs per perubahan status, dengan changed_by sesuai aktor |

## 5. Input dan validasi

| Sumber | Nilai |
| --- | --- |
| actor bukan null | set_config app.staff_id berisi string id staf |
| actor null | set_config app.staff_id berisi string kosong |
| Pembacaan trigger | NULLIF(current_setting app.staff_id, string kosong) lalu cast ke bigint |

## 6. Proses langkah demi langkah

1. Aplikasi menjalankan set_config dengan parameter lokal true, sehingga nilainya hanya berlaku di transaksi itu.
2. Model disimpan; trigger AFTER berjalan di transaksi yang sama.
3. Trigger menyisipkan baris status_logs. Untuk INSERT, from_status bernilai null; untuk UPDATE, from_status berisi status lama.
4. changed_by diambil dari current_setting app.staff_id; bila kosong hasilnya null.
5. changed_at memakai default now().

Konsekuensi yang perlu dipahami pembaca dokumen: aksi pembeli seperti konfirmasi
jadwal dan pengajuan jadwal ulang menghasilkan changed_by null. Untuk mengetahui
pelakunya, harus dibaca tabel schedule_confirmations.responded_at.

## 7. Output

| Field status_logs | Tipe | Keterangan |
| --- | --- | --- |
| package_id | bigint | paket terkait |
| from_status | text atau null | status sebelum perubahan, null untuk baris pertama |
| to_status | text | status sesudah perubahan |
| changed_by | bigint atau null | id staf, atau null untuk aksi pembeli dan sistem |
| changed_at | timestamptz | waktu perubahan |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Aksi pembeli | changed_by null; pelaku harus dibaca dari tabel konfirmasi |
| Aksi sistem pada pembuatan paket pelanggan | changed_by null karena created_by juga null |
| Transaksi digulung | baris log ikut hilang karena trigger berjalan dalam transaksi yang sama |
| Nilai app.staff_id bukan angka | cast bigint gagal; karena itu aktor selalu dikirim sebagai string id atau string kosong |

## 9. Efek samping

Satu baris status_logs per perubahan status, tanpa penulisan tambahan dari aplikasi.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Kode | Support/PackageStatusTransitioner.php |
| Migrasi | 0001_01_01_000015_create_status_logs.php, 0001_01_01_000016_add_package_status_guard.php |
| Model | app/Models/StatusLog.php termasuk relasi changedByStaff |
| Test | tests/Feature/ClientFlowTest.php test_schedule_confirm_transitions_status_with_null_actor |
| Konsumen | Admin/ShipmentController@show (statusLogs.changedByStaff), Support/PackageTimelineBuilder, Services/Client/ClientTimelineBuilder |

## 11. Skenario uji

1. Given admin menugaskan kurir, When transisi COURIER_ASSIGNED tersimpan, Then baris log terbaru memuat changed_by sama dengan id admin.
2. Given pembeli mengonfirmasi jadwal, When transisi CONFIRMED tersimpan, Then baris log terbaru memuat changed_by null dan schedule_confirmations mencatat responded_at.

## 12. Yang perlu dikonfirmasi

1. Apakah audit aksi pembeli perlu aktor eksplisit? Saat ini identitas pembeli hanya tersirat dari pemilik paket pada tabel schedule_confirmations.
2. Apakah perlu kolom sumber aksi (BUYER, SYSTEM, STAFF) pada status_logs agar pembacaan laporan lebih sederhana?

