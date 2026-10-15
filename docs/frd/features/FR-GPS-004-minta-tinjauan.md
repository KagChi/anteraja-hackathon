# FR-GPS-004 — Permintaan tinjauan GPS oleh kurir

| Properti | Nilai |
| --- | --- |
| ID | FR-GPS-004 |
| Modul | GPS — Cold Chain dan Validasi Lokasi |
| Judul | Permintaan tinjauan GPS oleh kurir |
| Status | Live |
| Berkas modul | docs/frd/05-coldchain-gps.md |
| Aturan bisnis | BR-026 |
| Berkas kode | app/Http/Controllers/Courier/GpsReviewController.php, app/Services/Gps/GpsValidator.php |

## 1. Ringkasan

Jalan keluar dari blokir lokasi. Insidennya tetap tercatat, tetapi setelah hub
menyetujui permintaan, kurir dapat mengulang aksi yang sama.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir pemilik paket |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |
| Policy | Gate::authorize update |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tombol minta tinjauan saat gps.can_request_review bernilai true |
| Endpoint | POST /api/courier/deliveries/{tracking_number}/gps-review |
| Otomatis | validator juga membuka permintaan saat memblokir |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | ada penilaian terakhir untuk paket dan aksi tersebut |
| Postcondition | satu baris gps_lock_requests PENDING untuk paket dan aksi itu |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| action | string | required, harus nilai GpsAction: DEPART, ARRIVE, POD |
| note | string | nullable, max 300 |

Catatan: parameter note diterima validasi tetapi tidak dipakai saat membentuk
permintaan. Ini temuan kecil yang perlu diputuskan.

## 6. Proses langkah demi langkah

1. Policy memastikan kepemilikan paket.
2. Controller memvalidasi action sebagai Enum dan note sebagai string.
3. GpsValidator::requestReview mengambil penilaian terakhir untuk paket dan aksi itu.
4. Bila tidak ada penilaian, dibentuk asesmen bawaan berlevel SUSPECTED tanpa alasan.
5. openLockRequest memeriksa apakah sudah ada permintaan PENDING untuk paket dan aksi tersebut; bila ada, permintaan itu yang dikembalikan sehingga aksi idempoten.
6. Bila belum ada, alasan dari penilaian dipakai, ditambah alasan outside_geofence bila posisi di luar radius.
7. Baris gps_lock_requests dibuat dengan status PENDING dan level BLOCKED.
8. Balas HTTP 201 dengan data permintaan.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| message | string | "Permintaan tinjauan GPS dikirim ke hub." |
| data.id | integer | id permintaan |
| data.action, data.action_label | string | aksi dan labelnya |
| data.status, data.status_label | string | PENDING dan "Menunggu Tinjauan" |
| data.reasons | array | alasan yang dibawa ke hub |
| data.created_at | string | ISO8601 |

## 8. Error dan edge case

| Kasus | HTTP | Keterangan |
| --- | --- | --- |
| action di luar enum | 422 | validasi Enum |
| note lebih dari 300 karakter | 422 | validasi max |
| Permintaan PENDING sudah ada | 201 | mengembalikan baris yang sama, tanpa duplikat |
| Paket milik kurir lain | 404 | PackagePolicy |
| Tanpa riwayat penilaian | 201 | memakai asesmen bawaan |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Permintaan tinjauan | gps_lock_requests, satu baris per paket dan aksi |
| Indeks unik | uq_gps_lock_requests_open mencegah dua permintaan terbuka |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier deliveries |
| Controller | Courier/GpsReviewController |
| Service | Services/Gps/GpsValidator::requestReview dan openLockRequest |
| Enum | Enums/GpsAction.php, Enums/GpsLockStatus.php |
| Model | app/Models/GpsLockRequest.php |
| Test | tests/Feature/CourierGpsValidationTest.php test_a_courier_can_ask_the_hub_to_review_and_the_request_is_idempotent, tests/Feature/CourierNativeScreensTest.php test_a_blocked_hand_over_offers_a_hub_review, test_asking_the_hub_to_review_from_the_delivery_screen |
| UI | app/NativeComponents/Courier/Delivery.php |

## 11. Skenario uji

1. Given kurir baru diblokir pada aksi POD, When minta tinjauan dikirim, Then HTTP 201 dengan satu baris PENDING.
2. Given permintaan PENDING yang sama dikirim dua kali, When permintaan kedua diproses, Then hanya satu baris PENDING di database.
3. Given action bernilai ALLOWED, When dikirim, Then HTTP 422.

## 12. Yang perlu dikonfirmasi

1. Parameter note diterima tetapi tidak dipakai. Apakah perlu disimpan pada permintaan agar hub tahu alasan kurir?
2. Apakah kurir perlu dapat membatalkan permintaan tinjauan yang sudah dikirim?
3. Apakah permintaan perlu kedaluwarsa bila tidak diputuskan dalam beberapa jam?

