export interface ClientTimelineNode {
    label: string;
    time: string;
    detail: string;
    active?: boolean;
}

export interface ClientSchedule {
    status: string;
    coldBadge: string;
    productCategory: string;
    productName: string;
    productMeta: string;
    productNote: string;
    priority: string;
    window: string;
    windowDate: string;
    courier: string;
    courierRole: string;
    noticeTitle: string;
    notice: string;
}

export interface ClientIssue {
    statusBadge: string;
    tempBadge: string;
    problemLabel: string;
    problem: string;
    decisionLabel: string;
    decision: string;
    qualityLabel: string;
    quality: string;
    history: ClientTimelineNode[];
}

export interface ClientOrder {
    resi: string;
    product: string;
    productMeta: string;
    recipient: string;
    recipientArea: string;
    address: string;
    status: string;
    statusKey: "transit" | "waiting" | "issue" | "done";
    etaDate: string;
    etaTime: string;
    courier: string;
    courierBox: string;
    courierTel: string;
    temperature: string;
    temperatureState: string;
    coldChain: string;
    timeline: ClientTimelineNode[];
    schedule?: ClientSchedule;
    issue?: ClientIssue;
}

const NUGGET_TIMELINE: ClientTimelineNode[] = [
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
];

const WINDOW_NOTICE = {
    noticeTitle: "Penting Sebelum Konfirmasi",
    notice: "Kurir akan membawa cooler box. Mohon pastikan ada penerima di lokasi pada jam tersebut agar kualitas makanan beku tetap aman.",
    courierRole: "Kurir Khusus Frozen Anteraja",
    priority: "Prioritas Dingin"
};

export const CLIENT_ORDERS: ClientOrder[] = [
    {
        resi: "AJ-FRZ-0012345",
        product: "Nugget Ayam 1 kg",
        productMeta: "1 Paket • Berat 1.000g",
        recipient: "Rina Putri",
        recipientArea: "Kebayoran Baru",
        address: "Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410",
        status: "Sedang Diantar",
        statusKey: "transit",
        etaDate: "Kamis, 15 Okt 2024",
        etaTime: "13.45 WIB",
        courier: "Budi Santoso",
        courierBox: "Box #04",
        courierTel: "tel:081234567890",
        temperature: "-18°C",
        temperatureState: "Optimal",
        coldChain: "Terjaga",
        timeline: NUGGET_TIMELINE
    },
    {
        resi: "AJ-FRZ-0012347",
        product: "Es Krim Vanilla & Choco Box",
        productMeta: "2 Paket • Berat 1.400g",
        recipient: "Rina Putri",
        recipientArea: "Kebayoran Baru",
        address: "Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410",
        status: "Menunggu Konfirmasi",
        statusKey: "waiting",
        etaDate: "Kamis, 15 Okt 2024",
        etaTime: "13.00 - 15.00 WIB",
        courier: "Budi Santoso",
        courierBox: "Box #04",
        courierTel: "tel:081234567890",
        temperature: "-18°C",
        temperatureState: "Optimal",
        coldChain: "Terjaga",
        timeline: [
            {
                label: "Menunggu Konfirmasi Jadwal",
                time: "09.20",
                detail: "Paket siap di hub. Konfirmasi jadwal pengantaran untuk melanjutkan.",
                active: true
            },
            {
                label: "Paket Dikemas",
                time: "08.10",
                detail: "Es krim dikemas dengan ice gel tambahan di Hub Jakarta Selatan."
            }
        ],
        schedule: {
            status: "Menunggu Konfirmasi",
            coldBadge: "-18°C Terjamin",
            productCategory: "Makanan Beku (Frozen Food)",
            productName: "Es Krim Vanilla & Choco Box",
            productMeta: "2 Paket • Berat 1.400g",
            productNote:
                "Membutuhkan kotak pendingin & ice gel khusus. Wajib diterima langsung agar suhu beku terjaga optimal.",
            window: "13.00 - 15.00 WIB",
            windowDate: "Kamis, 15 Oktober 2024",
            courier: "Budi Santoso",
            ...WINDOW_NOTICE
        }
    },
    {
        resi: "AJ-FRZ-0012349",
        product: "Dimsum Ayam 500g",
        productMeta: "1 Paket • Berat 500g",
        recipient: "Rina Putri",
        recipientArea: "Kebayoran Baru",
        address: "Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410",
        status: "Kendala Pengiriman",
        statusKey: "issue",
        etaDate: "Kamis, 15 Okt 2024",
        etaTime: "Menunggu keputusan admin",
        courier: "Budi Santoso",
        courierBox: "Box #04",
        courierTel: "tel:081234567890",
        temperature: "-18°C",
        temperatureState: "Optimal",
        coldChain: "Terjaga",
        timeline: [
            {
                label: "Keputusan Admin",
                time: "12.45",
                detail: "Paket diputuskan untuk dikembalikan (retur) ke penjual karena kemasan bocor.",
                active: true
            },
            {
                label: "Pemeriksaan Hub",
                time: "12.10",
                detail: "Petugas hub menemukan kebocoran pada kemasan Dimsum Ayam 500g saat pengecekan suhu."
            },
            {
                label: "Tiba di Hub",
                time: "10.00",
                detail: "Paket diterima di Hub Cold Storage Jakarta Selatan."
            }
        ],
        issue: {
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
                    detail: "Petugas hub menemukan kebocoran pada kemasan Dimsum Ayam 500g saat pengecekan suhu."
                },
                {
                    label: "Tiba di Hub",
                    time: "10.00 WIB",
                    detail: "Paket diterima di Hub Cold Storage Jakarta Selatan."
                }
            ]
        },
        schedule: {
            status: "Menunggu Konfirmasi",
            coldBadge: "-18°C Terjamin",
            productCategory: "Makanan Beku (Frozen Food)",
            productName: "Dimsum Ayam 500g (Pengganti)",
            productMeta: "1 Paket • Berat 500g",
            productNote:
                "Produk pengganti dikirim ulang dengan kotak pendingin. Wajib diterima langsung agar suhu beku terjaga optimal.",
            window: "16.00 - 18.00 WIB",
            windowDate: "Jumat, 16 Oktober 2024",
            courier: "Budi Santoso",
            ...WINDOW_NOTICE
        }
    },
    {
        resi: "AJ-FRZ-0012320",
        product: "Kentang Goreng 2 kg",
        productMeta: "1 Paket • Berat 2.000g",
        recipient: "Rina Putri",
        recipientArea: "Kebayoran Baru",
        address: "Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410",
        status: "Diterima",
        statusKey: "done",
        etaDate: "Diterima hari ini",
        etaTime: "09.52 WIB",
        courier: "Budi Santoso",
        courierBox: "Box #04",
        courierTel: "tel:081234567890",
        temperature: "-18°C",
        temperatureState: "Optimal",
        coldChain: "Terjaga",
        timeline: [
            {
                label: "Diterima Penerima",
                time: "09.52",
                detail: "Paket diterima langsung oleh Rina Putri dalam kondisi beku.",
                active: true
            },
            {
                label: "Paket Keluar Hub",
                time: "08.40",
                detail: "Paket dipindahkan ke cooler box kurir di Hub Jakarta Selatan."
            }
        ]
    },
    {
        resi: "AJ-FRZ-0012288",
        product: "Frozen Beef Slice 1 kg",
        productMeta: "1 Paket • Berat 1.000g",
        recipient: "Rina Putri",
        recipientArea: "Kebayoran Baru",
        address: "Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410",
        status: "Diterima",
        statusKey: "done",
        etaDate: "Diterima kemarin",
        etaTime: "16.20 WIB",
        courier: "Dani Kurniawan",
        courierBox: "Box #02",
        courierTel: "tel:081234567891",
        temperature: "-19°C",
        temperatureState: "Optimal",
        coldChain: "Terjaga",
        timeline: [
            {
                label: "Diterima Penerima",
                time: "16.20",
                detail: "Paket diterima langsung oleh Rina Putri dalam kondisi beku.",
                active: true
            },
            {
                label: "Paket Keluar Hub",
                time: "15.05",
                detail: "Paket dipindahkan ke cooler box kurir di Hub Jakarta Selatan."
            }
        ]
    }
];

