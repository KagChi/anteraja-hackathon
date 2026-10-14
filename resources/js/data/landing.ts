/**
 * Copy for the marketing landing page.
 *
 * It lives here rather than in the page so the sections stay readable and the
 * same lists can be reused (the footer repeats the console entries, the blog
 * index repeats the service names).
 */

export interface LandingStat {
    /** Numeric part the count-up animates to. */
    value: number;
    suffix: string;
    label: string;
    detail: string;
}

export interface LandingService {
    icon: string;
    title: string;
    sla: string;
    description: string;
    points: string[];
    featured?: boolean;
}

export interface LandingAdvantage {
    icon: string;
    title: string;
    description: string;
}

export interface CoverageArea {
    region: string;
    areas: string[];
    eta: string;
}

export const HERO_STATS: LandingStat[] = [
    { value: 100, suffix: "%", label: "Rantai dingin terjaga", detail: "Suhu dicatat di setiap titik" },
    { value: 18, suffix: "°C", label: "Batas aman", detail: "Freezer box + ice gel" },
    { value: 4, suffix: " jam", label: "Layanan instan", detail: "Untuk area Jabodetabek" },
    { value: 99, suffix: "%", label: "Tepat waktu", detail: "Jendela antar yang disepakati" }
];

export const SERVICES: LandingService[] = [
    {
        icon: "ac_unit",
        title: "Anteraja Frozen Instant",
        sla: "SLA 2 - 4 jam",
        description: "Kiriman beku yang tidak boleh mencair: dikirim satu kurir, satu paket, tanpa transit ulang.",
        points: ["Kurir khusus frozen", "Verifikasi lokasi serah terima", "Prioritas jendela antar"],
        featured: true
    },
    {
        icon: "local_shipping",
        title: "Anteraja Frozen Reguler",
        sla: "Same day Jabodetabek",
        description:
            "Pengiriman terjadwal untuk stok mingguan rumah tangga, toko frozen food, dan reseller di Jabodetabek.",
        points: ["Jendela antar yang bisa dipilih", "Kontrol suhu sebelum berangkat", "Bukti foto serah terima"]
    },
    {
        icon: "apartment",
        title: "Pengiriman Corporate",
        sla: "Kontrak bulanan",
        description: "Volume tinggi untuk restoran, hotel, dan distributor dengan alur armada yang bisa diaudit.",
        points: ["Laporan harian dan ekspor CSV", "Audit riwayat status", "Penanganan kendala terpusat"]
    },
    {
        icon: "storefront",
        title: "Pengiriman UMKM",
        sla: "Bayar per kiriman",
        description: "Untuk penjual frozen food online: pesanan masuk sendiri lewat aplikasi pelanggan.",
        points: ["Katalog produk di aplikasi", "Checkout dan jadwal mandiri", "Lacak status real time"]
    }
];

export const ADVANTAGES: LandingAdvantage[] = [
    {
        icon: "device_thermostat",
        title: "Suhu dicatat, bukan diasumsikan",
        description: "Setiap keberangkatan mencatat suhu paket dan hasil pemeriksaan fisik sebelum kurir bergerak."
    },
    {
        icon: "my_location",
        title: "Serah terima yang tervalidasi",
        description: "Server memeriksa akurasi sinyal GPS dan radius titik penerima sebelum bukti pengantaran diterima."
    },
    {
        icon: "photo_camera",
        title: "Bukti yang bisa diaudit",
        description:
            "Foto serah terima, nama penerima, dan hubungannya tersimpan bersama waktu server yang tidak bisa diubah."
    },
    {
        icon: "support_agent",
        title: "Kendala ditangani manusia",
        description:
            "Paket bermasalah masuk antrean kendala dengan keputusan admin, alasan tertulis, dan riwayat lengkap."
    }
];

/**
 * Service area. Jabodetabek only: the hub network, the instant tier and the
 * cold-chain fleet all live in Greater Jakarta, so the page must not imply
 * nationwide coverage.
 */
export const COVERAGE: CoverageArea[] = [
    { region: "Jakarta Selatan", areas: ["Tebet", "Kebayoran", "Cilandak", "Pasar Minggu"], eta: "Instant 2 - 4 jam" },
    {
        region: "Jakarta Pusat & Selatan",
        areas: ["Setiabudi", "Kuningan", "Mampang", "Senayan"],
        eta: "Instant 2 - 4 jam"
    },
    {
        region: "Jakarta Timur & Barat",
        areas: ["Cakung", "Pulogadung", "Kebon Jeruk", "Grogol"],
        eta: "Instant 2 - 4 jam"
    },
    { region: "Jakarta Utara", areas: ["Kelapa Gading", "Penjaringan", "Tanjung Priok"], eta: "Same day" },
    { region: "Depok", areas: ["Margonda", "Cinere", "Beji"], eta: "Same day" },
    { region: "Tangerang & Tangerang Selatan", areas: ["BSD", "Bintaro", "Karawaci"], eta: "Same day" },
    { region: "Bekasi", areas: ["Bekasi Barat", "Bekasi Timur", "Cikarang"], eta: "Same day" },
    { region: "Bogor", areas: ["Bogor Barat", "Cibinong", "Sentul"], eta: "Same day" }
];

/** The three interfaces a visitor can walk into from the landing page. */
export const CONSOLE_LINKS = [
    {
        title: "Admin Console",
        description: "Pantau pengiriman, tangani kendala, tinjau laporan dan riwayat.",
        icon: "monitoring",
        href: "/admin/shipment"
    },
    {
        title: "Aplikasi Kurir",
        description: "Terima tugas, validasi lokasi, unggah bukti penyerahan.",
        icon: "local_shipping",
        href: "/courier/tasks"
    },
    {
        title: "Aplikasi Pelanggan",
        description: "Pesan produk beku, pilih jadwal, lacak status kiriman.",
        icon: "receipt_long",
        href: "/client/orders"
    }
];

export const NAV_LINKS = [
    { label: "Produk", href: "#produk" },
    { label: "Layanan", href: "#layanan" },
    { label: "Keunggulan", href: "#keunggulan" },
    { label: "Jangkauan", href: "#jangkauan" },
    { label: "Blog", href: "#blog" }
];

/** Estimated per-kilogram rate used by the shipping-fee panel, Jabodetabek only. */
export const FEE_ZONES = [
    { value: "jakarta-selatan", label: "Jakarta Selatan", perKg: 11000, minDays: 1, maxDays: 1 },
    { value: "jakarta-lainnya", label: "Jakarta lainnya", perKg: 13000, minDays: 1, maxDays: 1 },
    { value: "depok-bekasi", label: "Depok & Bekasi", perKg: 15000, minDays: 1, maxDays: 1 },
    { value: "tangerang", label: "Tangerang & Tangsel", perKg: 16000, minDays: 1, maxDays: 1 },
    { value: "bogor", label: "Bogor", perKg: 17000, minDays: 1, maxDays: 1 }
];

export const FEE_SERVICES = [
    { value: "REGULER", label: "Reguler", multiplier: 1 },
    { value: "INSTANT_FROZEN", label: "Instant Frozen", multiplier: 1.6 }
];
