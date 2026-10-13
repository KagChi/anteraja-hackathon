export const COURIER = {
    name: "Budi Santoso",
    status: "Aktif"
};

export interface TaskMetric {
    label: string;
    value: string;
}

export const TASK_DETAIL = {
    id: "KRR-8821",
    age: "Masuk 2 mnt lalu",
    tracking: "AJ-FRZ-0012345",
    service: "Instant Frozen",
    window: "13:00 - 15:00 WIB (Hari ini)",
    windowStatus: "Tepat Waktu",
    recipient: "Rina Putri",
    placeType: "Rumah / Residensial",
    address: "Jl. Melati Indah No. 42, RT 03 / RW 07, Tebet Barat, Jakarta Selatan, DKI Jakarta 12810",
    instruction: "Produk beku, serahkan langsung ke penerima. Jangan ditinggal di pagar atau pos satpam.",
    metrics: [
        { label: "Jumlah", value: "1 Koli" },
        { label: "Kategori", value: "Frozen Food" },
        { label: "Berat", value: "1.8 kg" }
    ] satisfies TaskMetric[],
    distance: "2.4 km dari lokasi Anda",
    eta: "~12 Menit"
};

export const ACTIVE_DELIVERY = {
    tracking: "AJ-FRZ-0012345",
    service: "Paket Beku",
    recipient: "Rina Putri",
    phone: "0812-9876-5432",
    phoneHref: "tel:081298765432",
    address: "Jl. Melati Indah No. 42, RT 03 / RW 07, Tebet Barat, Jakarta Selatan",
    instruction: "Produk beku, serahkan langsung ke penerima",
    mapLabel: "Jl. Melati Indah No. 42, Tebet Barat, Jakarta Selatan",
    mapHref: `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent("Jl. Melati Indah No. 42, RT 03 / RW 07, Tebet Barat, Jakarta Selatan")}`
};

export const ISSUE_SHEET_OPTIONS = ["Penerima Tidak Ada di Tempat", "Alamat Tidak Ditemukan", "Paket Rusak / Mencair"];

export const PROOF_FORM = {
    tracking: "AJ-FRZ-0012345",
    service: "Reguler",
    recipient: "Rina Putri",
    photoName: "bukti_serah_terima.jpg",
    photoMeta: "1 foto terlampir • 1.4 MB",
    notesPlaceholder: "Contoh: Diterima langsung, diletakkan di teras",
    relations: ["Penerima Sendiri", "Keluarga Serumah", "Rekan Kerja", "Satpam / Sekuriti"]
};

export const ISSUE_FORM = {
    tracking: "AJ-FRZ-0012345",
    recipient: "Rina Putri",
    reporter: "Budi Santoso",
    photoName: "foto_kemasan_bocor.jpg",
    photoMeta: "1.8 MB • Baru saja",
    noteLimit: 200,
    note: "Kemasan es beku mencair dan kemasan luar basah/bocor saat tiba di depan gerbang penerima.",
    options: [
        { value: "kemasan_bocor", label: "Kemasan bocor" },
        { value: "pembeli_tidak_ada", label: "Pembeli tidak ada" },
        { value: "alamat_tidak_ditemukan", label: "Alamat tidak ditemukan" },
        { value: "pembayaran_cod_ditolak", label: "Penerima menolak bayar COD" },
        { value: "akses_ditutup", label: "Akses jalan/lokasi ditutup" }
    ]
};

export interface CourierHistoryRow {
    resi: string;
    recipient: string;
    completedAt: string;
    status: string;
}

export const COURIER_HISTORY: CourierHistoryRow[] = [
    { resi: "AJ-FRZ-0012344", recipient: "Andi Saputra", completedAt: "Hari ini • 13:42 WIB", status: "Terkirim" },
    { resi: "AJ-FRZ-0012343", recipient: "Nadia Suryani", completedAt: "Hari ini • 12:18 WIB", status: "Terkirim" },
    { resi: "AJ-FRZ-0012341", recipient: "Bagus Prasetyo", completedAt: "Hari ini • 11:05 WIB", status: "Terkirim" },
    { resi: "AJ-FRZ-0012339", recipient: "Tania Wijaya", completedAt: "Hari ini • 10:47 WIB", status: "Terkirim" },
    { resi: "AJ-FRZ-0012336", recipient: "Rendra Kusuma", completedAt: "Hari ini • 09:52 WIB", status: "Dikembalikan" }
];

export const COURIER_PROFILE = {
    name: "Budi Santoso",
    status: "Aktif",
    id: "KRR-8821",
    phone: "0812-3456-7890",
    hub: "Hub Jakarta Selatan",
    vehicle: "Motor Box #04",
    stats: [
        { label: "Selesai hari ini", value: "8" },
        { label: "Tepat waktu", value: "97%" },
        { label: "Rating", value: "4.8" }
    ]
};
