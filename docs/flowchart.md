# Flow Map Anteraja Frozen: Good Case sampai Edge Case

Dokumen ini memetakan alur paket beku Anteraja sejak paket dibuat sampai proses ditutup, lengkap dengan cabang kasus di luar jalur normal. Semua diagram ditulis dengan Mermaid sehingga bisa dirender di GitHub, VS Code, dan Obsidian, dan pada saat yang sama tetap terbaca sebagai teks biasa oleh agent maupun developer.

Dua hal yang perlu dipegang saat membaca dokumen ini:

1. **Status dan panah di diagram bukan karangan.** Semuanya berasal dari tabel `package_statuses` dan `package_status_transitions` (lihat `database/seeders/ReferenceDataSeeder.php`) dan dijalankan lewat satu pintu `App\Support\PackageStatusTransitioner`. Kalau FSM di database berubah, dokumen ini yang harus disesuaikan lebih dulu.
2. **Yang digambar hanya alur yang sudah punya implementasi.** Improvisasi di luar jalur dasar, misalnya kurir mengobrol langsung dengan pembeli di luar alur notifikasi, belum masuk. Bagian 8 mencatat bagian yang belum terhubung.

Konvensi penulisan: nama status selalu memakai kode database dalam huruf kapital, placeholder nomor resi ditulis `{resi}` pada tabel dan `RESI` pada label diagram, garis penuh berarti jalur normal, garis putus-putus berarti cabang edge case. Bagian 3 merangkai semuanya menjadi satu diagram utuh, sementara Bagian 5 memecah setiap cabang supaya lebih enak dibaca satu per satu.

---

## 1. Daftar Status Paket

| Kode | Label | Menunggu aksi? | Terminal? | Arti |
|---|---|:---:|:---:|---|
| `CREATED` | Dibuat | tidak | tidak | Baris paket sudah ada, permintaan konfirmasi belum dikirim |
| `AWAITING_CONFIRMATION` | Menunggu Konfirmasi | ya | tidak | Permintaan jadwal sudah dikirim, menunggu jawaban pembeli |
| `CONFIRMED` | Dijadwalkan | tidak | tidak | Jadwal disetujui pembeli, siap ditugaskan ke kurir |
| `COURIER_ASSIGNED` | Kurir Ditugaskan | tidak | tidak | Kurir sudah dipilih, checklist kemasan belum diisi |
| `CHECKED` | Sudah Diperiksa | tidak | tidak | Checklist kemasan dan suhu sudah diisi |
| `ISSUE_REPORTED` | Kendala | ya | tidak | Ada laporan kendala yang menunggu keputusan admin |
| `IN_DELIVERY` | Sedang Diantar | tidak | tidak | Kurir sedang di jalan |
| `RECEIVED` | Diterima | tidak | tidak | Serah terima sudah tercatat |
| `RETURNED` | Dikembalikan | tidak | tidak | Paket ditarik ke hub untuk retur atau diganti resi baru |
| `CLOSED` | Selesai | tidak | ya | Proses ditutup dan masuk laporan |

Dua status penanda antrian kerja, `AWAITING_CONFIRMATION` dan `ISSUE_REPORTED`, dihitung dari kolom `is_waiting` sehingga panel admin bisa menampilkan pekerjaan yang menggantung tanpa logika tambahan.

## 2. Peta Transisi Status (FSM)

```mermaid
stateDiagram-v2
    [*] --> CREATED
    CREATED --> AWAITING_CONFIRMATION : sistem mengirim permintaan konfirmasi
    AWAITING_CONFIRMATION --> CONFIRMED : pembeli memilih siap menerima atau memilih jendela lain
    CONFIRMED --> COURIER_ASSIGNED : admin menugaskan kurir
    COURIER_ASSIGNED --> AWAITING_CONFIRMATION : kurir atau admin mengajukan jadwal (opsional jendela baru)
    COURIER_ASSIGNED --> CONFIRMED : kurir menolak penugasan
    COURIER_ASSIGNED --> CHECKED : kurir mengisi checklist kemasan
    CHECKED --> IN_DELIVERY : kondisi normal, kurir berangkat
    CHECKED --> ISSUE_REPORTED : checklist menemukan kendala
    IN_DELIVERY --> RECEIVED : serah terima tercatat
    IN_DELIVERY --> ISSUE_REPORTED : kendala di lapangan
    ISSUE_REPORTED --> IN_DELIVERY : keputusan PROCEED
    ISSUE_REPORTED --> CHECKED : keputusan RECHECK
    ISSUE_REPORTED --> AWAITING_CONFIRMATION : keputusan RESCHEDULE
    ISSUE_REPORTED --> RETURNED : keputusan RETURN atau RESEND
    RECEIVED --> CLOSED : proses ditutup
    RETURNED --> CLOSED : klaim retur selesai
    CLOSED --> [*]

    note right of CHECKED
        Menerima tugas tidak mengubah status.
        Yang memindahkan paket ke CHECKED
        adalah checklist pra-pengantaran.
    end note
    note right of CLOSED
        Dua transisi menuju CLOSED diizinkan
        database, tetapi belum ada endpoint
        atau job yang menjalankannya.
    end note
```

