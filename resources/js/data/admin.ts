export type StatusTone = "alert" | "transit" | "waiting" | "done" | "returned" | "scheduled";

export interface ShipmentRow {
    tracking: string;
    buyer: string;
    location: string;
    schedule: string;
    courier: string | null;
    status: string;
    statusKey: string;
    tone: StatusTone;
    hasIssue: boolean;
}

export const SHIPMENTS: ShipmentRow[] = [
    {
        tracking: "AJ-FRZ-0012345",
        buyer: "Rina Putri",
        location: "Tebet, Jaksel",
        schedule: "Hari ini, 14:00 - 16:00",
        courier: "Budi Santoso",
        status: "Kendala (Kemasan bocor)",
        statusKey: "issue",
        tone: "alert",
        hasIssue: true
    },
    {
        tracking: "AJ-FRZ-0012346",
        buyer: "Hendra Wijaya",
        location: "Kelapa Gading",
        schedule: "Hari ini, 15:00 - 17:00",
        courier: "Dimas Pratama",
        status: "Sedang Diantar",
        statusKey: "transit",
        tone: "transit",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012347",
        buyer: "Siti Rahma",
        location: "Kebayoran Baru",
        schedule: "Hari ini, 16:00 - 18:00",
        courier: "Budi Santoso",
        status: "Menunggu Konfirmasi",
        statusKey: "waiting",
        tone: "waiting",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012348",
        buyer: "Kevin Tan",
        location: "Pluit, Jakut",
        schedule: "Hari ini, 13:00 - 15:00",
        courier: "Ahmad Subagyo",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012349",
        buyer: "Dewi Lestari",
        location: "Serpong, Tangsel",
        schedule: "Hari ini, 14:00 - 16:00",
        courier: "Dani Kurniawan",
        status: "Sedang Diantar",
        statusKey: "transit",
        tone: "transit",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012350",
        buyer: "Rizky Ramadhan",
        location: "Cilandak",
        schedule: "Besok, 09:00 - 11:00",
        courier: null,
        status: "Dijadwalkan",
        statusKey: "scheduled",
        tone: "scheduled",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012351",
        buyer: "Maya Safitri",
        location: "Bintaro, Tangsel",
        schedule: "Hari ini, 13:00 - 15:00",
        courier: "Budi Santoso",
        status: "Sedang Diantar",
        statusKey: "transit",
        tone: "transit",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012352",
        buyer: "Farhan Malik",
        location: "Tebet, Jaksel",
        schedule: "Hari ini, 15:00 - 17:00",
        courier: "Dani Kurniawan",
        status: "Menunggu Konfirmasi",
        statusKey: "waiting",
        tone: "waiting",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012353",
        buyer: "Cindy Claudia",
        location: "Sunter, Jakut",
        schedule: "Hari ini, 16:00 - 18:00",
        courier: "Ahmad Subagyo",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012354",
        buyer: "Hendro Hartono",
        location: "Bekasi Barat",
        schedule: "Besok, 09:00 - 11:00",
        courier: null,
        status: "Dijadwalkan",
        statusKey: "scheduled",
        tone: "scheduled",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012355",
        buyer: "Nadia Suryani",
        location: "Depok",
        schedule: "Besok, 11:00 - 13:00",
        courier: null,
        status: "Dijadwalkan",
        statusKey: "scheduled",
        tone: "scheduled",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012356",
        buyer: "Bagus Prasetyo",
        location: "Cempaka Putih",
        schedule: "Besok, 13:00 - 15:00",
        courier: null,
        status: "Dijadwalkan",
        statusKey: "scheduled",
        tone: "scheduled",
        hasIssue: false
    },
    // Deliveries that already closed; they stay in the same list and are reachable
    // through the status filter instead of a separate archive screen.
    {
        tracking: "AJ-FRZ-0012344",
        buyer: "Andi Saputra",
        location: "Tebet, Jaksel",
        schedule: "Hari ini, 13:42 WIB",
        courier: "Budi Santoso",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012343",
        buyer: "Nadia Suryani",
        location: "Depok",
        schedule: "Hari ini, 12:18 WIB",
        courier: "Dani Kurniawan",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012341",
        buyer: "Bagus Prasetyo",
        location: "Cempaka Putih",
        schedule: "Hari ini, 11:05 WIB",
        courier: "Ahmad Subagyo",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012339",
        buyer: "Tania Wijaya",
        location: "Kelapa Gading",
        schedule: "Hari ini, 10:47 WIB",
        courier: "Dimas Pratama",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done",
        hasIssue: false
    },
    {
        tracking: "AJ-FRZ-0012336",
        buyer: "Rendra Kusuma",
        location: "Bekasi Barat",
        schedule: "Hari ini, 09:52 WIB",
        courier: "Budi Santoso",
        status: "Dikembalikan",
        statusKey: "returned",
        tone: "returned",
        hasIssue: false
    }
];

