import { addDaysIso, todayIso } from "@/lib/date";
import { PHOTO_PLACEHOLDER } from "@/lib/photo";

export type StatusTone = "alert" | "transit" | "waiting" | "done" | "returned" | "scheduled";

export type IssueCategoryKey =
    | "kemasan_bocor"
    | "kemasan_basah"
    | "suhu_naik"
    | "pembeli_tidak_ada"
    | "alamat_tidak_ditemukan"
    | "cod_ditolak";

export type IssueDecisionKey = "RETURN" | "PROCEED" | "RESCHEDULE" | "RECHECK";

export type ResolutionStatus =
    | "Menunggu Keputusan"
    | "Selesai"
    | "Dilanjutkan"
    | "Proses Kirim Ulang"
    | "Dijadwalkan"
    | "Pemeriksaan Ulang";

/**
 * Demo clock. The dataset is anchored to the day the app runs, so the "Hari ini"
 * and "Besok" windows can never drift away from the dates the report filters on.
 */
export const TODAY = todayIso();
export const TOMORROW = addDaysIso(TODAY, 1);

/** Window label for a delivery day: "Hari ini, 14:00 - 16:00". */
export function scheduleLabel(date: string, hours: string): string {
    return `${date === TODAY ? "Hari ini, " : "Besok, "}${hours}`;
}

/** Category labels, ported from App\\Enums\\IssueCategory. */
export const ISSUE_CATEGORY_LABEL: Record<IssueCategoryKey, string> = {
    kemasan_bocor: "Kemasan bocor",
    kemasan_basah: "Kemasan basah luar",
    suhu_naik: "Suhu naik",
    pembeli_tidak_ada: "Pembeli tidak di tempat",
    alamat_tidak_ditemukan: "Alamat tidak ditemukan",
    cod_ditolak: "COD ditolak"
};

/** Decision labels and hints, ported from App\\Enums\\IssueDecision. */
export const ISSUE_DECISIONS: Record<IssueDecisionKey, { label: string; hint: string }> = {
    RETURN: { label: "Kembalikan Paket", hint: "Paket ditarik ke DC untuk klaim retur" },
    PROCEED: { label: "Lanjutkan", hint: "Pengantaran diteruskan, kompensasi ditanggung operasional" },
    RESCHEDULE: { label: "Jadwal Ulang", hint: "Minta pembeli memilih jadwal baru" },
    RECHECK: { label: "Periksa Ulang", hint: "Pemeriksaan ulang fisik di lapangan" }
};

/** Decisions each category allows, ported from IssueCategory::allowedDecisions(). */
export const ISSUE_CATEGORY_DECISIONS: Record<IssueCategoryKey, IssueDecisionKey[]> = {
    kemasan_bocor: ["RETURN", "PROCEED"],
    kemasan_basah: ["RETURN", "PROCEED"],
    suhu_naik: ["RETURN", "PROCEED"],
    pembeli_tidak_ada: ["RESCHEDULE", "RETURN"],
    alamat_tidak_ditemukan: ["RESCHEDULE", "RETURN"],
    cod_ditolak: ["RESCHEDULE", "RETURN"]
};

/** Resolution a decision lands on, mirroring IssueReport::resolutionStatus(). */
const RESOLUTION_BY_DECISION: Record<IssueDecisionKey, ResolutionStatus> = {
    RETURN: "Selesai",
    PROCEED: "Dilanjutkan",
    RESCHEDULE: "Dijadwalkan",
    RECHECK: "Pemeriksaan Ulang"
};

export function resolutionFor(decision: IssueDecisionKey | null): ResolutionStatus {
    return decision === null ? "Menunggu Keputusan" : RESOLUTION_BY_DECISION[decision];
}

/** Resolution states mapped onto the shared status palette. */
export const RESOLUTION_TONE: Record<ResolutionStatus, StatusTone> = {
    "Menunggu Keputusan": "alert",
    Selesai: "done",
    Dilanjutkan: "transit",
    "Proses Kirim Ulang": "waiting",
    Dijadwalkan: "scheduled",
    "Pemeriksaan Ulang": "alert"
};