## 3. Alur Lengkap dalam Satu Diagram

Bagian ini adalah versi gabungan yang Anda minta. Semua jalur, baik good case maupun edge case, digambar dalam satu diagram karena cabang-cabangnya memang kembali bermuara ke jalur utama yang sama.

### 3.1 Satu Diagram untuk Semua Jalur

Mulai dari Fase 1 di bagian atas lalu turun sampai Fase 7. Garis penuh adalah jalur normal, garis putus-putus adalah lompatan yang hanya terjadi pada kondisi tertentu, dan setiap cabang yang memutar paket kembali ke fase sebelumnya digambar sebagai panah yang menunjuk balik ke node fase itu. Di situlah terlihat bahwa E1, E3, dan keputusan `RESCHEDULE` mengembalikan paket ke tahap yang sudah dilewati, bukan menciptakan alur baru.

```mermaid
flowchart TD
    subgraph F1["Fase 1 - Paket dibuat"]
        CAT["[Pembeli] jelajahi katalog<br/>GET /api/client/catalog"]
        ORD["[Pembeli] checkout: produk, alamat, jadwal<br/>POST /api/client/orders"]
        HUB["[Admin] atau input paket dari hub<br/>POST /api/admin/shipments"]
        CREATED(("CREATED"))
        AWC(("AWAITING_CONFIRMATION"))
        REQ["[Sistem] kirim permintaan konfirmasi<br/>template schedule_request"]
    end

    subgraph F2["Fase 2 - Konfirmasi jadwal"]
        DEC1{"[Pembeli] siap menerima?"}
        CONF(("CONFIRMED"))
        NRESCH["[Sistem] notifikasi reschedule_requested ke admin"]
        ADRS["[Admin/Kurir] ajukan jadwal<br/>POST .../request-confirmation"]
        NEWREQ["[Sistem] kirim jadwal baru<br/>template schedule_rescheduled"]
        OVERDUE["[Sistem] reminder pembeli belum menjawab<br/>confirmations:remind-unanswered"]
        ADMAN["[Admin] hubungi pembeli secara manual"]
    end

    subgraph F3["Fase 3 - Penugasan kurir"]
        ASSIGN["[Admin] tugaskan kurir<br/>POST /api/admin/shipments/RESI/assign"]
        CA(("COURIER_ASSIGNED"))
        TASK["[Kurir] tugas muncul di aplikasi<br/>tidak ada WhatsApp ke kurir"]
        KACC{"[Kurir] terima tugas?"}
        KYES["[Kurir] terima<br/>courier_accepted_at diisi, status tidak berubah"]
        KNO["[Kurir] tolak<br/>POST /api/courier/tasks/RESI/reject"]
        REJ["[Sistem] status kembali ke CONFIRMED<br/>courier_id dikosongkan"]
        NADM["[Sistem] notifikasi task_rejected ke admin"]
    end

    subgraph F4["Fase 4 - Pemeriksaan kondisi"]
        CHK["[Kurir] isi checklist kemasan dan suhu"]
        COND{"Kondisi normal?"}
        CHKED(("CHECKED"))
    end

    subgraph F5["Fase 5 - Pengantaran"]
        DEPART["[Kurir] berangkat mengantar<br/>POST deliveries/RESI/depart"]
        INDEL(("IN_DELIVERY"))
        NOTW["[Sistem] notifikasi on_the_way ke pembeli"]
        ARRIVE["[Kurir] tiba di lokasi<br/>POST deliveries/RESI/arrive"]
        NARR["[Sistem] notifikasi courier_arrived ke pembeli"]
        ROAD{"[Kurir] ada kendala di jalan?"}
        RPT["[Kurir] lapor kendala<br/>POST deliveries/RESI/issue"]
    end

    subgraph F6["Fase 6 - Kendala dan keputusan admin"]
        ISSUE(("ISSUE_REPORTED"))
        NISSUE["[Sistem] notifikasi issue_reported ke admin<br/>dan issue_options ke pembeli"]
        BCHOICE["[Pembeli] pilih opsi 1 sampai 4 di WhatsApp<br/>tersimpan di issue_reports.buyer_choice"]
        PANEL["[Admin] buka panel kendala<br/>GET /api/admin/exceptions/RESI"]
        CATK{"Kategori kendala"}
        OPT1{"Opsi: RETURN, RESEND,<br/>PROCEED, RECHECK"}
        OPT2{"Opsi: RESCHEDULE, RETURN"}
        DP["[Admin] PROCEED"]
        DR["[Admin] RECHECK"]
        DS["[Admin] RESCHEDULE"]
        DRT["[Admin] RETURN"]
        DRS["[Admin] RESEND"]
        RRET(("RETURNED"))
        RNEW(("Paket baru<br/>AWAITING_CONFIRMATION"))
    end

    subgraph F7["Fase 7 - Serah terima dan penutupan"]
        PROOF["[Kurir] isi bukti serah terima<br/>POST deliveries/RESI/proof"]
        RECV(("RECEIVED"))
        NDLV["[Sistem] notifikasi delivered ke pembeli"]
        REPORT["[Sistem] paket masuk rekap harian dan riwayat"]
        GAP["Belum terhubung<br/>CLOSED dan closed_at belum punya eksekutor"]
    end

    CAT --> ORD
    ORD --> CREATED
    HUB --> CREATED
    CREATED --> AWC
    AWC --> REQ
    REQ --> DEC1
    DEC1 -->|"siap menerima"| CONF
    DEC1 -->|"minta jadwal lain, E1"| CONF
    DEC1 -->|"minta jadwal lain"| NRESCH
    NRESCH --> CONF
    CA -->|"admin/kurir ajukan jadwal"| ADRS
    ADRS --> NEWREQ
    NEWREQ --> AWC
    DEC1 -.->|"tidak menjawab, E2"| OVERDUE
    OVERDUE --> ADMAN
    ADMAN -.-> DEC1
    CONF --> ASSIGN
    ASSIGN --> CA
    CA --> TASK
    TASK --> KACC
    KACC -->|"terima"| KYES
    KYES --> CHK
    KACC -->|"tolak, E3"| KNO
    KNO --> REJ
    REJ --> NADM
    NADM --> ASSIGN
    CHK --> COND
    COND -->|"normal"| CHKED
    CHKED --> DEPART
    COND -->|"bermasalah, E4"| RPT
    DEPART --> INDEL
    INDEL --> NOTW
    NOTW --> ARRIVE
    ARRIVE --> NARR
    NARR --> ROAD
    ROAD -->|"tidak"| PROOF
    ROAD -->|"ya, E5"| RPT
    RPT --> ISSUE
    ISSUE --> NISSUE
    NISSUE --> PANEL
    BCHOICE -.->|"masukan, bukan keputusan"| PANEL
    PANEL --> CATK
    CATK -->|"kemasan_bocor, kemasan_basah, suhu_naik"| OPT1
    CATK -->|"pembeli_tidak_ada, alamat_tidak_ditemukan, cod_ditolak"| OPT2
    OPT1 --> DP
    OPT1 --> DR
    OPT1 --> DRT
    OPT1 --> DRS
    OPT2 --> DS
    OPT2 --> DRT
    DP -->|"pengantaran diteruskan"| INDEL
    DR -->|"periksa ulang lalu berangkat"| DEPART
    DS -->|"kurir dilepas, pembeli pilih jadwal"| AWC
    DRT -->|"paket ditarik ke hub"| RRET
    DRS -->|"paket asal ditutup"| RRET
    RRET -->|"khusus RESEND, resi pengganti dibuat"| RNEW
    BCHOICE -.->|"opsi 2, paket asal tetap ISSUE_REPORTED"| RNEW
    RNEW -->|"paket baru mulai dari fase konfirmasi"| AWC
    PROOF --> RECV
    RECV --> NDLV
    NDLV --> REPORT
    RECV -.->|"belum ada eksekutor"| GAP
    RRET -.-> GAP

    classDef pembeli fill:#dbeafe,stroke:#1d4ed8,color:#0f172a
    classDef admin fill:#fef3c7,stroke:#b45309,color:#0f172a
    classDef kurir fill:#dcfce7,stroke:#15803d,color:#0f172a
    classDef sistem fill:#f1f5f9,stroke:#64748b,color:#0f172a
    classDef status fill:#ede9fe,stroke:#6d28d9,color:#0f172a

    class CAT,ORD,DEC1,BCHOICE pembeli
    class HUB,ADRS,ADMAN,ASSIGN,PANEL,CATK,OPT1,OPT2,DP,DR,DS,DRT,DRS admin
    class KACC,KYES,KNO,CHK,COND,DEPART,ARRIVE,ROAD,RPT,PROOF kurir
    class REQ,NRESCH,NEWREQ,OVERDUE,TASK,REJ,NADM,NOTW,NARR,NISSUE,NDLV,REPORT,GAP sistem
    class CREATED,AWC,CONF,CA,CHKED,INDEL,ISSUE,RRET,RNEW,RECV status
```