export const STATUS_TONE: Record<StatusTone, { dot: string; text: string }> = {
    alert: { dot: "bg-error", text: "text-error font-medium" },
    transit: { dot: "bg-secondary", text: "text-on-surface" },
    waiting: { dot: "bg-outline", text: "text-secondary" },
    done: { dot: "bg-tertiary-container", text: "text-tertiary" },
    returned: { dot: "bg-outline", text: "text-secondary" },
    scheduled: { dot: "bg-secondary-fixed-dim", text: "text-secondary" }
};

/** `selesai` is an aggregate for the deliveries that already closed. */
export const STATUS_OPTIONS = [
    { value: "issue", label: "Kendala" },
    { value: "transit", label: "Sedang Diantar" },
    { value: "waiting", label: "Menunggu Konfirmasi" },
    { value: "scheduled", label: "Dijadwalkan" },
    { value: "delivered", label: "Diterima" },
    { value: "returned", label: "Dikembalikan" },
    { value: "selesai", label: "Selesai (Arsip)" }
];

export const COURIER_OPTIONS = [
    { value: "Budi Santoso", label: "Budi Santoso" },
    { value: "Dimas Pratama", label: "Dimas Pratama" },
    { value: "Ahmad Subagyo", label: "Ahmad Subagyo" },
    { value: "Dani Kurniawan", label: "Dani Kurniawan" },
    { value: "unassigned", label: "Belum Ditugaskan" }
];

export const SCHEDULE_OPTIONS = [
    { value: "today", label: "Hari Ini" },
    { value: "tomorrow", label: "Besok" }
];

export interface ReportIssueRow {
    tracking: string;
    buyer: string;
    address: string;
    courier: string;
    issue: string;
    issueAlert?: boolean;
    decision: string;
    status: string;
    statusActive?: boolean;
}

export const REPORT_ISSUES: ReportIssueRow[] = [
    {
        tracking: "AJ-FRZ-0012345",
        buyer: "Rina Putri",
        address: "Jl. Tebet Barat Dalam VII No. 14, Jakarta Selatan",
        courier: "Budi Santoso",
        issue: "Kemasan bocor",
        decision: "Kembalikan Paket",
        status: "Selesai"
    },
    {
        tracking: "AJ-FRZ-0012320",
        buyer: "Hendro Hartono",
        address: "Jl. Bekasi Barat Raya No. 8, Bekasi",
        courier: "Dani Kurniawan",
        issue: "Suhu naik (-8°C)",
        issueAlert: true,
        decision: "Kirim Ulang",
        status: "Proses Kirim Ulang",
        statusActive: true
    },
    {
        tracking: "AJ-FRZ-0012288",
        buyer: "Cindy Claudia",
        address: "Jl. Sunter Jaya No. 21, Jakarta Utara",
        courier: "Ahmad Subagyo",
        issue: "Kemasan basah luar",
        decision: "Lanjutkan (Kompensasi)",
        status: "Selesai"
    },
    {
        tracking: "AJ-FRZ-0012280",
        buyer: "Dimas Anggara",
        address: "Jl. Kalimalang No. 45, Jakarta Timur",
        courier: "Dani Kurniawan",
        issue: "Suhu cooler box naik",
        issueAlert: true,
        decision: "Kirim Ulang",
        status: "Selesai"
    },
    {
        tracking: "AJ-FRZ-0012264",
        buyer: "Sinta Maharani",
        address: "Jl. Margonda Raya No. 12, Depok",
        courier: "Budi Santoso",
        issue: "Kemasan penyok",
        decision: "Lanjutkan (Kompensasi)",
        status: "Selesai"
    },
    {
        tracking: "AJ-FRZ-0012210",
        buyer: "Farhan Malik",
        address: "Jl. Tebet Utara Dalam No. 3, Jakarta Selatan",
        courier: "Budi Santoso",
        issue: "Kemasan bocor",
        decision: "Kembalikan Paket",
        status: "Selesai"
    },
    {
        tracking: "AJ-FRZ-0012195",
        buyer: "Maya Safitri",
        address: "Jl. Bintaro Utama Sektor 3, Tangerang Selatan",
        courier: "Hendra Setiawan",
        issue: "Pembeli tidak di tempat",
        decision: "Jadwal Ulang",
        status: "Dijadwalkan",
        statusActive: true
    }
];

export interface AuditEntry {
    time: string;
    actor: string;
    action: string;
    highlight?: boolean;
}

export const AUDIT_TIMELINE: AuditEntry[] = [
    {
        time: "14:18 WIB",
        actor: "Budi Santoso (Kurir)",
        action: "Melaporkan kendala kemasan bocor & unggah foto lapangan."
    },
    {
        time: "14:20 WIB",
        actor: "Sistem Dispatcher",
        action: "Notifikasi otomatis terkirim ke WhatsApp Dispatcher dan Admin Area Jaksel."
    },
    {
        time: "14:25 WIB",
        actor: "Dimas Pratama (Admin)",
        action: "Meninjau laporan, verifikasi kerusakan foto es krim cair, dan memproses tiket klaim.",
        highlight: true
    }
];
