# FR-PKG-008 — Transisi ke CLOSED

| Properti | Nilai |
| --- | --- |
| ID | FR-PKG-008 |
| Modul | PKG — Paket dan FSM Status |
| Judul | Transisi ke CLOSED |
| Status | [TIDAK TERVERIFIKASI] |
| Berkas modul | docs/frd/02-paket-fsm.md |
| Aturan bisnis | BR-001, BR-002 |
| Berkas kode | tidak ada pemanggil; matriks mengizinkan, kode tidak |

## 1. Ringkasan

Matriks transisi mengizinkan RECEIVED ke CLOSED dan RETURNED ke CLOSED, dan sistem
sudah memperlakukan CLOSED sebagai status selesai di beberapa tempat. Namun tidak
ada satu pun pemanggil transisi ke CLOSED di dalam app/, dan kolom packages.closed_at
tidak pernah ditulis. Requirement ini dicatat apa adanya, tanpa mengarang pemicu.

## 2. Aktor dan otorisasi

[TIDAK TERVERIFIKASI] tidak ada route, Action, middleware, maupun perintah
scheduler yang melakukan transisi ini.

## 3. Trigger

[TIDAK TERVERIFIKASI]. Kemungkinan yang masuk akal, tanpa bukti kode: aksi admin
dari konsol, job terjadwal harian, atau proses di luar sistem.

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | paket berstatus RECEIVED atau RETURNED |
| Postcondition | [TIDAK TERVERIFIKASI] |

## 5. Input dan validasi

[TIDAK TERVERIFIKASI].

## 6. Proses langkah demi langkah

[TIDAK TERVERIFIKASI]. Yang dapat dipastikan hanya bahwa bila nanti dibuat
transisi wajib melalui PackageStatusTransitioner di dalam transaction dan mengisi
closed_at, sesuai aturan repositori pada AGENTS.md dan BR-002.

## 7. Output

[TIDAK TERVERIFIKASI].

## 8. Error dan edge case

| Kasus | Perilaku saat ini |
| --- | --- |
| Paket RECEIVED tidak pernah ditutup | tetap RECEIVED selamanya, closed_at null |
| Paket RETURNED tidak pernah ditutup | tetap RETURNED selamanya, closed_at null |
| Arsip paket | paket tetap muncul di perhitungan tertentu yang memakai status terminal secara eksplisit |

## 9. Efek samping

Tidak ada.

## 10. Traceability

| Bukti | Lokasi |
| --- | --- |
| Matriks mengizinkan | database/seeders/ReferenceDataSeeder.php baris RECEIVED ke CLOSED dan RETURNED ke CLOSED |
| Kolom tidak terpakai | database/migrations/0001_01_01_000008_create_packages.php kolom closed_at; app/Models/Package.php fillable dan cast |
| Kode baca yang sudah mengantisipasi CLOSED | Queries/ClientOrderListQuery, Resources/Client/BuyerProfileResource, Services/Client/ReplacementEligibility, Services/Client/ClientAccessService, Services/Client/ClientTimelineBuilder, Services/Client/OrderPresenter, Http/Controllers/Courier/DeliveryController |
| Pencarian pemanggil | penelusuran transitioner di app/Actions dan app/Support tidak menemukan transisi ke CLOSED |

## 11. Skenario uji

1. Given paket RECEIVED dan tidak ada proses apa pun berjalan, When satu minggu berlalu, Then status tetap RECEIVED dan closed_at null.
2. Given keputusan stakeholder menetapkan pemicunya, When mekanisme dibuat, Then transisi ke CLOSED harus memakai transitioner, muncul di status_logs, dan mengisi closed_at.

## 12. Yang perlu dikonfirmasi

1. Apa pemicu resmi penutupan paket: aksi admin, job harian, atau memang belum ada kebutuhan?
2. Berapa lama paket boleh diarsipkan sebelum ditutup, dan apakah pelanggan masih dapat membuka tautannya setelah itu?
3. Bila paket CLOSED tidak pernah terwujud, apakah label status CLOSED sebaiknya dihapus dari tabel referensi agar tidak membingungkan?