Warnanya menandai peran: biru untuk pembeli, kuning untuk admin, hijau untuk kurir, abu-abu untuk sistem, dan ungu untuk status paket. Setiap kotak juga sudah diawali peran di dalam kurung siku, jadi diagramnya tetap terbaca kalau warnanya tidak muncul. Kotak berbentuk kapsul adalah status di kolom `packages.status`, sedangkan kotak persegi adalah tindakan.

### 3.2 Alur yang Sama, Dilihat Per Peran

Kalau yang dibutuhkan hanya pembagian tanggung jawab tanpa percabangan, versi per peran di bawah ini lebih enak dibaca. Isinya sama dengan diagram di atas, hanya tanpa garis putus-putus edge case.

```mermaid
flowchart TD
    subgraph L1["Pembeli - app mobile dan tautan WhatsApp"]
        PG1["Jelajahi katalog produk beku<br/>GET /api/client/catalog"]
        PG2["Checkout: produk, alamat, jadwal<br/>POST /api/client/orders"]
        PG3["Terima permintaan konfirmasi jadwal"]
        PG4{"Siap menerima?"}
        PG5["Siap menerima<br/>POST orders/RESI/schedule/confirm"]
        PG6["Minta jadwal lain<br/>POST orders/RESI/schedule/reschedule"]
        PG7["Terima pesan paket sedang diantar<br/>dan pemberitahuan menjelang tiba"]
        PG8["Serah terima paket di lokasi"]
        PG9["Pesanan pindah ke tab Riwayat"]
    end

    subgraph L2["Admin dan hub"]
        AD1["Buat paket bila order datang dari hub<br/>POST /api/admin/shipments"]
        AD2["Ajukan jadwal (opsional jendela baru)<br/>POST /api/admin/shipments/RESI/request-confirmation"]
        AD3["Tugaskan kurir<br/>POST /api/admin/shipments/RESI/assign"]
        AD4["Pantau kendala<br/>GET /api/admin/exceptions"]
        AD5["Putuskan kendala<br/>POST /api/admin/exceptions/RESI/decision"]
        AD6["Rekap harian dan ekspor<br/>GET /api/admin/reports"]
    end

    subgraph L3["Kurir - app mobile"]
        KR1["Terima tugas dan instruksi handling"]
        KR2["Isi checklist kemasan dan suhu"]
        KR3{"Kondisi normal?"}
        KR4["Berangkat mengantar<br/>POST deliveries/RESI/depart"]
        KR5["Tiba di lokasi<br/>POST deliveries/RESI/arrive"]
        KR6["Isi bukti serah terima<br/>POST deliveries/RESI/proof"]
        KR7["Lapor kendala<br/>POST deliveries/RESI/issue"]
    end

    subgraph L4["Sistem dan background job"]
        SY1["Kirim permintaan konfirmasi<br/>template schedule_request"]
        SY2["Ingatkan admin bila pembeli diam<br/>confirmations:remind-unanswered"]
        SY3["Kirim notifikasi on_the_way"]
        SY4["Kirim notifikasi courier_arrived"]
        SY5["Kirim notifikasi delivered"]
        SY6["Paket masuk rekap dan riwayat"]
    end

    EDGE(["Cabang edge case, lihat Bagian 5"])

    PG1 --> PG2
    PG2 --> AD1
    AD1 --> SY1
    SY1 --> PG3
    PG3 --> PG4
    PG4 -->|"siap menerima"| PG5
    PG4 -->|"minta jadwal lain"| PG6
    PG6 -.->|"E1"| AD2
    AD2 -.->|"jadwal baru dikirim ulang"| SY1
    PG5 --> AD3
    AD3 --> KR1
    KR1 --> KR2
    KR2 --> KR3
    KR3 -->|"normal"| KR4
    KR3 -->|"bermasalah"| KR7
    KR4 --> SY3
    SY3 --> PG7
    KR4 --> KR5
    KR5 --> SY4
    SY4 --> PG7
    KR5 --> KR6
    KR6 --> SY5
    SY5 --> PG8
    PG8 --> SY6
    SY6 --> PG9
    SY6 --> AD6
    PG3 -.->|"E2"| EDGE
    KR1 -.->|"E3 bila menolak"| EDGE
    KR7 --> AD4
    AD4 --> AD5
    AD5 -.->|"E4 dan E5"| EDGE
```

