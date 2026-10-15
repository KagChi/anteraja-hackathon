/**
 * Presentation tables for the admin screens.
 *
 * The data itself comes from the API; what lives here is the copy and the
 * mapping from the API's tone vocabulary onto the design system's colour roles.
 */
import type { Tone } from "@/lib/api";

export interface StatusTone {
    dot: string;
    text: string;
}

/** Tone palette, mirroring App\Enums\Tone. */
export const STATUS_TONE: Record<Tone, StatusTone> = {
    neutral: { dot: "bg-outline", text: "text-secondary" },
    muted: { dot: "bg-outline", text: "text-secondary" },
    warning: { dot: "bg-secondary-fixed-dim", text: "text-secondary" },
    info: { dot: "bg-secondary", text: "text-on-surface" },
    danger: { dot: "bg-error", text: "text-error font-medium" },
    dark: { dot: "bg-secondary", text: "text-on-surface" },
    success: { dot: "bg-tertiary-container", text: "text-tertiary" }
};

export type IssueCategoryKey =
    | "kemasan_bocor"
    | "kemasan_basah"
    | "suhu_naik"
    | "pembeli_tidak_ada"
    | "alamat_tidak_ditemukan"
    | "cod_ditolak";

/** Category labels, ported from App\Enums\IssueCategory. */
export const ISSUE_CATEGORY_LABEL: Record<IssueCategoryKey, string> = {
    kemasan_bocor: "Kemasan bocor",
    kemasan_basah: "Kemasan basah luar",
    suhu_naik: "Suhu naik",
    pembeli_tidak_ada: "Pembeli tidak di tempat",
    alamat_tidak_ditemukan: "Alamat tidak ditemukan",
    cod_ditolak: "COD ditolak"
};

export type IssueDecisionKey = "RETURN" | "RESEND" | "PROCEED" | "RESCHEDULE" | "RECHECK";

/** Decision labels and hints, ported from App\Enums\IssueDecision. */
export const ISSUE_DECISIONS: Record<IssueDecisionKey, { label: string; hint: string }> = {
    RETURN: { label: "Kembalikan Paket", hint: "Paket ditarik ke DC untuk klaim retur" },
    RESEND: { label: "Kirim Ulang", hint: "Buat resi baru slot pengiriman terdekat dari hub asal" },
    PROCEED: { label: "Lanjutkan", hint: "Kompensasi parsial jika disetujui pembeli" },
    RESCHEDULE: { label: "Jadwal Ulang", hint: "Minta pembeli memilih jadwal baru" },
    RECHECK: { label: "Periksa Ulang", hint: "Pemeriksaan ulang fisik di lapangan" }
};

/** Prefilled dispatcher reason, kept as a starting point rather than a default. */
export const DECISION_REASON_DEFAULT =
    "Kemasan bocor di lokasi penerima, barang tidak layak konsumsi. Tarik kembali ke gudang pusat untuk proses klaim vendor.";

/** Delivery slots the cold-chain fleet runs, from config/coldchain.php. */
export const DELIVERY_SLOTS = ["09:00-11:00", "11:00-13:00", "13:00-15:00", "15:00-17:00", "17:00-19:00"];
