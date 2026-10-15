# FR-GPS-007 — Pengaturan radius geofence

| Properti | Nilai |
| --- | --- |
| ID | FR-GPS-007 |
| Modul | GPS — Cold Chain dan Validasi Lokasi |
| Judul | Pengaturan radius geofence |
| Status | Live |
| Berkas modul | docs/frd/05-coldchain-gps.md |
| Aturan bisnis | BR-009 |
| Berkas kode | app/Http/Controllers/Admin/GeofenceController.php, app/Http/Requests/Admin/UpdateGeofenceRequest.php |

## 1. Ringkasan

Hub menyesuaikan toleransi radius per segmen layanan. Nilai ini langsung mengubah
seberapa ketat serah terima divalidasi, sehingga setiap perubahan mencatat siapa
yang melakukannya.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | admin hub |
| Middleware | api, staff.role:ADMIN |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | halaman /admin/geofence, resources/js/pages/admin/Geofence.tsx |
| Endpoint | GET dan PUT /api/admin/geofence |
| Nama route | api.admin.geofence.index, api.admin.geofence.update |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | untuk tulis, minimal satu segmen dikirim |
| Postcondition | baris geofence_policies diperbarui dan cache radius dibuang |

## 5. Input dan validasi

| Field | Tipe | Aturan |
| --- | --- | --- |
| policies | array | required, min 1 |
| policies.INSTANT_FROZEN | integer | required, antara 10 dan 100 |
| policies.REGULER | integer | required, antara 25 dan 500 |

Batas database lebih longgar (10 sampai 1000); batas di atas adalah yang boleh
dipilih hub dari antarmuka.

## 6. Proses langkah demi langkah

1. Baca: ambil radius efektif dari evaluator, lalu susun daftar segmen berisi service_type, label, SLA, deskripsi, batas minimum dan maksimum, langkah, radius saat ini, dan radius bawaan.
2. Baca: sertakan waktu perubahan terakhir dari MAX updated_at.
3. Tulis: untuk setiap segmen yang dikirim, jalankan updateOrCreate dengan kunci service_type dan nilai radius_m beserta updated_by id admin.
4. Tulis: buang cache geofence.radii agar nilai baru berlaku pada permintaan berikutnya, bukan menunggu 60 detik.
5. Tulis: balas message dan daftar segmen terbaru.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| segments[].service_type | string | REGULER atau INSTANT_FROZEN |
| segments[].label | string | nama segmen |
| segments[].sla | string | keterangan SLA |
| segments[].description | string | penjelasan toleransi |
| segments[].min, max, step | integer | batas input antarmuka |
| segments[].radius_m | integer | nilai berlaku |
| segments[].default_m | integer | nilai bawaan |
| updated_at | string atau null | waktu perubahan terakhir |
| message | string | hanya pada PUT |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Nilai di luar batas segmen | 422, nilai lama tidak berubah |
| policies kosong | 422 |
| Segmen tidak dikirim | baris lama dibiarkan, tidak dihapus |
| Belum pernah diubah | updated_at null |

## 9. Efek samping

| Efek | Tempat |
| --- | --- |
| Baris kebijakan | geofence_policies |
| Cache | geofence.radii dibuang |
| Jejak perubahan | kolom updated_by dan updated_at |

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup admin |
| Controller | Admin/GeofenceController@index dan update |
| FormRequest | Requests/Admin/UpdateGeofenceRequest.php |
| Model | app/Models/GeofencePolicy.php konstanta SEGMENTS dan DEFAULT_RADIUS_M |
| Service | Services/Gps/GeofenceEvaluator::forget |
| Test | tests/Feature/AdminGpsConsoleTest.php test_the_console_lists_the_radius_per_segment, test_the_radius_can_be_raised_within_the_segment_bounds, test_a_radius_outside_the_segment_bounds_is_rejected, test_the_radius_settings_are_admin_only |
| UI | resources/js/pages/admin/Geofence.tsx |

## 11. Skenario uji

1. Given radius REGULER 100 meter, When admin menyetel 150, Then HTTP 200 dan pembacaan berikutnya menunjukkan 150.
2. Given nilai 10 untuk REGULER, When disimpan, Then HTTP 422 dan nilai lama tidak berubah.
3. Given pengiriman yang sedang berjalan, When radius diubah, Then penilaian berikutnya memakai radius baru sedangkan evidence lama tidak berubah.

## 12. Yang perlu dikonfirmasi

1. Apakah harga lama perlu dipertahankan dalam riwayat agar audit dapat menjelaskan verdict pada masa lalu? Saat ini radius hanya tersimpan sebagai salinan angka pada location_checks.
2. Apakah kurir perlu dapat melihat radius yang berlaku pada segmennya sebelum berangkat? Saat ini layar pengantaran menampilkannya, sehingga sudah terbaca.