Ada dua pintu masuk yang sama-sama sah untuk langkah pertama. Pembeli bisa memulai sendiri dari katalog aplikasi (`POST /api/client/orders`), atau admin memasukkan paket dari hub (`POST /api/admin/shipments`). Keduanya berakhir di titik yang sama: baris `packages` berstatus `CREATED` yang langsung dipindahkan ke `AWAITING_CONFIRMATION` supaya `status_logs` tetap lengkap.

## 4. Rincian Tiap Fase

| Fase | Aktor | Aksi | Endpoint | Status sesudah | Notifikasi |
|---|---|---|---|---|---|
| 1. Paket dibuat | Pembeli atau Admin | Checkout dari katalog, atau input manual di hub | `POST /api/client/orders`, `POST /api/admin/shipments` | `CREATED` | tidak ada |
| 2. Penugasan kurir | Admin | Pilih kurir aktif | `POST /api/admin/shipments/{resi}/assign` | `COURIER_ASSIGNED` | tidak ada (kurir melihat tugas di aplikasi) |
| 3. Permintaan konfirmasi jadwal | Kurir atau Admin | Minta penerima mengonfirmasi tanggal terima, opsional dengan jendela baru | `POST /api/courier/deliveries/{resi}/request-confirmation`, `POST /api/admin/shipments/{resi}/request-confirmation` | `AWAITING_CONFIRMATION` | `schedule_request` (atau `schedule_rescheduled` bila jendela diubah) ke penerima |
| 4. Konfirmasi jadwal | Penerima | Pilih siap menerima, atau memilih jendela lain (langsung diterapkan) | `POST .../schedule/confirm`, `POST .../schedule/reschedule` | `CONFIRMED` | `reschedule_requested` ke admin bila pembeli memilih jendela lain |
| 5. Pemeriksaan dan keberangkatan | Kurir | Isi checklist kemasan dan suhu | `POST /api/courier/deliveries/{resi}/depart` | `CHECKED` lalu `IN_DELIVERY` | `on_the_way` dan `delivery_pin` ke penerima |
| 6. Pengantaran | Kurir | Tandai tiba di lokasi | `POST /api/courier/deliveries/{resi}/arrive` | tetap `IN_DELIVERY` | `courier_arrived` ke penerima |
| 7. Serah terima | Kurir | Catat nama penerima, relasi, foto, catatan, dan PIN penerima | `POST /api/courier/deliveries/{resi}/proof` | `RECEIVED` | `delivered` ke penerima |
| 8. Penutupan | Sistem | Paket masuk rekap harian dan riwayat | `GET /api/admin/reports` | secara desain `CLOSED`, praktiknya berhenti di `RECEIVED` | tidak ada |

