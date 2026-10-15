# FR-DEL-007 — Profil kurir

| Properti | Nilai |
| --- | --- |
| ID | FR-DEL-007 |
| Modul | DEL — Pengantaran dan Bukti Serah Terima |
| Judul | Profil kurir |
| Status | Partial (kendaraan, tepat waktu, dan rating masih konstanta di controller) |
| Berkas modul | docs/frd/04-pengantaran-bukti.md |
| Berkas kode | app/Http/Controllers/Courier/ProfileController.php |

## 1. Ringkasan

Tab profil kurir: identitas, penempatan, kendaraan, dan tiga kartu statistik harian.

## 2. Aktor dan otorisasi

| Aspek | Nilai |
| --- | --- |
| Aktor | kurir terautentikasi |
| Middleware | AuthenticateCourier, abilities:courier, staff.role:COURIER |

## 3. Trigger

| Pemicu | Detail |
| --- | --- |
| UI | tab profil aplikasi kurir |
| Endpoint | GET /api/courier/profile |
| Nama route | api.courier.profile.show |

## 4. Precondition dan postcondition

| Tahap | Isi |
| --- | --- |
| Precondition | token kurir valid |
| Postcondition | tidak ada perubahan state |

## 5. Input dan validasi

Tanpa input.

## 6. Proses langkah demi langkah

1. Hitung jumlah paket RECEIVED milik kurir yang updated_at-nya hari ini.
2. Susun respons: nama dan status aktif dari baris staff, nomor kurir berbentuk KRR- diikuti id, nomor telepon atau tanda hubung, hub dari config coldchain.hub_name, kendaraan konstanta "Motor Box #04", dan tiga kartu statistik.
3. Kartu statistik berisi selesai hari ini (hasil hitung), tepat waktu "97%", dan rating "4.8".

Kedua angka terakhir adalah konstanta di dalam controller, bukan hasil perhitungan.
Requirement ini karena itu berstatus Partial.

## 7. Output

| Field | Tipe | Keterangan |
| --- | --- | --- |
| profile.name | string | nama kurir |
| profile.status | string | Aktif atau Nonaktif |
| profile.id | string | KRR- diikuti id staf |
| profile.phone | string | nomor telepon atau "-" |
| profile.hub | string | nilai config coldchain.hub_name |
| profile.vehicle | string | konstanta |
| profile.stats | array | tiga kartu label dan value |

## 8. Error dan edge case

| Kasus | Keterangan |
| --- | --- |
| Kurir tanpa pengantaran hari ini | kartu pertama bernilai 0 |
| Nomor telepon kosong | ditampilkan sebagai "-" |
| Hub belum dikonfigurasi | memakai bawaan "Hub Jakarta Selatan" |

## 9. Efek samping

Tidak ada.

## 10. Traceability

| Lapisan | Rujukan |
| --- | --- |
| Route | routes/api.php grup courier |
| Controller | Courier/ProfileController@show |
| Konfigurasi | config/coldchain.php hub_name |
| Test | tests/Feature/CourierFlowTest.php test_courier_history_and_profile, tests/Feature/PwaShellTest.php test_the_profile_tab_shows_identity_and_assignment, test_the_profile_switches_the_app_theme |
| UI | resources/js/pwa/screens/courier/Profile.tsx |

## 11. Skenario uji

1. Given kurir dengan dua paket terkirim hari ini, When profil diminta, Then kartu selesai hari ini bernilai 2 dan hub memakai nilai konfigurasi.
2. Given kurir tanpa pengantaran hari ini, When profil diminta, Then kartu pertama bernilai 0 dan permintaan tetap HTTP 200.

## 12. Yang perlu dikonfirmasi

1. Apakah kendaraan perlu menjadi data (kolom pada staff atau tabel armada) alih-alih konstanta?
2. Apakah angka tepat waktu dan rating harus dihitung dari data, atau dihapus agar tidak menyesatkan kurir dan manajemen?
3. Nilai hub berbeda antara coldchain.hub_name dan coldchain.hub.display_name. Nilai mana yang benar untuk ditampilkan?