export interface ShipmentIssue {
    categoryKey: IssueCategoryKey;
    /** Row label: the category label, with the reading appended for suhu naik. */
    label: string;
    /** Courier's field note, shown in the detail modal. */
    note: string;
    condition: string[];
    reportedAt: string;
    decision: IssueDecisionKey | null;
    resolution: ResolutionStatus;
}

export interface ShipmentRow {
    tracking: string;
    buyer: string;
    /** Short area shown in the shipment table. */
    location: string;
    /** Full street address for the detail modal; falls back to `location`. */
    address?: string;
    schedule: string;
    /** ISO delivery day, driving the date filters and the daily report. */
    scheduledDate: string;
    courier: string | null;
    status: string;
    statusKey: string;
    tone: StatusTone;
    issue?: ShipmentIssue;
}

const ISSUE_NOTE =
    "Kemasan luar kardus basah dan es krim bocor di bagian tutup saat hendak diserahkan ke pembeli. Pembeli menolak paket.";

const ISSUE_CONDITION = ["Kemasan basah", "Es krim meleleh", "Segel bocor"];

function issue(
    categoryKey: IssueCategoryKey,
    label: string,
    reportedAt: string,
    decision: IssueDecisionKey | null,
    resolution: ResolutionStatus
): ShipmentIssue {
    return { categoryKey, label, reportedAt, decision, resolution, note: ISSUE_NOTE, condition: ISSUE_CONDITION };
}