Fase 4 menarik untuk dicatat: satu request kurir melakukan dua transisi berurutan, `COURIER_ASSIGNED` menuju `CHECKED` menuju `IN_DELIVERY`. Kalau status sudah `CHECKED` karena keputusan `RECHECK` admin, transisi pertama dilewati dan yang dijalankan hanya `CHECKED` menuju `IN_DELIVERY`.

## 5. Cabang Edge Case

Bagian ini adalah pembesaran dari cabang di Bagian 3. Tujuannya supaya tiap kondisi bisa dibaca tenang satu per satu, lengkap dengan efek tiap keputusan terhadap status paket.

### 5.1 E1 - Pembeli meminta jadwal lain, bisa berulang

```mermaid
flowchart TD
    A["AWAITING_CONFIRMATION<br/>permintaan jadwal sudah dikirim"]
    B{"Pembeli menjawab apa?"}
    C["CONFIRMED<br/>siap menerima jadwal saat ini"]
    D["CONFIRMED<br/>jendela pilihan pembeli langsung diterapkan"]
    E["Notifikasi reschedule_requested<br/>ke semua admin aktif"]
    F["COURIER_ASSIGNED<br/>kurir ditugaskan"]
    G["Admin atau kurir mengajukan jendela baru<br/>POST .../request-confirmation"]
    H["AWAITING_CONFIRMATION<br/>konfirmasi PENDING baru; notifikasi schedule_rescheduled"]
    I["E2 bila pembeli tidak menjawab"]

    A --> B
    B -->|"siap menerima"| C
    B -->|"pilih jendela lain"| D
    B -.->|"tidak menjawab"| I
    D --> E
    C --> F
    E --> F
    F --> G
    G --> H
    H --> A
```

Pembeli yang memilih jendela lain tidak menunggu admin: jendelanya langsung diterapkan dan paket menjadi `CONFIRMED`, sementara admin tetap menerima notifikasi. Admin atau kurir dapat mengajukan jendela baru setelah paket ditugaskan (`COURIER_ASSIGNED`) lewat aksi yang sama dengan permintaan konfirmasi; paket kembali ke `AWAITING_CONFIRMATION` dan penerima mengonfirmasi sekali lagi. Permintaan pembeli bersifat idempoten: menekan tombol dua kali saat paket sudah `CONFIRMED` tidak membuat transisi kedua.

### 5.2 E2 - Pembeli tidak merespons

```mermaid
flowchart TD
    A["Status tetap AWAITING_CONFIRMATION<br/>schedule_confirmations.response = PENDING"]
    B{"requested_at melewati ambang?"}
    C["Command confirmations:remind-unanswered<br/>berjalan setiap 15 menit"]
    D["Notifikasi confirmation_overdue<br/>ke semua admin aktif"]
    E["Admin menghubungi pembeli secara manual"]
    F["Pembeli menjawab, alur kembali ke E1"]

    A --> B
    B -->|"belum"| A
    B -->|"ya"| C
    C --> D
    D --> E
    E --> F
```

