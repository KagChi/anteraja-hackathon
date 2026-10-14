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
        sla: "SLA 1 - 2 hari",
        description: "Pengiriman terjadwal untuk stok mingguan rumah tangga, toko frozen food, dan reseller.",
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

export const COVERAGE: CoverageArea[] = [
    { region: "DKI Jakarta", areas: ["Jak-Sel", "Jak-Tim", "Jak-Pus", "Jak-Bar", "Jak-Ut"], eta: "Instant 2 - 4 jam" },
    { region: "Jabodetabek", areas: ["Depok", "Tangerang", "Bekasi", "Bogor"], eta: "Same day" },
    { region: "Jawa Barat", areas: ["Bandung", "Cimahi", "Bekasi Kota"], eta: "1 hari" },
    { region: "Jawa Tengah & DIY", areas: ["Semarang", "Solo", "Yogyakarta"], eta: "1 - 2 hari" },
    { region: "Jawa Timur", areas: ["Surabaya", "Malang", "Sidoarjo"], eta: "1 - 2 hari" },
    { region: "Bali & Nusa Tenggara", areas: ["Denpasar", "Mataram"], eta: "2 hari" },
    { region: "Sumatera", areas: ["Medan", "Palembang", "Padang"], eta: "2 - 3 hari" },
    { region: "Kalimantan & Sulawesi", areas: ["Balikpapan", "Makassar", "Manado"], eta: "2 - 3 hari" }
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

/** Estimated per-kilogram rate used by the showcase shipping-fee form. */
export const FEE_ZONES = [
    { value: "jabodetabek", label: "Jabodetabek", perKg: 12000, minDays: 1, maxDays: 1 },
    { value: "jawa", label: "Pulau Jawa", perKg: 18000, minDays: 1, maxDays: 2 },
    { value: "bali-nusra", label: "Bali & Nusa Tenggara", perKg: 26000, minDays: 2, maxDays: 3 },
    { value: "sumatera", label: "Sumatera", perKg: 30000, minDays: 2, maxDays: 3 },
    { value: "kalimantan-sulawesi", label: "Kalimantan & Sulawesi", perKg: 34000, minDays: 2, maxDays: 4 }
];

export const FEE_SERVICES = [
    { value: "REGULER", label: "Reguler", multiplier: 1 },
    { value: "INSTANT_FROZEN", label: "Instant Frozen", multiplier: 1.6 }
];