/** Look up an order by resi; undefined when the resi is unknown. */
export function findOrder(resi: string | null | undefined): ClientOrder | undefined {
    if (!resi) return undefined;
    return CLIENT_ORDERS.find((order) => order.resi === resi);
}

export const CLIENT_ACTIVE_ORDERS = CLIENT_ORDERS.filter((order) => order.statusKey !== "done");
export const CLIENT_PAST_ORDERS = CLIENT_ORDERS.filter((order) => order.statusKey === "done");

export const CLIENT_PROFILE = {
    name: "Rina Putri",
    phone: "0812-9876-5432",
    email: "rina.putri@email.com",
    address: "Jl. Melati No. 14, Jakarta Selatan, DKI Jakarta 12410",
    stats: [
        { label: "Pesanan aktif", value: "3" },
        { label: "Selesai", value: "12" },
        { label: "Tepat waktu", value: "96%" }
    ]
};

/** Customer-service channel used by the help, complaint and reschedule actions. */
export const SUPPORT = {
    label: "Layanan Pelanggan Anteraja Frozen",
    phone: "0812-8899-1234",
    whatsapp: "6281288991234"
};

/** Build a WhatsApp deep link with a pre-filled message. */
export function whatsappLink(text: string): string {
    return `https://wa.me/${SUPPORT.whatsapp}?text=${encodeURIComponent(text)}`;
}

/** Chip styling per order state, shared by the Pesanan and Riwayat lists. */
export const CLIENT_STATUS_TONE: Record<ClientOrder["statusKey"], string> = {
    transit: "bg-primary-fixed text-primary",
    waiting: "bg-secondary-container text-on-secondary-container",
    issue: "bg-error-container text-on-error-container",
    done: "bg-tertiary-fixed text-tertiary"
};