Tidak ada transisi status di jalur ini. Paket sengaja dibiarkan di `AWAITING_CONFIRMATION` karena keputusan tetap menunggu manusia. Ambang waktunya diambil dari `coldchain.client.unanswered_after_minutes` dengan nilai bawaan 120 menit, dan reminder di-dedup per `confirmation_id` supaya admin tidak dibanjiri pesan berulang untuk paket yang sama.

### 5.3 E3 - Kurir menolak penugasan

```mermaid
flowchart TD
    A["COURIER_ASSIGNED<br/>tugas tampil di aplikasi kurir; tidak ada WhatsApp"]
    B{"Kurir menerima tugas?"}
    C["courier_accepted_at diisi<br/>status tetap COURIER_ASSIGNED"]
    D["COURIER_ASSIGNED menuju CONFIRMED<br/>courier_id dan courier_accepted_at dikosongkan"]
    E["Notifikasi task_rejected<br/>ke semua admin aktif"]
    F["Admin menugaskan kurir lain<br/>kembali ke COURIER_ASSIGNED"]
    G["Lanjut ke checklist kemasan"]

    A --> B
    B -->|"terima"| C
    C --> G
    B -->|"tolak"| D
    D --> E
    E --> F
    F --> A
```

Kurir hanya bisa menolak selama `courier_accepted_at` masih kosong. Setelah menerima tugas, tombol tolak tertutup dan satu-satunya jalan keluar dari penugasan adalah jalur kendala.

### 5.4 E4 dan E5 - Kendala, lalu keputusan admin

E4 adalah kendala yang ketahuan saat pemeriksaan kemasan, E5 adalah kendala yang ketahuan di jalan. Keduanya bermuara ke pintu masuk yang sama.

```mermaid
flowchart TD
    A1["Kurir menemukan masalah saat memeriksa kemasan<br/>status COURIER_ASSIGNED atau CHECKED"]
    A2["Kurir menemukan masalah saat mengantar<br/>status IN_DELIVERY"]
    R["POST /api/courier/deliveries/RESI/issue<br/>ConditionCheck is_normal = false dan IssueReport dibuat"]
    S["Status menuju ISSUE_REPORTED<br/>lockForUpdate, satu kendala terbuka per paket"]
    N1["Notifikasi issue_reported<br/>ke semua admin aktif"]
    N2["Notifikasi issue_options<br/>ke pembeli lewat WhatsApp"]
    PC["Pembeli memilih opsi 1 sampai 4<br/>tersimpan di issue_reports.buyer_choice"]
    M["Admin membuka panel kendala<br/>GET /api/admin/exceptions/RESI"]
    K{"Kategori kendala"}
    O1["Opsi RETURN, RESEND, PROCEED, RECHECK"]
    O2["Opsi RESCHEDULE, RETURN"]
    DEC["POST /api/admin/exceptions/RESI/decision<br/>ResolveIssue di dalam satu transaksi"]
    C1["RETURN menuju RETURNED<br/>paket ditarik ke hub untuk klaim retur"]
    C2["RESEND menuju RETURNED pada paket asal<br/>lalu resi pengganti dibuat"]
    C3["PROCEED menuju IN_DELIVERY<br/>pengantaran diteruskan"]
    C4["RECHECK menuju CHECKED<br/>pemeriksaan ulang fisik"]
    C5["RESCHEDULE menuju AWAITING_CONFIRMATION<br/>kurir dilepas, pembeli memilih jadwal"]

    A1 --> R
    A2 --> R
    R --> S
    S --> N1
    S --> N2
    S --> PC
    N1 --> M
    PC -.->|"masukan pembeli, keputusan tetap milik admin"| M
    M --> K
    K -->|"kemasan_bocor, kemasan_basah, suhu_naik"| O1
    K -->|"pembeli_tidak_ada, alamat_tidak_ditemukan, cod_ditolak"| O2
    O1 --> DEC
    O2 --> DEC
    DEC --> C1
    DEC --> C2
    DEC --> C3
    DEC --> C4
    DEC --> C5
```

Keputusan yang diizinkan dibatasi kategori kendala. Ini penting karena paket yang tidak bisa diperiksa ulang, misalnya pembeli tidak di tempat, tidak boleh menawarkan opsi `RECHECK`.

| Kategori kendala | Keputusan yang diizinkan |
|---|---|
| `kemasan_bocor`, `kemasan_basah`, `suhu_naik` | `RETURN`, `RESEND`, `PROCEED`, `RECHECK` |
| `pembeli_tidak_ada`, `alamat_tidak_ditemukan`, `cod_ditolak` | `RESCHEDULE`, `RETURN` |

Efek tiap keputusan, semuanya dijalankan di dalam transaksi yang sama dengan penguncian baris paket dan laporan kendala:

