# FRD — Anteraja Frozen (Master)

Versi: 1.0 (as-is)
Tanggal: 15 Oktober 2026
Referensi: docs/prd_00.md, docs/_analysis/00-inventory.md,
docs/_analysis/01-actors-domain.md, docs/_analysis/02-verification.md

---

## 1. Pendahuluan

### 1.1 Scope

FRD ini mencakup seluruh perilaku fungsional yang dapat dibuktikan dari kode
repositori Anteraja Frozen:

- API JSON di routes/api.php.
- Halaman Inertia di routes/web.php.
- Layar native Android di routes/mobile.php.
- Proses asinkron: queue job, scheduler, outbox notifikasi, dan workflow n8n.

Di luar scope: kode frontend React (dimiliki tim frontend sesuai AGENTS.md),
konfigurasi cluster, dan isi berkas n8n yang bukan kontrak antarmuka.

### 1.2 Konvensi ID

Format ID: FR-<MODUL>-<NNN>.

Tabel modul:

| Kode modul | Nama modul | Berkas detail |
| --- | --- | --- |
| AUTH | Autentikasi dan Sesi (admin, kurir, pelanggan, klaim tautan) | docs/frd/01-auth.md |
| PKG | Paket dan FSM Status | docs/frd/02-paket-fsm.md |
| ASG | Penugasan Kurir dan Siklus Tugas | docs/frd/03-penugasan-kurir.md |
| DEL | Pengantaran dan Bukti Serah Terima | docs/frd/04-pengantaran-bukti.md |
| GPS | Cold Chain dan Validasi Lokasi | docs/frd/05-coldchain-gps.md |
| ISS | Laporan Kendala, Keputusan, dan Penggantian | docs/frd/06-kendala-penggantian.md |
| NOTIF | Notification Outbox dan Penjadwalan | docs/frd/07-notifikasi-outbox.md |
| WA | Percakapan WhatsApp (n8n, WAHA, LLM) | docs/frd/08-whatsapp.md |
| TRACK | Pelacakan Publik dan Tautan Tamu | docs/frd/09-pelacakan.md |
| ORD | Katalog dan Pemesanan Pelanggan | docs/frd/10-katalog-pesanan.md |
| RPT | Pelaporan, Ekspor, dan Media | docs/frd/11-pelaporan.md |
| LAND | Landing Page dan Blog | docs/frd/12-landing-blog.md |
| MOB | Shell Mobile Native | docs/frd/13-mobile-native.md |

### 1.3 Legenda label

| Label | Arti |
| --- | --- |
| Live | Perilaku ada dan dipakai; ada bukti wiring dan umumnya ada test |
| Partial | Perilaku ada sebagian; ada bagian yang belum tertutup |
| [TIDAK TERVERIFIKASI] | Tidak ada bukti di kode; wajib dikonfirmasi manusia |
| [INFERENSI] | Simpulan dari bukti, bukan pernyataan langsung kode |

### 1.4 Konvensi kalimat requirement

Setiap requirement ditulis sebagai "Sistem HARUS ..." dengan perilaku yang dapat
diuji, menyebut kode HTTP bila relevan.

---

## 2. Arsitektur Fungsional

Alur permintaan satu arah, dari perangkat sampai database dan kembali:

    flowchart TD
        A[Klien: SPA admin, APK kurir, APK pelanggan, browser landing] --> B[Ingress nginx atau k8s]
        B --> C[FrankenPHP + Laravel Octane, worker mode]
        C --> D[Middleware: force.json, staff.role, client.headers, client.buyer, client.owned, client.package, internal.token, throttle]
        D --> E[Controller tipis]
        E --> F[FormRequest atau validasi inline]
        F --> G[Action untuk mutasi, Query untuk baca, Service untuk aturan bisnis]
        G --> H[DB transaction + lockForUpdate]
        H --> I[(PostgreSQL: packages, status_logs, trigger FSM)]
        G --> J[Outbox: tabel notifications]
        J --> K[Job DispatchNotificationToN8n setelah commit]
        K --> L[Redis queue]
        L --> M[n8n outbound webhook]
        M --> N[WAHA]
        N --> O[WhatsApp pelanggan]
        O --> P[WAHA inbound webhook ke n8n]
        P --> Q[n8n inbound: resolusi LID, LLM intent, state anteraja_chat_state]
        Q --> R[API guest aplikasi: confirm, reschedule, issue choice]
        Q --> S[API internal: lookup resi]
        R --> I

Penjelasan per simpul, dengan bukti:

