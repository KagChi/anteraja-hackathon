export const TRACKING = {
    tracking: "AJ-FRZ-0012345",
    status: "Sedang Diantar",
    etaDate: "Kamis, 15 Okt 2024",
    etaTime: "13.45 WIB",
    recipient: "Rina Putri",
    recipientArea: "Kebayoran Baru",
    courier: "Budi Santoso",
    courierBox: "Box #04",
    courierTel: "tel:081234567890",
    temperature: "-18°C",
    temperatureState: "Optimal",
    coldChain: "Terjaga",
    timeline: [
        {
            label: "Sedang Diantar",
            time: "13.15",
            detail: "Kurir Budi Santoso sedang menuju alamat penerima membawa cooler box.",
            active: true
        },
        {
            label: "Paket Keluar Hub",
            time: "12.30",
            detail: "Paket dipindahkan ke cooler box kurir di Hub Jakarta Selatan."
        },
        {
            label: "Tiba di Hub Cold Storage",
            time: "10.00",
            detail: "Paket tiba dan disimpan di freezer hub Jakarta Selatan."
        },
        {
            label: "Paket Dijemput",
            time: "08.30",
            detail: "Paket Nugget Ayam 1 kg diterima dari penjual dalam kondisi beku sempurna."
        }
    ]
};

export const SCHEDULE = {
    tracking: "AJ-FRZ-0012345",
    status: "Menunggu Konfirmasi",
    recipient: "Rina Putri",
    address: "Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410",
    coldBadge: "-18°C Terjamin",
    productCategory: "Makanan Beku (Frozen Food)",
    productName: "Nugget Ayam 1 kg",
    productMeta: "1 Paket • Berat 1.000g",
    productNote:
        "Membutuhkan kotak pendingin & ice gel khusus. Wajib diterima langsung agar suhu beku terjaga optimal.",
    priority: "Prioritas Dingin",
    window: "13.00 - 15.00 WIB",
    windowDate: "Kamis, 15 Oktober 2024",
    courier: "Budi Santoso",
    courierRole: "Kurir Khusus Frozen Anteraja",
    noticeTitle: "Penting Sebelum Konfirmasi",
    notice: "Kurir akan membawa cooler box. Mohon pastikan ada penerima di lokasi pada jam tersebut agar kualitas makanan beku tetap aman."
};

export const ISSUE = {
    tracking: "AJ-FRZ-0012345",
    recipient: "Rina Putri",
    courier: "Budi Santoso",
    product: "Nugget Ayam 1 kg",
    statusBadge: "Pengiriman Terkendala",
    tempBadge: "Suhu: -18°C Terjaga",
    problemLabel: "Laporan Masalah Fisik",
    problem: "Kemasan bocor saat proses transit di hub pendingin.",
    decisionLabel: "Keputusan Admin Operasional",
    decision:
        "Paket Dibatalkan & Dikembalikan ke Penjual (Retur Otomatis). Penjual akan mengirimkan produk pengganti baru yang segar.",
    qualityLabel: "Pemeriksaan Kualitas Cold Chain",
    quality:
        "Tim kontrol kualitas menemukan kerusakan segel kemasan beku. Demi menjaga keamanan pangan dan standar higienis, pengantaran dibatalkan secara otomatis tanpa biaya bagi pembeli.",
    history: [
        {
            label: "Keputusan Admin",
            time: "12.45 WIB",
            detail: "Paket diputuskan untuk dikembalikan (retur) ke penjual karena kemasan bocor.",
            active: true
        },
        {
            label: "Pemeriksaan Hub",
            time: "12.10 WIB",
            detail: "Petugas hub menemukan kebocoran pada kemasan Nugget Ayam 1 kg saat pengecekan suhu."
        },
        {
            label: "Tiba di Hub",
            time: "10.00 WIB",
            detail: "Paket diterima di Hub Cold Storage Jakarta Selatan."
        }
    ]
};