export const SHIPMENTS: ShipmentRow[] = [
    {
        tracking: "AJ-FRZ-0012345",
        buyer: "Rina Putri",
        location: "Tebet, Jaksel",
        address: "Jl. Tebet Barat Dalam VII No. 14, Jakarta Selatan",
        schedule: scheduleLabel(TODAY, "14:00 - 16:00"),
        scheduledDate: TODAY,
        courier: "Budi Santoso",
        status: "Kendala (Kemasan bocor)",
        statusKey: "issue",
        tone: "alert",
        issue: issue("kemasan_bocor", "Kemasan bocor", "14:18 WIB", "RETURN", "Selesai")
    },
    {
        tracking: "AJ-FRZ-0012346",
        buyer: "Hendra Wijaya",
        location: "Kelapa Gading",
        schedule: scheduleLabel(TODAY, "15:00 - 17:00"),
        scheduledDate: TODAY,
        courier: "Dimas Pratama",
        status: "Sedang Diantar",
        statusKey: "transit",
        tone: "transit"
    },
    {
        tracking: "AJ-FRZ-0012347",
        buyer: "Siti Rahma",
        location: "Kebayoran Baru",
        schedule: scheduleLabel(TODAY, "16:00 - 18:00"),
        scheduledDate: TODAY,
        courier: "Budi Santoso",
        status: "Menunggu Konfirmasi",
        statusKey: "waiting",
        tone: "waiting"
    },
    {
        tracking: "AJ-FRZ-0012348",
        buyer: "Kevin Tan",
        location: "Pluit, Jakut",
        schedule: scheduleLabel(TODAY, "13:00 - 15:00"),
        scheduledDate: TODAY,
        courier: "Ahmad Subagyo",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done"
    },
    {
        tracking: "AJ-FRZ-0012349",
        buyer: "Dewi Lestari",
        location: "Serpong, Tangsel",
        schedule: scheduleLabel(TODAY, "14:00 - 16:00"),
        scheduledDate: TODAY,
        courier: "Dani Kurniawan",
        status: "Sedang Diantar",
        statusKey: "transit",
        tone: "transit"
    },
    {
        tracking: "AJ-FRZ-0012350",
        buyer: "Rizky Ramadhan",
        location: "Cilandak",
        schedule: scheduleLabel(TOMORROW, "09:00 - 11:00"),
        scheduledDate: TOMORROW,
        courier: null,
        status: "Dijadwalkan",
        statusKey: "scheduled",
        tone: "scheduled"
    },
    {
        tracking: "AJ-FRZ-0012351",
        buyer: "Maya Safitri",
        location: "Bintaro, Tangsel",
        schedule: scheduleLabel(TODAY, "13:00 - 15:00"),
        scheduledDate: TODAY,
        courier: "Budi Santoso",
        status: "Sedang Diantar",
        statusKey: "transit",
        tone: "transit"
    },
    {
        tracking: "AJ-FRZ-0012352",
        buyer: "Farhan Malik",
        location: "Tebet, Jaksel",
        schedule: scheduleLabel(TODAY, "15:00 - 17:00"),
        scheduledDate: TODAY,
        courier: "Dani Kurniawan",
        status: "Menunggu Konfirmasi",
        statusKey: "waiting",
        tone: "waiting"
    },
    {
        tracking: "AJ-FRZ-0012353",
        buyer: "Cindy Claudia",
        location: "Sunter, Jakut",
        schedule: scheduleLabel(TODAY, "16:00 - 18:00"),
        scheduledDate: TODAY,
        courier: "Ahmad Subagyo",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done"
    },
    {
        tracking: "AJ-FRZ-0012354",
        buyer: "Hendro Hartono",
        location: "Bekasi Barat",
        schedule: scheduleLabel(TOMORROW, "09:00 - 11:00"),
        scheduledDate: TOMORROW,
        courier: null,
        status: "Dijadwalkan",
        statusKey: "scheduled",
        tone: "scheduled"
    },
    {
        tracking: "AJ-FRZ-0012355",
        buyer: "Nadia Suryani",
        location: "Depok",
        schedule: scheduleLabel(TOMORROW, "11:00 - 13:00"),
        scheduledDate: TOMORROW,
        courier: null,
        status: "Dijadwalkan",
        statusKey: "scheduled",
        tone: "scheduled"
    },
    {
        tracking: "AJ-FRZ-0012356",
        buyer: "Bagus Prasetyo",
        location: "Cempaka Putih",
        schedule: scheduleLabel(TOMORROW, "13:00 - 15:00"),
        scheduledDate: TOMORROW,
        courier: null,
        status: "Dijadwalkan",
        statusKey: "scheduled",
        tone: "scheduled"
    },
    // Deliveries that already closed; they stay in the same list and are reachable
    // through the status filter instead of a separate archive screen.
    {
        tracking: "AJ-FRZ-0012344",
        buyer: "Andi Saputra",
        location: "Tebet, Jaksel",
        schedule: scheduleLabel(TODAY, "13:42 WIB"),
        scheduledDate: TODAY,
        courier: "Budi Santoso",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done"
    },
    {
        tracking: "AJ-FRZ-0012343",
        buyer: "Nadia Suryani",
        location: "Depok",
        schedule: scheduleLabel(TODAY, "12:18 WIB"),
        scheduledDate: TODAY,
        courier: "Dani Kurniawan",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done"
    },
    {
        tracking: "AJ-FRZ-0012341",
        buyer: "Bagus Prasetyo",
        location: "Cempaka Putih",
        schedule: scheduleLabel(TODAY, "11:05 WIB"),
        scheduledDate: TODAY,
        courier: "Ahmad Subagyo",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done"
    },
    {
        tracking: "AJ-FRZ-0012339",
        buyer: "Tania Wijaya",
        location: "Kelapa Gading",
        schedule: scheduleLabel(TODAY, "10:47 WIB"),
        scheduledDate: TODAY,
        courier: "Dimas Pratama",
        status: "Diterima",
        statusKey: "delivered",
        tone: "done"
    },
    {
        tracking: "AJ-FRZ-0012336",
        buyer: "Rendra Kusuma",
        location: "Bekasi Barat",
        schedule: scheduleLabel(TODAY, "09:52 WIB"),
        scheduledDate: TODAY,
        courier: "Budi Santoso",
        status: "Dikembalikan",
        statusKey: "returned",
        tone: "returned"
    },
    // Earlier kendala cases. They used to live in a separate report list, which let
    // the report and the shipment queue disagree about the same delivery.
    {
        tracking: "AJ-FRZ-0012320",
        buyer: "Hendro Hartono",
        location: "Bekasi Barat",
        address: "Jl. Bekasi Barat Raya No. 8, Bekasi",
        schedule: scheduleLabel(TODAY, "09:00 - 11:00"),
        scheduledDate: TODAY,
        courier: "Dani Kurniawan",
        status: "Kendala (Suhu naik)",
        statusKey: "issue",
        tone: "alert",
        issue: issue("suhu_naik", "Suhu naik (-8°C)", "09:24 WIB", "PROCEED", "Dilanjutkan")
    },
    {
        tracking: "AJ-FRZ-0012288",
        buyer: "Cindy Claudia",
        location: "Sunter, Jakut",
        address: "Jl. Sunter Jaya No. 21, Jakarta Utara",
        schedule: scheduleLabel(TODAY, "10:00 - 12:00"),
        scheduledDate: TODAY,
        courier: "Ahmad Subagyo",
        status: "Kendala (Kemasan basah luar)",
        statusKey: "issue",
        tone: "alert",
        issue: issue("kemasan_basah", "Kemasan basah luar", "10:36 WIB", "PROCEED", "Dilanjutkan")
    },
    {
        tracking: "AJ-FRZ-0012280",
        buyer: "Dimas Anggara",
        location: "Kalimalang, Jaktim",
        address: "Jl. Kalimalang No. 45, Jakarta Timur",
        schedule: scheduleLabel(TODAY, "11:00 - 13:00"),
        scheduledDate: TODAY,
        courier: "Dani Kurniawan",
        status: "Kendala (Suhu naik)",
        statusKey: "issue",
        tone: "alert",
        issue: issue("suhu_naik", "Suhu cooler box naik", "11:48 WIB", "RETURN", "Selesai")
    },
    {
        tracking: "AJ-FRZ-0012264",
        buyer: "Sinta Maharani",
        location: "Margonda, Depok",
        address: "Jl. Margonda Raya No. 12, Depok",
        schedule: scheduleLabel(TODAY, "12:00 - 14:00"),
        scheduledDate: TODAY,
        courier: "Budi Santoso",
        status: "Kendala (Kemasan bocor)",
        statusKey: "issue",
        tone: "alert",
        issue: issue("kemasan_bocor", "Kemasan penyok", "12:22 WIB", "PROCEED", "Dilanjutkan")
    },
    {
        tracking: "AJ-FRZ-0012210",
        buyer: "Farhan Malik",
        location: "Tebet, Jaksel",
        address: "Jl. Tebet Utara Dalam No. 3, Jakarta Selatan",
        schedule: scheduleLabel(TODAY, "13:00 - 15:00"),
        scheduledDate: TODAY,
        courier: "Budi Santoso",
        status: "Kendala (Kemasan bocor)",
        statusKey: "issue",
        tone: "alert",
        issue: issue("kemasan_bocor", "Kemasan bocor", "13:31 WIB", "RETURN", "Selesai")
    },
    {
        tracking: "AJ-FRZ-0012195",
        buyer: "Maya Safitri",
        location: "Bintaro, Tangsel",
        address: "Jl. Bintaro Utama Sektor 3, Tangerang Selatan",
        schedule: scheduleLabel(TODAY, "15:00 - 17:00"),
        scheduledDate: TODAY,
        courier: "Hendra Setiawan",
        status: "Kendala (Pembeli tidak di tempat)",
        statusKey: "issue",
        tone: "alert",
        issue: issue("pembeli_tidak_ada", "Pembeli tidak di tempat", "15:44 WIB", "RESCHEDULE", "Dijadwalkan")
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
    { value: "Hendra Setiawan", label: "Hendra Setiawan" },
    { value: "unassigned", label: "Belum Ditugaskan" }
];

/** Couriers a shipment can be handed to; `unassigned` exists for filtering only. */
export const COURIERS = COURIER_OPTIONS.filter((option) => option.value !== "unassigned");

export const DECISION_REASON_DEFAULT =
    "Kemasan bocor di lokasi penerima, barang tidak layak konsumsi. Tarik kembali ke gudang pusat untuk proses klaim vendor.";

/** Slots the cold-chain fleet can move in a day, from config/coldchain.php. */
export const DAILY_FLEET_CAPACITY = 1500;

const COURIER_ASSIGNMENT = "Motor Box #04 · Hub Jaksel";

const TARGET_TEMPERATURE = "-18°C";

/** Field photo the courier attached while inspecting a damaged package. */
const FIELD_PROOF = {
    photo: PHOTO_PLACEHOLDER,
    caption: "Foto pemeriksaan · 14:15 WIB",
    meta: "Tolak penerimaan"
};

/** Handover photo captured when the recipient accepted the package. */
const HANDOVER_PROOF = {
    photo: PHOTO_PLACEHOLDER,
    caption: "Foto serah terima",
    meta: "Diterima pembeli"
};

/** Delivery day implied by a window label such as "Hari ini, 14:00 - 16:00". */
export function dateForWindow(window: string): string {
    return window.startsWith("Besok") ? TOMORROW : TODAY;
}

export function shipmentsOn(date: string): ShipmentRow[] {
    return SHIPMENTS.filter((row) => row.scheduledDate === date);
}

/** Deliveries with a kendala on the given day, optionally narrowed to one category. */
export function reportIssues(date: string, category: IssueCategoryKey | "" = ""): ShipmentRow[] {
    return shipmentsOn(date).filter(
        (row) => row.issue !== undefined && (category === "" || row.issue.categoryKey === category)
    );
}

/** Categories with at least one kendala that day, mirroring the report API. */
export function issueCategoriesOn(date: string): IssueCategoryKey[] {
    const keys = reportIssues(date).map((row) => (row.issue as ShipmentIssue).categoryKey);
    return Array.from(new Set(keys));
}

export interface ReportKpis {
    total: number;
    delivered: number;
    issues: number;
    successRatePct: number;
    incidentRatePct: number;
}

/** Same formulas as DailyReportQuery::getKpis(), applied to the demo dataset. */
export function reportKpis(date: string): ReportKpis {
    const rows = shipmentsOn(date);
    const total = rows.length;
    const delivered = rows.filter((row) => row.statusKey === "delivered").length;
    const issues = rows.filter((row) => row.issue !== undefined).length;

    return {
        total,
        delivered,
        issues,
        successRatePct: total > 0 ? Math.round((delivered / total) * 1000) / 10 : 0,
        incidentRatePct: total > 0 ? Math.round((issues / total) * 10000) / 100 : 0
    };
}

/** Delivery days present in the dataset, oldest first. */
export function reportDates(): string[] {
    return Array.from(new Set(SHIPMENTS.map((row) => row.scheduledDate))).sort();
}

export interface ShipmentDetail {
    resi: string;
    buyer: string;
    address: string;
    courier: string | null;
    schedule: string;
    scheduledDate: string;
    status: string;
    tone: StatusTone;
    temperature: string;
    box: string;
    issue: ShipmentIssue | null;
    proof: { photo: string; caption: string; meta: string } | null;
}

function buildDetail(row: ShipmentRow): ShipmentDetail {
    return {
        resi: row.tracking,
        buyer: row.buyer,
        address: row.address ?? row.location,
        courier: row.courier,
        schedule: row.schedule,
        scheduledDate: row.scheduledDate,
        status: row.status,
        tone: row.tone,
        temperature: TARGET_TEMPERATURE,
        box: COURIER_ASSIGNMENT,
        issue: row.issue ?? null,
        proof: row.issue ? FIELD_PROOF : row.statusKey === "delivered" ? HANDOVER_PROOF : null
    };
}

/** Detail for a shipment already in hand, including one created this session. */
export function detailFromRow(row: ShipmentRow): ShipmentDetail {
    return buildDetail(row);
}

export function resolveShipmentDetail(resi: string): ShipmentDetail | null {
    const row = SHIPMENTS.find((shipment) => shipment.tracking === resi);
    return row ? buildDetail(row) : null;
}