| Simpul | Bukti |
| --- | --- |
| Middleware klien dan staf | bootstrap/app.php alias, app/Http/Middleware/* |
| Controller tipis | app/Http/Controllers/** (semua final class, tanpa logika domain) |
| Action untuk mutasi | app/Actions/** (23 kelas) |
| Query untuk baca | app/Queries/** (6 kelas) |
| Service untuk aturan | app/Services/** (12 kelas) |
| Satu pintu transisi | app/Support/PackageStatusTransitioner.php |
| Outbox | app/Support/NotificationQueue.php, tabel notifications |
| Job | app/Jobs/DispatchNotificationToN8n.php (tries 3, backoff 10 dan 30) |
| n8n dan WAHA | deploy/n8n/README.md, deploy/n8n/workflows/*.json |
| API internal | app/Http/Controllers/Api/Internal/WhatsappLookupController.php |

Lapisan asinkron dan terjadwal:

    flowchart LR
        A[Action domain] --> B[NotificationQueue::queue]
        B --> C[(notifications QUEUED)]
        B --> D[DispatchNotificationToN8n afterCommit]
        D --> E{webhook n8n aktif}
        E -->|ya| F[POST template + payload]
        F -->|delivered true| G[status SENT + wa_message_id]
        F -->|unmapped atau skipped| H[tetap QUEUED + log info]
        F -->|selain itu| I[status FAILED + delivery_error]
        E -->|tidak| J[selesai tanpa perubahan status]
        K[Scheduler tiap 15 menit] --> L[confirmations:remind-unanswered]
        L --> B

---

## 3. Ringkasan Functional Requirements

Rincian tiap requirement ada pada berkas modul di docs/frd/. Tabel berikut hanya
memetakan modul dan judul requirement agar mudah dirujuk.

| ID | Judul | Status |
| --- | --- | --- |
| FR-AUTH-001 | Login sesi admin | Live |
| FR-AUTH-002 | Logout sesi admin | Live |
| FR-AUTH-003 | Profil sesi admin | Live |
| FR-AUTH-004 | Login kurir berbasis token | Live |
| FR-AUTH-005 | Logout kurir | Live |
| FR-AUTH-006 | Registrasi pelanggan | Live |
| FR-AUTH-007 | Login pelanggan | Live |
| FR-AUTH-008 | Logout pelanggan | Live |
| FR-AUTH-009 | Profil pelanggan | Live |
| FR-AUTH-010 | Klaim tautan menjadi akun | Live |
| FR-PKG-001 | Membuat paket dari hub | Live |
| FR-PKG-002 | Antrean shipment dengan filter | Live |
| FR-PKG-003 | Data master form pembuatan paket | Live |
| FR-PKG-004 | Detail paket dan timeline | Live |
| FR-PKG-005 | Penjadwalan ulang oleh admin | Live |
| FR-PKG-006 | Penjagaan FSM oleh database | Live |
| FR-PKG-007 | Atribusi aktor pada log status | Live |
| FR-PKG-008 | Transisi ke CLOSED | [TIDAK TERVERIFIKASI] |
| FR-ASG-001 | Penugasan kurir | Live |
| FR-ASG-002 | Papan tugas kurir | Live |
| FR-ASG-003 | Menerima tugas | Live |
| FR-ASG-004 | Menolak tugas | Live |
| FR-ASG-005 | Jarak dan ETA tujuan | Live |
| FR-DEL-001 | Layar pengantaran kurir | Live |
| FR-DEL-002 | Mulai pengantaran dengan checklist | Live |
| FR-DEL-003 | Menandai tiba di lokasi | Live |
| FR-DEL-004 | Form bukti serah terima | Live |
| FR-DEL-005 | Menyimpan bukti serah terima | Live |
| FR-DEL-006 | Riwayat pengantaran kurir | Live |
| FR-DEL-007 | Profil kurir | Partial |
| FR-GPS-001 | Deteksi manipulasi sinyal GPS | Live |
| FR-GPS-002 | Penilaian geofence per segmen layanan | Live |
| FR-GPS-003 | Pencatatan bukti penilaian lokasi | Live |
| FR-GPS-004 | Permintaan tinjauan GPS oleh kurir | Live |
| FR-GPS-005 | Konsol validasi GPS admin | Live |
| FR-GPS-006 | Putusan tinjauan GPS | Live |
| FR-GPS-007 | Pengaturan radius geofence | Live |
| FR-ISS-001 | Menyiapkan form laporan kendala | Live |
| FR-ISS-002 | Mengirim laporan kendala | Live |
| FR-ISS-003 | Antrean kendala admin | Live |
| FR-ISS-004 | Detail kendala admin | Live |
| FR-ISS-005 | Putusan kendala | Live |
| FR-ISS-006 | Efek samping lima jenis putusan | Live |
| FR-ISS-007 | Kelayakan dan pembuatan paket pengganti | Live |
| FR-ISS-008 | Layar kendala pelanggan | Live |
| FR-ISS-009 | Mencatat pilihan penyelesaian pelanggan | Live |
| FR-ISS-010 | Permintaan pengiriman ulang dari aplikasi | Live |
| FR-NOTIF-001 | Menulis notifikasi ke outbox | Live |
| FR-NOTIF-002 | Mengirim notifikasi ke n8n | Live |
| FR-NOTIF-003 | Pengingat konfirmasi tak dijawab | Live |
| FR-WA-001 | Kontrak webhook outbound | Live |
| FR-WA-002 | Percakapan masuk dan pemetaan aksi | Live |
| FR-WA-003 | Lookup resi untuk bot | Live |
| FR-TRACK-001 | Pelacakan publik berdasarkan resi | Live |
| FR-TRACK-002 | Resolusi tautan tamu berbatas waktu | Live |
| FR-TRACK-003 | Pemilihan layar otomatis | Live |
| FR-TRACK-004 | Data tracking pelanggan | Live |
| FR-TRACK-005 | Data jadwal pelanggan | Live |
| FR-TRACK-006 | Konfirmasi jadwal | Live |
| FR-TRACK-007 | Permintaan jadwal ulang pelanggan | Live |
| FR-ORD-001 | Daftar katalog publik | Live |
| FR-ORD-002 | Detail produk publik | Live |
| FR-ORD-003 | Checkout pesanan pelanggan | Live |
| FR-ORD-004 | Daftar pesanan dengan tab dan pencarian | Live |
| FR-ORD-005 | Detail pesanan pelanggan | Live |
| FR-RPT-001 | KPI laporan harian | Live |
| FR-RPT-002 | Ekspor laporan harian | Live |
| FR-RPT-003 | Pencarian riwayat paket | Live |
| FR-RPT-004 | Ekspor riwayat | Live |
| FR-RPT-005 | Ekspor antrean shipment | Live |
| FR-RPT-006 | Pratinjau foto bukti | Live |
| FR-LAND-001 | Halaman landing | Live |
| FR-LAND-002 | Blog index dan artikel | Live |
| FR-LAND-003 | Halaman login admin dan redirect ringkas | Live |
| FR-MOB-001 | Rute dan grup layar native | Live |
| FR-MOB-002 | Penyimpanan token per realm | Live |
| FR-MOB-003 | Klien API mobile | Live |
| FR-MOB-004 | Kapabilitas perangkat kurir | Live |
| FR-MOB-005 | Keranjang dan struk lokal pelanggan | Live |
| FR-MOB-006 | Pemilih tema aplikasi | Live |

---

## 4. Spesifikasi API

Seluruh entri di bawah berasal dari output php artisan route:list dan merupakan
route aktual. Kolom OpenAPI menandai apakah endpoint sudah tercantum di
docs/openapi.yaml.

### 4.1 Sesi staf

| Method | Path | Controller@method | Middleware | OpenAPI |
| --- | --- | --- | --- | --- |
| POST | /api/auth/login | Auth/SessionController@store | api | ya |
| POST | /api/auth/logout | Auth/SessionController@destroy | api | ya |
| GET | /api/auth/me | Auth/SessionController@me | api | ya |

### 4.2 Admin

| Method | Path | Controller@method | Middleware | OpenAPI |
| --- | --- | --- | --- | --- |
| GET | /api/admin/shipments | Admin/ShipmentController@index | staff.role:ADMIN | ya |
| GET | /api/admin/shipments/create | Admin/ShipmentController@create | staff.role:ADMIN | ya |
| POST | /api/admin/shipments | Admin/ShipmentController@store | staff.role:ADMIN | ya |
| GET | /api/admin/shipments/export | Admin/ShipmentExportController | staff.role:ADMIN | ya |
| GET | /api/admin/shipments/{tracking_number} | Admin/ShipmentController@show | staff.role:ADMIN | ya |
| POST | /api/admin/shipments/{tracking_number}/assign | Admin/ShipmentAssignmentController | staff.role:ADMIN | ya |
| POST | /api/admin/shipments/{tracking_number}/reschedule | Admin/ShipmentRescheduleController | staff.role:ADMIN | ya |
| GET | /api/admin/exceptions | Admin/ExceptionController@index | staff.role:ADMIN | ya |
| GET | /api/admin/exceptions/{tracking_number} | Admin/ExceptionController@show | staff.role:ADMIN | ya |
| POST | /api/admin/exceptions/{tracking_number}/decision | Admin/ExceptionDecisionController | staff.role:ADMIN | ya |
| GET | /api/admin/reports | Admin/ReportController@index | staff.role:ADMIN | ya |
| GET | /api/admin/reports/export | Admin/ReportExportController | staff.role:ADMIN | ya |
| GET | /api/admin/history | Admin/HistoryController@index | staff.role:ADMIN | ya |
| GET | /api/admin/history/export | Admin/HistoryExportController | staff.role:ADMIN | ya |
| GET | /api/admin/geofence | Admin/GeofenceController@index | staff.role:ADMIN | ya |
| PUT | /api/admin/geofence | Admin/GeofenceController@update | staff.role:ADMIN | ya |
| GET | /api/admin/gps-checks | Admin/GpsCheckController@index | staff.role:ADMIN | ya |
| POST | /api/admin/gps-checks/{id}/decision | Admin/GpsCheckController@decide | staff.role:ADMIN | ya |
| GET | /api/media/preview | Media/MediaPreviewController | staff.role:ADMIN | ya |

### 4.3 Kurir

| Method | Path | Controller@method | Middleware | OpenAPI |
| --- | --- | --- | --- | --- |
| POST | /api/courier/auth/login | Api/Courier/AuthController@login | force.json, throttle:courier-auth | ya |
| POST | /api/courier/auth/logout | Api/Courier/AuthController@logout | AuthenticateCourier, abilities:courier, staff.role:COURIER | ya |
| GET | /api/courier/tasks | Courier/TaskController@index | sama | ya |
| GET | /api/courier/tasks/{tracking_number}/distance | Courier/TaskDistanceController | sama | ya |
| POST | /api/courier/tasks/{tracking_number}/accept | Courier/AcceptTaskController | sama | ya |
| POST | /api/courier/tasks/{tracking_number}/reject | Courier/RejectTaskController | sama | ya |
| GET | /api/courier/deliveries/{tracking_number} | Courier/DeliveryController@show | sama | ya |
| POST | /api/courier/deliveries/{tracking_number}/depart | Courier/StartDeliveryController | sama | ya |
| POST | /api/courier/deliveries/{tracking_number}/arrive | Courier/MarkArrivedController | sama | ya |
| POST | /api/courier/deliveries/{tracking_number}/gps-review | Courier/GpsReviewController | sama | ya |
| GET | /api/courier/deliveries/{tracking_number}/proof | Courier/ProofController@create | sama | ya |
| POST | /api/courier/deliveries/{tracking_number}/proof | Courier/ProofController@store | sama | ya |
| GET | /api/courier/deliveries/{tracking_number}/issue | Courier/IssueReportController@create | sama | ya |
| POST | /api/courier/deliveries/{tracking_number}/issue | Courier/IssueReportController@store | sama | ya |
| GET | /api/courier/history | Courier/HistoryController@index | sama | ya |
| GET | /api/courier/profile | Courier/ProfileController@show | sama | ya |

### 4.4 Pelanggan publik dan terautentikasi

| Method | Path | Controller@method | Middleware | OpenAPI |
| --- | --- | --- | --- | --- |
| POST | /api/client/auth/register | Api/Client/AuthController@register | force.json, client.headers, throttle:client-auth | ya |
| POST | /api/client/auth/login | Api/Client/AuthController@login | force.json, client.headers, throttle:client-auth | tidak |
| POST | /api/client/auth/logout | Api/Client/AuthController@logout | auth:sanctum, abilities:client, client.buyer | tidak |
| GET | /api/client/me | Api/Client/ProfileController@show | sama | tidak |
| GET | /api/client/catalog | Api/Client/CatalogController@index | force.json, client.headers | ya |
| GET | /api/client/catalog/{slug} | Api/Client/CatalogController@show | sama | ya |
| GET | /api/client/orders | Api/Client/OrderController@index | auth:sanctum, abilities:client, client.buyer | ya |
| POST | /api/client/orders | Api/Client/OrderController@store | sama | tidak |
| GET | /api/client/orders/{tracking_number} | Api/Client/OrderController@show | sama + client.owned | tidak |
| GET | /api/client/orders/{tracking_number}/tracking | Api/Client/TrackingController@show | sama + client.owned | tidak |
| GET | /api/client/orders/{tracking_number}/schedule | Api/Client/ScheduleController@show | sama + client.owned | tidak |
| POST | /api/client/orders/{tracking_number}/schedule/confirm | Api/Client/ScheduleConfirmationController | sama + client.owned | tidak |
| POST | /api/client/orders/{tracking_number}/schedule/reschedule | Api/Client/ScheduleRescheduleController | sama + client.owned | tidak |
| GET | /api/client/orders/{tracking_number}/issue | Api/Client/IssueController@show | sama + client.owned | tidak |
| POST | /api/client/orders/{tracking_number}/issue/resend | Api/Client/ReplacementController | sama + client.owned | tidak |

### 4.5 Tautan tamu

| Method | Path | Controller@method | Middleware | OpenAPI |
| --- | --- | --- | --- | --- |
| GET | /api/guest/{token} | Api/Client/LinkController@resolve | force.json, client.headers, client.package | tidak |
| POST | /api/guest/{token}/claim | Api/Client/AuthController@claim | sama | tidak |
| GET | /api/guest/{token}/tracking | Api/Client/TrackingController@show | sama | tidak |
| GET | /api/guest/{token}/schedule | Api/Client/ScheduleController@show | sama | tidak |
| POST | /api/guest/{token}/schedule/confirm | Api/Client/ScheduleConfirmationController | sama | tidak |
| POST | /api/guest/{token}/schedule/reschedule | Api/Client/ScheduleRescheduleController | sama | tidak |
| GET | /api/guest/{token}/issue | Api/Client/IssueController@show | sama | tidak |
| POST | /api/guest/{token}/issue/choice | Api/Client/IssueChoiceController | sama | tidak |

### 4.6 Tautan token legacy

| Method | Path | Controller@method | Middleware | OpenAPI |
| --- | --- | --- | --- | --- |
| GET | /api/client/{token} | Client/EntryController | client.headers, client.package | ya |
| POST | /api/client/{token}/claim | Api/Client/AuthController@claim | sama | tidak |
| GET | /api/client/{token}/tracking | Client/TrackingController@show | sama | ya |
| GET | /api/client/{token}/schedule | Client/ScheduleController@show | sama | ya |
| POST | /api/client/{token}/schedule/confirm | Client/ScheduleConfirmationController | sama | ya |
| POST | /api/client/{token}/schedule/reschedule | Client/ScheduleRescheduleController | sama | ya |
| GET | /api/client/{token}/issue | Client/IssueController@show | sama | ya |
| POST | /api/client/{token}/issue/choice | Api/Client/IssueChoiceController | sama | tidak |
| POST | /api/client/{token}/issue/resend | Client/ReplacementController | sama | ya |

### 4.7 Publik, internal, dan dokumentasi

| Method | Path | Controller@method | Middleware | OpenAPI |
| --- | --- | --- | --- | --- |
| GET | /api/track/{resi} | Api/Tracking/TrackController | force.json, client.headers, throttle:track-resi | ya |
| POST | /api/internal/whatsapp/lookup | Api/Internal/WhatsappLookupController | force.json, internal.token | tidak |
| GET | /api/docs | Closure | api | - |
| GET | /api/docs/openapi.json | Closure | api | - |
| GET | /api/docs/openapi.yaml | Closure | api | - |

### 4.8 Halaman web

| Method | Path | Render | Middleware |
| --- | --- | --- | --- |
| GET | / | Inertia Landing | web |
| GET | /blog | Inertia Blog/Index | web |
| GET | /blog/{slug} | Inertia Blog/Show | web |
| GET | /login | Inertia Auth/Login | web |
| GET | /admin, /admin/exception | redirect | web |
| GET | /courier | redirect /courier/tasks | web |
| GET | /client, /client/tracking | redirect /client/orders | web |
| GET | /admin/shipment | Inertia admin/Shipment | staff.role:ADMIN |
| GET | /admin/report | Inertia admin/Report | staff.role:ADMIN |
| GET | /admin/history | Inertia admin/History | staff.role:ADMIN |
| GET | /admin/gps | Inertia admin/GpsChecks | staff.role:ADMIN |
| GET | /admin/geofence | Inertia admin/Geofence | staff.role:ADMIN |
| GET | /courier/tasks, /courier/delivery, /courier/proof, /courier/issue, /courier/history, /courier/profile | Inertia courier/* | web |
| GET | /client/orders, /client/history, /client/profile, /client/tracking/{resi}, /client/schedule/{resi}, /client/issue/{resi} | Inertia client/* | web |

---

## 5. Model Data dan Aturan Integritas

### 5.1 Tabel inti

| Tabel | Kolom kunci | Keterangan |
| --- | --- | --- |
| staff | id, name, role, phone, email, password, is_active | role bertipe StaffRole, email unik case-insensitive bila tidak null |
| buyers | id, name, phone (unik), password, phone_verified_at, is_active, address, area, address_type, latitude, longitude | phone dinormalkan ke E.164 oleh Attribute model |
| products | id, name, slug (unik bila tidak null), category, description, price_idr, unit, weight_kg, image_path, is_available, sort_order | diisi DemoDataSeeder sebanyak delapan produk |
| packages | id, tracking_number (unik, default AJ-FRZ-), buyer_id, created_by (boleh null), courier_id, cooler_box_code, package_category, weight_kg, status, service_type, koli_count, courier_accepted_at, client_token (unik), source, resend_of_package_id, scheduled_at, scheduled_end_at, handling_instructions, closed_at | sumber kebenaran FSM |
| package_items | package_id, product_id, quantity | unik per pasangan, quantity lebih dari 0 |
| schedule_confirmations | package_id, response, proposed_scheduled_at, requested_at, responded_at | satu PENDING per paket |
| condition_checks | package_id, courier_id, is_normal, checklist jsonb, notes, temperature_c, checked_at | tanpa updated_at (UPDATED_AT null) |
| issue_reports | check_id (unik), issue_category, description, photo_url, quality_inspection_note, decision, decision_reason, decided_by, decided_at, reported_at, buyer_choice, buyer_responded_at | satu laporan per condition_check |
| deliveries | package_id (unik), received_by, recipient_relation, photo_url, receipt_note, delivered_at | satu bukti per paket |
| delivery_milestones | package_id, milestone, courier_id, latitude, longitude, occurred_at | nilai DEPARTED atau ARRIVED |
| notifications | package_id, recipient_type, recipient_id, template, payload jsonb, status, sent_at | outbox |
| status_logs | package_id, from_status, to_status, changed_by, changed_at | ditulis trigger |
| geofence_policies | service_type (PK), radius_m, updated_by | radius 10 sampai 1000 meter |
| location_checks | package_id, staff_id, action, latitude, longitude, accuracy_m, level, reasons jsonb, distance_m, radius_m, inside_geofence, applied, override_id | bukti penilaian GPS |
| gps_lock_requests | package_id, staff_id, action, level, reasons, status, decided_by, decision_reason, decided_at | satu PENDING per paket dan aksi |

### 5.2 Endpoint dan payload ringkas

Contoh bentuk respons penting, diambil dari controller:

| Endpoint | Bentuk respons |
| --- | --- |
| GET /api/admin/shipments | shipments, paginator, filters, couriers, statusOptions |
| GET /api/admin/shipments/{resi} | package, timeline, client_url, scheduleLabel, statusLabel, tone, proofPhotoUrl, issuePhotoUrl |
| GET /api/admin/exceptions/{resi} | package, issueReport, conditionCheck, statusLabel, resolutionStatus, allowedDecisions, checklist, timeline, photoTemporaryUrl |
| GET /api/courier/tasks | newTasks, activeTasks, courier |
| GET /api/courier/deliveries/{resi} | delivery (termasuk canDepart, canArrive, canProof, canReportIssue, checklistItems, gps) |
| GET /api/client/orders | data dan meta (tab_counts, pagination, standard_temp) |
| GET /api/client/orders/{resi}/tracking | data view tracking hasil TrackingViewService |
| GET /api/track/{resi} | data: resi, status, service, category, cold_chain, destination_city, schedule_label, updated_label, timeline |
| POST /api/internal/whatsapp/lookup | found, tracking_number, client_token, client_url, template, status_label, window |

### 5.3 FSM lengkap (from ke to ke aktor)

| From | To | Aktor | Kelas pemicu |
| --- | --- | --- | --- |
| CREATED | AWAITING_CONFIRMATION | Admin | Actions/Packages/CreatePackage |
| CREATED | AWAITING_CONFIRMATION | Sistem | Actions/Client/CreateClientOrder |
| AWAITING_CONFIRMATION | CONFIRMED | Pembeli | Actions/Client/ConfirmSchedule |
| AWAITING_CONFIRMATION | RESCHEDULE_REQUESTED | Pembeli | Actions/Client/RequestReschedule |
| RESCHEDULE_REQUESTED | AWAITING_CONFIRMATION | Admin | Actions/Admin/RescheduleShipment |
| CONFIRMED | COURIER_ASSIGNED | Admin | Actions/Packages/AssignCourier |
| COURIER_ASSIGNED | CHECKED | Kurir | Actions/Courier/StartDelivery, Actions/Courier/ReportIssue |
| COURIER_ASSIGNED | CONFIRMED | Kurir | Actions/Courier/RejectTask |
| CHECKED | IN_DELIVERY | Kurir | Actions/Courier/StartDelivery |
| CHECKED | ISSUE_REPORTED | Kurir | Actions/Courier/ReportIssue |
| ISSUE_REPORTED | IN_DELIVERY | Admin | Actions/Issues/Decisions/ProceedDecisionHandler |
| ISSUE_REPORTED | CHECKED | Admin | Actions/Issues/Decisions/RecheckDecisionHandler |
| ISSUE_REPORTED | RETURNED | Admin | Actions/Issues/Decisions/ReturnDecisionHandler, ResendDecisionHandler |
| ISSUE_REPORTED | AWAITING_CONFIRMATION | Admin | Actions/Issues/Decisions/RescheduleDecisionHandler |
| IN_DELIVERY | RECEIVED | Kurir | Actions/Courier/ConfirmDelivery |
| IN_DELIVERY | ISSUE_REPORTED | Kurir | Actions/Courier/ReportIssue |
| RECEIVED | CLOSED | [TIDAK TERVERIFIKASI] | tidak ada pemanggil |
| RETURNED | CLOSED | [TIDAK TERVERIFIKASI] | tidak ada pemanggil |

Matriks di atas identik dengan seed di database/seeders/ReferenceDataSeeder.php
ditambah dua baris dari migrasi 0001_01_01_000020. Tidak ada transisi lain yang
diizinkan database.

---

## 6. Aturan Bisnis Terpusat

Aturan berikut dirujuk oleh lebih dari satu requirement, jadi didefinisikan sekali
di sini dan dipakai dengan kode BR.

| Kode | Aturan | Sumber | Dipakai oleh |
| --- | --- | --- | --- |
| BR-001 | Perubahan status paket hanya sah bila pasangan from dan to ada di tabel package_status_transitions; pelanggaran menghasilkan error check_violation dan dipetakan menjadi InvalidStatusTransition | migrasi 0001_01_01_000016, Support/PackageStatusTransitioner.php | semua FR yang mengubah status |
| BR-002 | Setiap perubahan status wajib melalui Support/PackageStatusTransitioner di dalam DB::transaction | AGENTS.md dan kode transitioner | FR-PKG-001, 005, 006, 007; seluruh Action |
| BR-003 | Aktor transisi ditulis ke sesi PostgreSQL dengan set_config app.staff_id; aksi pelanggan dan sistem memakai aktor kosong sehingga changed_by null | Support/PackageStatusTransitioner.php | FR-PKG-007 |
| BR-004 | Operasi penulisan yang rentan balapan memakai lockForUpdate pada baris paket | AssignCourier, ResolveIssue, ConfirmSchedule, RequestReschedule, ReportIssue, ConfirmDelivery, MarkArrived, RequestReplacement, RecordIssueChoice, RescheduleShipment | FR-ASG-001, FR-ISS-005, FR-TRACK-006, FR-TRACK-007 |
| BR-005 | Jadwal dipilih dalam zona Asia/Jakarta dan disimpan UTC; seluruh tampilan memakai label WIB | Support/DisplayTime.php, CreatePackage | FR-PKG-001, FR-PKG-005, FR-RPT-001, FR-ORD-003 |
| BR-006 | Jendela pengantaran pelanggan minimal 2 jam dari sekarang dan maksimal 14 hari ke depan, hanya dari slot yang terdaftar di konfigurasi | Requests/Client/RescheduleRequest.php, config coldchain.client | FR-TRACK-007 |
| BR-007 | Satu paket hanya boleh memiliki satu paket pengganti, dijaga unique index uq_packages_one_replacement | migrasi 0001_01_01_000022 | FR-ISS-007 |
| BR-008 | Penggantian paket hanya sah bila paket asal berstatus RETURNED atau CLOSED, keputusan kendala terakhir adalah RETURN, dan belum ada paket anak | Services/Client/ReplacementEligibility.php | FR-ISS-007, FR-ISS-010 |
| BR-009 | Radius geofence per segmen layanan: INSTANT_FROZEN toleransi ketat, REGULER toleransi standar; nilai diambil dari cache 60 detik | Services/Gps/GeofenceEvaluator.php, GeofencePolicy::SEGMENTS | FR-GPS-002, FR-GPS-007 |
| BR-010 | Radius hanya ditegakkan pada aksi serah terima; memulai perjalanan dan tiba di lokasi hanya mencatat jarak | GpsValidator dengan argumen enforceGeofence | FR-DEL-002, FR-DEL-003, FR-DEL-005 |
| BR-011 | GPS bersifat best effort: tanpa koordinat, tidak ada penilaian dan tidak ada blokir | GpsValidator::validate | FR-GPS-001, FR-DEL-002 |
| BR-012 | Keputusan kendala hanya boleh dari daftar yang diizinkan kategori kendala tersebut | Enums/IssueCategory::allowedDecisions | FR-ISS-005 |
| BR-013 | Keputusan kendala wajib disertai alasan minimal 10 karakter, dan ketiga kolom decision, decided_by, decided_at harus terisi bersamaan | Requests/Admin/ResolveIssueRequest.php, constraint ck_issue_reports_decided_together | FR-ISS-005 |
| BR-014 | Satu paket hanya boleh memiliki satu konfirmasi PENDING, dijaga unique index uq_schedule_confirmations_one_pending | migrasi 0001_01_01_000010 | FR-PKG-001, FR-PKG-005, FR-TRACK-006 |
| BR-015 | Semua notifikasi pelanggan keluar lewat tabel notifications dan job ke n8n; aplikasi tidak memanggil WhatsApp secara langsung | Support/NotificationQueue.php | FR-NOTIF-001, FR-NOTIF-002, FR-WA-001 |
| BR-016 | Notifikasi dengan jawaban n8n unmapped atau skipped dibiarkan berstatus QUEUED, bukan FAILED | Jobs/DispatchNotificationToN8n.php | FR-NOTIF-002 |
| BR-017 | Tautan token klien berupa hex 32 sampai 128 karakter, dicari ke kolom packages.client_token, dan kedaluwarsa setelah 30 hari untuk status terminal | Services/Client/ClientAccessService.php, config coldchain.client.link_ttl_days | FR-TRACK-002 |
| BR-018 | Kegagalan pencarian token dibatasi 20 kali per IP per menit | Services/Client/ClientAccessService.php | FR-TRACK-002 |
| BR-019 | Endpoint publik pelacakan tidak boleh mengembalikan nama, nomor telepon, alamat jalan, atau teks bebas; hanya kota tingkat kabupaten dan label tetap | Api/Tracking/TrackController.php | FR-TRACK-001 |
| BR-020 | Semua akses paket oleh kurir harus lolos PackagePolicy: kurir hanya boleh paketnya sendiri, admin bebas, selain itu 404 | app/Policies/PackagePolicy.php | seluruh FR kurir |
| BR-021 | Data pelanggan dinormalkan ke E.164 sebelum disimpan dan sebelum dicari | Support/PhoneFormatter.php, model Buyer, Request login dan register | FR-AUTH-006, FR-AUTH-007, FR-WA-003 |
| BR-022 | Foto bukti disimpan pada disk lokal dengan pola folder per bulan; URL sementara gagal untuk disk lokal sehingga pratinjau memakai route /api/media/preview berbatas pola path | Support/MediaStorage.php, Media/MediaPreviewController.php | FR-DEL-005, FR-ISS-002, FR-RPT-006 |
| BR-023 | Ekspor CSV menambahkan BOM UTF-8 dan menyaring formula injection dengan menambahkan apostrof di depan nilai yang dimulai karakter berbahaya | Support/Csv/StreamedCsv.php | FR-RPT-002, FR-RPT-004, FR-RPT-005 |
| BR-024 | Respons API pelanggan memasang header X-Robots-Tag noindex, Referrer-Policy no-referrer, Cache-Control private no-store, X-Content-Type-Options nosniff | Middleware/ClientSecurityHeaders.php | FR-ORD-001, FR-TRACK-001 |
| BR-025 | Idempotensi aksi pelanggan: konfirmasi dan jadwal ulang yang ditekan dua kali tidak menghasilkan transisi ganda; pilihan kendala pertama yang menang | ConfirmSchedule, RequestReschedule, RecordIssueChoice | FR-TRACK-006, FR-TRACK-007, FR-ISS-009 |
| BR-026 | Keputusan yang sudah ada tidak boleh ditimpa; permintaan kedua ditolak 422 | ExceptionDecisionController, GpsCheckController@decide | FR-ISS-005, FR-GPS-006 |

---

## 7. Integrasi Eksternal

### 7.1 n8n outbound

| Aspek | Nilai | Bukti |
| --- | --- | --- |
| URL webhook | config coldchain.n8n.webhook_url, default http://n8n/webhook/anteraja-frozen/notify | config/coldchain.php |
| Header autentikasi | X-Anteraja-Token berisi N8N_WEBHOOK_TOKEN | Jobs/DispatchNotificationToN8n.php |
| Body | notification_id, template, recipient_type, payload | idem |
| Timeout | config coldchain.n8n.timeout, default 10 detik | idem |
| Retry | tries 3, backoff 10 lalu 30 detik | idem |
| Respons yang bermakna | delivered true berarti SENT; reason unmapped atau skipped berarti tetap QUEUED; selain itu FAILED | idem |
| Aktivasi | hanya bila config enabled true dan webhook_url terisi | idem |

Payload notifikasi selalu diperkaya oleh NotificationQueue dengan recipient_type,
recipient_name, recipient_phone (E.164), tracking_number, client_token,
courier_name, window, dan untuk penerima BUYER juga client_url. Bila payload
template belum memuat address, buyer_name, atau window, nilai itu diisi otomatis.

### 7.2 WAHA dan n8n inbound

| Aspek | Nilai | Bukti |
| --- | --- | --- |
| Webhook inbound | http://n8n/webhook/anteraja-frozen/waha-inbound | deploy/k8s/waha/configmap.yaml |
| Header validasi | X-Anteraja-Hook dicocokkan credential Anteraja WAHA Hook | deploy/n8n/README.md |
| Resolusi LID | GET http://waha/api/anteraja/lids/{id} memakai kredensial Anteraja WAHA | deploy/n8n/README.md |
| Model bahasa | deepseek-v4-flash pada endpoint OpenAI-compatible | deploy/n8n/README.md |
| State percakapan | tabel anteraja_chat_state di database n8n, kolom last_template dan context | deploy/n8n/state-table.sql |
| Aksi yang dapat dipanggil | POST schedule/confirm, POST schedule/reschedule, POST issue/choice pada API guest | deploy/n8n/README.md |
| Uji | suite Node deploy/n8n/tests/inbound-scenarios.mjs dijalankan CI | .github/workflows/n8n-scenarios.yaml |

### 7.3 Endpoint internal lookup

| Aspek | Nilai | Bukti |
| --- | --- | --- |
| Route | POST /api/internal/whatsapp/lookup | routes/api.php |
| Autentikasi | header X-Anteraja-Token sama dengan config coldchain.n8n.token; 401 bila salah, 503 bila token belum disetel | Middleware/VerifyInternalToken.php |
| Body | phone dan resi, keduanya wajib, maksimal 32 karakter | Api/Internal/WhatsappLookupController.php |
| Pencocokan | resi harus milik pembeli dengan nomor tersebut setelah normalisasi E.164 | idem |
| Respons tidak cocok | found false dengan HTTP 200, bukan error | idem |
| Respons cocok | found true, tracking_number, client_token, client_url, template, status_label, window | idem |
| Perhitungan template | dari OrderPresenter::resolveActionRequired, dipetakan ke schedule_request, issue_options, issue_decision, in_delivery, atau none | idem |

### 7.4 Google Maps dan tautan telepon

Aplikasi kurir hanya membentuk tautan luar: https://www.google.com/maps/search/
dengan koordinat pembeli bila ada, atau dengan alamat bila koordinat kosong, dan
tautan tel: berformat E.164 (Courier/DeliveryController@show).

---

## 8. Spesifikasi UI Fungsional

Bagian ini menjelaskan elemen, aksi, validasi, dan keadaan kosong per halaman.
Frontend dimiliki tim frontend sesuai AGENTS.md; uraian berikut adalah kontrak
data yang diharapkan halaman, bukan instruksi perubahan UI.

### 8.1 Admin

| Halaman | Data yang dimuat | Aksi | Validasi di klien | Keadaan kosong dan error |
| --- | --- | --- | --- | --- |
| admin/Shipment | GET /api/admin/shipments dengan filter q, status, courier, schedule, date, source, per_page | buat shipment, assign kurir, reschedule, buka modal detail, ekspor CSV | form shipment: produk wajib minimal satu, titik lokasi tujuan wajib dipin di peta, tanggal tidak boleh lampau, jam selesai setelah jam mulai | tabel kosong menampilkan pesan tanpa baris; 422 menampilkan pesan pertama dari errors |
| admin/Report | GET /api/admin/reports dengan date dan category | pilih tanggal, filter kategori, ekspor CSV | tanggal format Y-m-d | hari tanpa data menampilkan KPI nol dan daftar kendala kosong |
| admin/History | GET /api/admin/history dengan q dan status | cari, filter status, ekspor CSV | q maksimal 100 karakter | hasil kosong menampilkan tabel tanpa baris |
| admin/GpsChecks | GET /api/admin/gps-checks dengan status dan level | pilih tab, filter level, putuskan permintaan tinjauan | alasan keputusan wajib minimal 10 karakter | daftar checks dan locks kosong; ringkasan menampilkan angka nol |
| admin/Geofence | GET /api/admin/geofence | ubah radius per segmen dan simpan | radius wajib dalam batas segmen | segmen selalu dua baris dari konfigurasi |
| Auth/Login | POST /api/auth/login | masuk, ingat saya | email format email, password wajib | pesan gagal "Hanya admin aktif yang dapat mengakses panel ini" bila bukan admin aktif |

### 8.2 Kurir

| Layar | Data | Aksi | Validasi | Keadaan kosong |
| --- | --- | --- | --- | --- |
| courier/Tasks dan layar native Tasks | GET /api/courier/tasks | terima, tolak dengan alasan, lihat jarak dan ETA | alasan tolak maksimal 200 karakter | dua daftar kosong dengan pesan tugas baru dan tugas aktif |
| courier/Delivery | GET /api/courier/deliveries/{resi} | buka peta, telepon, berangkat, tiba, buka bukti, buka kendala, minta tinjauan GPS | checklist dan suhu opsional numerik -60 sampai 40 | blok GPS menampilkan tingkat, alasan, jarak, radius, dan tombol minta tinjauan |
| courier/Proof | GET dan POST .../proof | ambil foto, isi nama penerima, pilih hubungan, catatan | foto wajib maksimal 5 MB format jpeg, jpg, png, webp; nama penerima 2 sampai 120 karakter; hubungan wajib | 422 mengembalikan pesan dan pengguna tetap di layar |
| courier/Issue | GET dan POST .../issue | pilih kategori, isi catatan, isi suhu bila suhu_naik, ambil foto | deskripsi 10 sampai 200 karakter, foto wajib 5 MB | paket di luar status yang diizinkan mendapat 422 dengan redirect ke halaman pengantaran |
| courier/History | GET /api/courier/history | pilih halaman | q dan status opsional | rincian kosong dan ringkasan selesai hari ini |
| courier/Profile | GET /api/courier/profile | pindah tema, keluar | - | statistik selalu tiga kartu: selesai hari ini, tepat waktu, rating |

### 8.3 Pelanggan

| Layar | Data | Aksi | Validasi | Keadaan kosong |
| --- | --- | --- | --- | --- |
| Client Landing | GET /api/client/catalog | tuju katalog, daftar, atau masuk | - | katalog kosong menampilkan keadaan kosong |
| Client Catalog dan ProductDetail | GET /api/client/catalog dan /{slug} | pilih jumlah, tambah ke keranjang | jumlah 1 sampai 20 per produk | slug tidak dikenal menampilkan kartu tidak ditemukan |
| Client Register | POST /api/client/auth/register | daftar lalu lanjut checkout | nama 2 sampai 120, telepon unik maksimal 20, password minimal 8 dan terkonfirmasi, alamat 5 sampai 255 | pesan "Nomor HP ini sudah terdaftar" dan "Konfirmasi password tidak sama" |
| Client Checkout | GET /api/client/catalog dan /api/client/me, POST /api/client/orders | kirim pesanan | items 1 sampai 20 baris, quantity 1 sampai 20, tanggal tidak lampau, jam selesai setelah mulai, titik lokasi penerima wajib dipin (latitude dan longitude) | tamu diarahkan ke register; produk tidak tersedia menghasilkan 422 PRODUCT_UNAVAILABLE |
| Client OrderSuccess | struk dari POST orders | buka beranda pesanan | - | struk kedaluwarsa atau kosong menampilkan keadaan kosong |
| Client Orders dan History | GET /api/client/orders dengan tab | pindah tab, cari, buka layar lanjutan | q maksimal 100, per_page 1 sampai 50 | tab kosong menampilkan daftar kosong; resi tidak dikenal menampilkan kartu tidak ditemukan |
| Client Tracking | GET .../tracking | telepon kurir bila status pengantaran, buka jadwal atau kendala | - | gangguan panggilan API menampilkan keadaan coba lagi |
| Client Schedule | GET .../schedule | konfirmasi jadwal atau minta slot lain | slot wajib salah satu daftar; tanggal minimal hari ini dan maksimal 14 hari | paket tanpa jadwal menampilkan "Belum ditentukan" |
| Client Issue | GET .../issue | minta pengiriman ulang bila layak, hubungi layanan pelanggan | - | tanpa laporan kendala, endpoint mengembalikan redirect_url ke tracking |
| Client Profile | GET /api/client/me | pindah tema, hubungi layanan pelanggan, keluar | - | kanal dukungan selalu tersedia dari konfigurasi |

### 8.4 Publik

| Halaman | Data | Aksi | Keadaan kosong |
| --- | --- | --- | --- |
| Landing | produk highlight, konfigurasi unduhan APK, data statis landing | lacak resi, unduh APK, masuk ke tiga konsol, buka blog | produk highlight kosong menyembunyikan kartu |
| Blog Index dan Show | resources/js/data/posts.ts | buka artikel | slug tidak dikenal menampilkan halaman artikel tanpa konten |

Layar native tidak boleh memuat web view; ClientNativeScreensTest dan
CourierNativeScreensTest memverifikasi hal itu (test_no_screen_renders_a_web_view).

---

## 9. Non-Functional Requirements

### 9.1 Performa

| NFR | Nilai atau mekanisme | Bukti |
| --- | --- | --- |
| Runtime HTTP | FrankenPHP dalam mode worker Octane | Dockerfile, composer.json octane |
| Pencegahan N+1 | Model::shouldBeStrict aktif di luar produksi dan relasi di-eager load dengan with | AppServiceProvider.php, Queries/* |
| Query agregat alih-alih banyak query | jumlah tab pesanan dihitung satu query FILTER, KPI laporan satu selectOne | Queries/ClientOrderListQuery.php, DailyReportQuery.php |
| Cache KPI hari lampau | 600 detik per tanggal | Admin/ReportController@index |
| Cache radius geofence | 60 detik, dihapus saat admin menyimpan | Services/Gps/GeofenceEvaluator.php, GeofenceController@update |
| Batas jumlah baris pada konsol GPS | 50 baris terakhir per kueri | Admin/GpsCheckController@index |
| Paginasi | shipment per_page 10, 25, atau 50; riwayat 15; pesanan pelanggan per_page 1 sampai 50 | IndexShipmentRequest, HistoryController, OrderIndexRequest |

### 9.2 Keamanan

| NFR | Mekanisme | Bukti |
| --- | --- | --- |
| Pemisahan realm token | ability courier dan client dipisah; test isolasi ability ada | routes/api.php, ClientApiAuthTest::test_sanctum_ability_isolation |
| Proteksi token yang dicabut | token kurir diresolusi tiap permintaan, bukan di-cache guard | Middleware/AuthenticateCourier.php |
| Rate limit autentikasi | 5 per menit per email atau phone plus IP untuk login kurir dan klien | AppServiceProvider.php |
| Rate limit pencarian resi | 20 per menit per IP | AppServiceProvider.php |
| Rate limit kegagalan token klien | 20 per menit per IP, hit dihitung saat gagal | Services/Client/ClientAccessService.php |
| Kepemilikan data | client.owned memastikan paket milik pembeli yang login; PackagePolicy membatasi kurir | ResolveOwnedPackage.php, PackagePolicy.php |
| Header keamanan klien | noindex, no-referrer, private no-store, nosniff | ClientSecurityHeaders.php |
| Rahasia internal | perbandingan token memakai hash_equals; endpoint mati dengan 503 bila token belum disetel | VerifyInternalToken.php |
| Sanitasi CSV | nilai yang dimulai karakter formula diberi prefiks apostrof | StreamedCsv.php |
| Batas foto | maksimal 5 MB, hanya jpeg, jpg, png, webp | ConfirmDeliveryRequest, ReportIssueRequest |
| Pengunjung tanpa sesi hanya boleh lihat data non-identitas | tabel STEPS tetap dan kota tingkat kabupaten | TrackController.php |

### 9.3 Reliabilitas

| NFR | Mekanisme | Bukti |
| --- | --- | --- |
| Atomicitas mutasi | DB::transaction di setiap Action mutasi | app/Actions/** |
| Pencegahan balapan | lockForUpdate pada baris paket, kadang pada baris konfirmasi dan laporan | BR-004 |
| Job tahan gangguan | tries 3 dengan backoff 10 dan 30 detik, exception koneksi dilempar ulang agar dicoba lagi | Jobs/DispatchNotificationToN8n.php |
| Job dikirim setelah commit | DispatchNotificationToN8n::dispatch(...)->afterCommit() | Support/NotificationQueue.php |
| Idempotensi aksi pelanggan | pemeriksaan status awal sebelum transisi | BR-025 |
| Kegagalan foto tidak meninggalkan file yatim | MediaStorage::delete dipanggil saat transaksi gagal atau duplikat | ConfirmDelivery, ReportIssue |
| Konflik unik penggantian tidak menjadi error pengguna | QueryException 23505 pada index penggantian ditangkap dan baris yang ada dikembalikan | Actions/Client/RequestReplacement.php |

### 9.4 Observability

| NFR | Mekanisme | Bukti |
| --- | --- | --- |
| Jejak status | tabel status_logs terisi otomatis oleh trigger | migrasi 0001_01_01_000016 |
| Jejak penilaian lokasi | seluruh penilaian tersimpan apa pun hasilnya | LocationCheck, GpsValidator |
| Jejak upaya notifikasi | kolom status dan payload.delivery_error pada tabel notifications | Job DispatchNotificationToN8n |
| Log template yang belum dipetakan | Log::info dengan notification_id dan template | Job DispatchNotificationToN8n |
| Health check | route /up | bootstrap/app.php |
| Pengujian skenario n8n di CI | workflow n8n-scenarios.yaml menjalankan suite Node | .github/workflows/n8n-scenarios.yaml |

### 9.5 Deployment dan quality gate

| NFR | Mekanisme | Bukti |
| --- | --- | --- |
| Image multi-stage | tahap assets Bun, tahap vendor Composer, tahap runtime FrankenPHP | Dockerfile |
| Package manager frontend | Bun, dilarang package-lock.json | package.json packageManager bun@1.4.2, AGENTS.md |
| Database | PostgreSQL, bukan SQLite untuk running dan pengujian | phpunit.xml mengomentari setelan sqlite, AGENTS.md |
| Orkestrasi | manifest Kubernetes untuk web, n8n, WAHA, database | deploy/k8s/** |
| CI | docker.yaml, release.yaml, android-apk.yaml, n8n-scenarios.yaml | .github/workflows |
| Format kode | Laravel Pint dan php-cs-fixer | composer.json, .php-cs-fixer.php, AGENTS.md |

---

## 10. Matriks Traceability

Matriks lengkap FR terhadap route, controller, action, test, dan halaman UI berada
di berkas modul masing-masing pada tabel Traceability setiap requirement. Ringkasan
tingkat atas:

| Modul | FR | Action terpakai | Test utama | Halaman UI |
| --- | --- | --- | --- | --- |
| AUTH | 10 | - | AdminAccessTest, CourierApiAuthTest, ClientApiAuthTest, ClientStorefrontTest | Auth/Login, Login native, Register native, Profile native |
| PKG | 8 | CreatePackage, RescheduleShipment, PackageStatusTransitioner | AdminShipmentTest, ClientFlowTest, AdminConsoleWiringTest | admin/Shipment |
| ASG | 5 | AssignCourier, AcceptTask, RejectTask | AdminShipmentTest, CourierFlowTest | admin/Shipment, courier/Tasks, Tasks native |
| DEL | 7 | StartDelivery, MarkArrived, ConfirmDelivery | CourierFlowTest, AdminConsoleE2ETest | courier/Delivery, courier/Proof, Delivery native, Proof native |
| GPS | 7 | GpsValidator, FakeGpsDetector, GeofenceEvaluator | GpsDetectionTest, CourierGpsValidationTest, AdminGpsConsoleTest | admin/GpsChecks, admin/Geofence, Delivery native |
| ISS | 10 | ReportIssue, ResolveIssue, lima handler, RecordIssueChoice, RequestReplacement | CourierFlowTest, AdminExceptionTest, ClientFlowTest, WhatsAppNotificationFlowTest | courier/Issue, admin/Shipment, client/Issue, Issue native |
| NOTIF | 3 | NotificationQueue, DispatchNotificationToN8n, RemindUnansweredConfirmations | WhatsAppNotificationFlowTest, ClientFlowTest | - |
| WA | 3 | WhatsappLookupController, NotificationQueue | WhatsappLookupTest, WhatsAppNotificationFlowTest | - |
| TRACK | 7 | ClientAccessService, ClientScreenResolver, tiga ViewService, ConfirmSchedule, RequestReschedule | ClientFlowTest, ClientUnitTest, PublicTrackingTest | client/Tracking, client/Schedule, Tracking native, Schedule native |
| ORD | 5 | CreateClientOrder, ClientOrderListQuery, OrderPresenter | ClientStorefrontTest, ClientApiAuthTest, ClientFlowTest | Catalog, ProductDetail, Checkout, Orders, History native |
| RPT | 6 | ShipmentIndexQuery, HistoryQuery, DailyReportQuery, StreamedCsv, MediaStorage | AdminShipmentTest, AdminExceptionTest, AdminConsoleWiringTest, ColdChainUnitTest | admin/Report, admin/History, admin/Shipment |
| LAND | 3 | - | LandingPageTest, AdminConsoleWiringTest | Landing, Blog/Index, Blog/Show |
| MOB | 6 | MobileApi, MobileToken, ClientCart, ClientReceipt, Appearance | CourierNativeScreensTest, ClientNativeScreensTest | seluruh layar native |

---

## 11. Temuan dan Ketidakkonsistenan

### 11.1 Drift antara OpenAPI dan route aktual

docs/openapi.yaml menyatakan versi 1.0.0 dan mendata 46 path. Route aktual jauh
lebih banyak. Endpoint yang ADA di kode tetapi TIDAK ada di OpenAPI:

| Endpoint | Bukti |
| --- | --- |
| POST /api/client/auth/login | routes/api.php |
| POST /api/client/auth/logout | routes/api.php |
| GET /api/client/me | routes/api.php |
| POST /api/client/orders | routes/api.php |
| GET /api/client/orders/{tracking_number} | routes/api.php |
| GET /api/client/orders/{tracking_number}/tracking | routes/api.php |
| GET /api/client/orders/{tracking_number}/schedule | routes/api.php |
| POST /api/client/orders/{tracking_number}/schedule/confirm | routes/api.php |
| POST /api/client/orders/{tracking_number}/schedule/reschedule | routes/api.php |
| GET /api/client/orders/{tracking_number}/issue | routes/api.php |
| POST /api/client/orders/{tracking_number}/issue/resend | routes/api.php |
| Seluruh grup /api/guest/{token} termasuk claim, tracking, schedule, confirm, reschedule, issue, issue/choice | routes/api.php |
| POST /api/client/{token}/claim | routes/api.php |
| POST /api/client/{token}/issue/choice | routes/api.php |
| POST /api/internal/whatsapp/lookup | routes/api.php |
| GET /api/docs, /api/docs/openapi.json, /api/docs/openapi.yaml | routes/api.php |

Catatan tambahan: OpenAPI hanya mendokumentasikan /client/auth/register, bukan
login klien, sehingga kontrak publik untuk aplikasi pelanggan belum lengkap.
OpenAPI juga memakai skema token /client/{token} sebagai representasi utama
pelanggan, padahal aplikasi pelanggan saat ini memakai jalur akun
/api/client/orders/*.

### 11.2 Perbedaan perilaku antar jalur yang serupa

| Temuan | Bukti | Implikasi |
| --- | --- | --- |
| Grup /api/guest/{token} memasang force.json, grup legacy /api/client/{token} tidak | routes/api.php | format error dapat berbeda antara dua tautan yang fungsinya sama |
| Grup legacy punya endpoint issue/resend, grup guest tidak | routes/api.php | tautan gaya baru tidak bisa meminta pengiriman ulang, tautan lama bisa |
| Controller Client/* adalah duplikat Api/Client/* untuk endpoint yang sama | app/Http/Controllers/Client dan Api/Client | dua tempat harus diubah bila payload berubah |

### 11.3 Status dan kolom yang tidak terpakai

| Temuan | Bukti |
| --- | --- |
| Transisi RECEIVED ke CLOSED dan RETURNED ke CLOSED ada di matriks tetapi tidak ada pemanggil | pencarian transitioner di app/ hanya menemukan pemanggilan yang tercantum di tabel 5.3 |
| Kolom packages.closed_at tidak pernah ditulis | hanya muncul di fillable dan casts model Package |
| Status Nothing pada OrderPresenter::resolveActionRequired mengembalikan null untuk status di luar daftar | Services/Client/OrderPresenter.php |
| Kategori kendala cod_ditolak dan label COD hanya sebagai kategori kendala; tidak ada alur pembayaran | Enums/IssueCategory.php |

### 11.4 Angka yang tampil sebagai konstanta

| Angka | Lokasi | Status |
| --- | --- | --- |
| Tepat waktu 96 persen | Resources/Client/BuyerProfileResource.php | konstanta |
| Tepat waktu 97 persen dan rating 4.8 | Courier/ProfileController.php | konstanta |
| SLA 96 persen pada meta daftar pesanan | Api/Client/OrderController@index | konstanta |
| Kapasitas armada 1500 per hari | config/coldchain.php | konfigurasi manual |
| Jangkauan instan 2 sampai 4 jam | resources/js/data/landing.ts | konten statis |

### 11.5 Data contoh yang masih hidup di kode backend

App/Support/ClientData.php dan App/Support/CourierData.php masih menyimpan lima
pesanan, katalog profil, dan riwayat contoh. Keduanya masih dipakai untuk
membentuk tautan WhatsApp layanan pelanggan dan pesan bantuan pada layar native
(pemanggilan ClientData::whatsappLink dan ClientData::helpMessage di
Client/Issue.php, Client/Profile.php, Client/Schedule.php, Client/Tracking.php).
Sisanya sudah tidak dibaca layar mana pun karena layar memakai MobileApi. Kelas
ini tidak berbahaya untuk data, tetapi berpotensi menyesatkan pembaca kode karena
namanya tidak menandai dirinya sebagai fixture.

### 11.6 Ketidaksesuaian kecil yang perlu diputuskan

| Temuan | Bukti | Rekomendasi |
| --- | --- | --- |
| Nama hub berbeda antara dua konfigurasi: hub_name Hub Jaksel dan hub.display_name Hub Jakarta Selatan | config/coldchain.php | satukan ke satu nilai |
| Profil kurir menampilkan kendaraan Motor Box #04 sebagai konstanta | Courier/ProfileController.php | ambil dari data bila nanti ada kolomnya |
| Handler keputusan Resend tidak menangkap duplikat index penggantian, berbeda dengan RequestReplacement | bandingkan ResendDecisionHandler dan Actions/Client/RequestReplacement | seragamkan penanganan |
| Dua endpoint berbeda bisa menandai laporan kendala terbuka dengan cara berbeda: IssueReportController memakai join manual, DeliveryController memakai whereHas | Courier/IssueReportController@create, Courier/DeliveryController@show | satukan ke satu scope query |