| Keputusan | Status paket | Efek | Notifikasi |
|---|---|---|---|
| `RETURN` | `RETURNED` | Paket ditarik ke pusat distribusi untuk klaim retur | `issue_decision_update` ke pembeli |
| `RESEND` | `RETURNED` pada paket asal, resi pengganti di `AWAITING_CONFIRMATION` | Resi baru dibuat dan ditautkan lewat `resend_of_package_id` | `issue_decision_update` ke pembeli, `schedule_request` dan `resend_requested` untuk resi baru |
| `PROCEED` | `IN_DELIVERY` | Pengantaran diteruskan, kompensasi parsial bila disetujui pembeli | `issue_decision_update` ke pembeli |
| `RECHECK` | `CHECKED` | Pemeriksaan ulang kondisi fisik di lapangan | `issue_decision_update` ke pembeli |
| `RESCHEDULE` | `AWAITING_CONFIRMATION` | Kurir dilepas, pembeli memilih jadwal baru | `schedule_request` ke pembeli |

Pilihan pembeli lewat WhatsApp, opsi 1 sampai 4, hanya tersimpan sebagai `buyer_choice` dan diteruskan ke admin sebagai masukan. Keputusan resmi tetap ada di tangan admin, sehingga tidak ada jalur di mana pembeli mengubah status paket sendirian.

### 5.5 E6 - Resi pengganti

```mermaid
flowchart TD
    A["Pengiriman ulang dipicu"]
    B{"Dari mana pemicunya?"}
    C["Admin memutuskan RESEND<br/>paket asal menuju RETURNED lebih dulu"]
    D["Pembeli memilih opsi 2 di WhatsApp<br/>paket asal tetap ISSUE_REPORTED"]
    E["ReplacementPackageFactory membuat resi pengganti<br/>resend_of_package_id menunjuk paket asal<br/>jadwal digeser ke hari berikutnya"]
    F["Resi pengganti menuju AWAITING_CONFIRMATION<br/>dengan schedule_confirmations PENDING"]
    G["Notifikasi schedule_request ke pembeli"]
    H["Notifikasi resend_requested ke semua admin"]
    I["Alur kembali ke Bagian 3, fase konfirmasi jadwal"]

    A --> B
    B -->|"admin"| C
    B -->|"pembeli, bila memenuhi syarat dan resend_auto_approve aktif"| D
    C --> E
    D --> E
    E --> F
    F --> G
    F --> H
    G --> I
```

Ada dua pemicu pengiriman ulang dan keduanya berujung pada resi baru yang kembali ke fase konfirmasi jadwal. Bedanya, jalur admin menutup paket asal ke `RETURNED` lebih dulu, sedangkan jalur pembeli lewat `POST .../issue/resend` atau opsi 2 di WhatsApp meninggalkan paket asal di `ISSUE_REPORTED` karena keputusan kendalanya belum diambil admin. Kelayakan pengiriman ulang disaring oleh `App\Services\Client\ReplacementEligibility`, dan pembuatan resi baru dilindungi indeks unik parsial `uq_packages_one_replacement` supaya satu paket asal tidak pernah punya dua resi pengganti.

### 5.6 E7 - Retur dan penutupan

```mermaid
flowchart LR
    A["Paket asal status RETURNED"] --> B["Kurir menarik paket ke hub"] --> C["Klaim retur diproses di luar sistem"] --> D["Status menuju CLOSED dan closed_at terisi"]
```

Transisi `RETURNED` menuju `CLOSED` dan `RECEIVED` menuju `CLOSED` sudah diizinkan database, tetapi belum ada endpoint, action, atau job yang menjalankannya. Selama itu belum ada, rekap harian tetap membaca paket selesai dari tabel `deliveries` dan laporan kendala dari `issue_reports`, bukan dari status `CLOSED`. Lihat Bagian 8.

## 6. Rantai Notifikasi dan Background Job

```mermaid
sequenceDiagram
    autonumber
    participant K as Kurir
    participant API as API Laravel
    participant DB as PostgreSQL
    participant N as Tabel notifications
    participant J as Job DispatchNotificationToN8n
    participant W as Workflow n8n
    participant P as Pembeli

    K->>API: POST deliveries/RESI/depart, checklist dan suhu
    API->>DB: INSERT condition_checks dan delivery_milestones DEPARTED
    API->>DB: UPDATE packages.status lewat PackageStatusTransitioner
    API->>N: INSERT notifications template on_the_way, status QUEUED
    API-->>K: 200 OK
    Note over API,J: Job di-dispatch afterCommit agar tidak mendahului commit transaksi
    J->>N: baca notifikasi QUEUED dan rakit payload
    J->>W: kirim payload ke workflow n8n
    W->>P: pesan WhatsApp berisi tautan resi
    J->>N: UPDATE notifications.status menjadi SENT
```

Semua pesan ke pembeli, kurir, dan admin melewati satu antrean yang sama: baris di tabel `notifications`, lalu job `DispatchNotificationToN8n`, lalu workflow n8n yang mengirim WhatsApp. Karena payload notifikasi sudah memuat nomor penerima, nama, resi, dan tautan klien, workflow n8n tidak perlu membuka database aplikasi. Template yang belum dipetakan ke pesan WhatsApp dibiarkan berstatus `QUEUED`, bukan `FAILED`, supaya pemetaan bisa ditambah tanpa alarm palsu.

## 7. Matriks Ringkas Cabang Edge Case

| ID | Pemicu | Status sebelum menuju sesudah | Penanganan | Endpoint atau job |
|---|---|---|---|---|
| E1 | Pembeli memilih jendela lain, lalu admin/kurir mengajukan jendela baru | `AWAITING_CONFIRMATION` menuju `CONFIRMED` (pembeli), atau `COURIER_ASSIGNED` menuju `AWAITING_CONFIRMATION` (admin/kurir) | Jendela pembeli langsung diterapkan; jendela yang diajukan admin/kurir menunggu konfirmasi penerima | `POST /api/client/orders/{resi}/schedule/reschedule`, `POST /api/admin/shipments/{resi}/request-confirmation` |
| E2 | Pembeli tidak merespons melewati ambang waktu | tetap `AWAITING_CONFIRMATION` | Reminder otomatis ke admin | `confirmations:remind-unanswered` setiap 15 menit |
| E3 | Kurir menolak penugasan | `COURIER_ASSIGNED` menuju `CONFIRMED` | Penugasan dibatalkan, admin memilih kurir lain | `POST /api/courier/tasks/{resi}/reject` |
| E4 | Checklist kemasan menemukan masalah sebelum berangkat | `COURIER_ASSIGNED` atau `CHECKED` menuju `ISSUE_REPORTED` | Admin memutuskan lewat panel kendala | `POST /api/courier/deliveries/{resi}/issue`, `POST /api/admin/exceptions/{resi}/decision` |
| E5 | Kendala terjadi saat pengantaran | `IN_DELIVERY` menuju `ISSUE_REPORTED` | Sama seperti E4 | Sama seperti E4 |
| E6 | Pengiriman ulang disetujui | Paket asal `RETURNED`, resi pengganti `AWAITING_CONFIRMATION` | Resi baru dibuat otomatis, kembali ke fase konfirmasi | `POST /api/admin/exceptions/{resi}/decision`, `POST /api/client/orders/{resi}/issue/resend` |
| E7 | Paket retur ditarik ke hub | `RETURNED` menuju `CLOSED` | Belum terhubung ke endpoint mana pun | belum ada |

## 8. Celah yang Belum Terhubung

Bagian ini penting supaya dokumen tidak menjanjikan lebih dari yang sudah jalan.

- **Penutupan paket belum punya aktor.** Transisi ke `CLOSED` ada di matriks FSM dan kolom `closed_at` sudah tersedia, tetapi tidak ada endpoint, action, atau job yang menjalankannya. Selama ini belum ditutup, paket berhenti di `RECEIVED` atau `RETURNED` dan rekap harian tetap menghitungnya sebagai selesai.
- **Pengiriman ulang dari sisi pembeli tidak mengubah status paket asal.** Pada jalur ini paket asal tetap `ISSUE_REPORTED` sampai admin mengambil keputusan, sehingga paket bisa muncul di daftar kendala sekaligus sudah punya resi pengganti.
- **Kurir mengobrol langsung dengan pembeli** di luar alur notifikasi belum digambar karena belum ada di backend. Kalau nanti diimplementasikan, tempatnya adalah cabang E4 dan E5.

## 9. Catatan Pemeliharaan

Kalau ada perubahan alur, urutan yang benar adalah mengubah matriks di `database/seeders/ReferenceDataSeeder.php` lebih dulu, menambah migrasi bila perlu, baru memperbarui dokumen ini. Diagram di sini adalah cermin dari FSM di database, dan cara termurah untuk memeriksanya adalah membandingkan daftar transisi di Bagian 2 dengan isi tabel `package_status_transitions`.

Berkas ini sengaja diletakkan di `docs/` bersama `client-api.md` dan `client-data-contract.md` supaya tidak menyerempet area frontend, dan sengaja memakai Mermaid supaya bisa dirender oleh manusia sekaligus dibaca sebagai teks oleh agent yang bekerja di repositori ini.

Versi D2 dari diagram gabungan dan peta FSM tersedia di `docs/diagrams/`, lengkap dengan skrip render dan pembukanya. Dari akar repositori:

```bash
bash docs/diagrams/render.sh                 # hasilkan SVG, PNG, dan teks ASCII
bash docs/diagrams/lihat.sh                  # buka diagram gabungan dari terminal
bash docs/diagrams/lihat.sh -t fsm-status    # baca versi teks di terminal
```
