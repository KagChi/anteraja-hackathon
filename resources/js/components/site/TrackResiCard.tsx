import { type FormEvent, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ApiError, api, type Tone, type TrackResult } from "@/lib/api";
import { cx } from "@/lib/cx";

interface TrackResiCardProps {
    className?: string;
}

/** Mirrors the server route's guard: a resi is letters, digits and dashes. */
const RESI_PATTERN = /^[A-Z0-9-]{8,32}$/;

/** Status chip colours per tone, the same roles the consoles use. */
const TONE_CHIP: Record<Tone, string> = {
    neutral: "bg-surface-container text-secondary",
    muted: "bg-surface-container text-secondary",
    warning: "bg-secondary-fixed text-on-secondary-fixed",
    info: "bg-secondary-container text-on-secondary-container",
    danger: "bg-error-container text-on-error-container",
    dark: "bg-inverse-surface text-inverse-on-surface",
    success: "bg-tertiary-fixed text-on-tertiary-fixed"
};

/**
 * The landing page's "Lacak Pesananmu" panel.
 *
 * It queries the public tracking endpoint (GET /api/track/{resi}), which
 * answers with the status, the agreed window, the destination city and a
 * timeline of fixed labels — never the recipient's name, phone or address.
 * Booking, schedule confirmation and proof of delivery stay in the app, so the
 * result points there instead of offering a web flow.
 */
export function TrackResiCard({ className }: TrackResiCardProps) {
    const [resi, setResi] = useState("");
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<TrackResult | null>(null);

    async function submit(event: FormEvent) {
        event.preventDefault();

        const value = resi.trim().toUpperCase();

        if (value.length < 8) {
            setError("Nomor resi minimal 8 karakter, contoh AJ-FRZ-0012345.");
            setResult(null);
            return;
        }

        if (!RESI_PATTERN.test(value)) {
            setError("Format nomor resi belum sesuai. Periksa kembali nomor di bukti pengiriman.");
            setResult(null);
            return;
        }

        setError(null);
        setLoading(true);

        try {
            const response = await api.track.show(value);
            setResult(response.data);
        } catch (caught) {
            setResult(null);

            if (caught instanceof ApiError && caught.status === 404) {
                setError("Nomor resi tidak ditemukan. Periksa kembali nomor di bukti pengiriman.");
            } else {
                setError(caught instanceof ApiError ? caught.message : "Pencarian resi gagal. Coba lagi.");
            }
        } finally {
            setLoading(false);
        }
    }

    return (
        <form
            onSubmit={submit}
            className={cx(
                "flex flex-col gap-3 rounded-2xl border border-black/5 bg-surface-container-lowest p-5 shadow-xl sm:p-6",
                className
            )}
        >
            <div className="flex items-center gap-2">
                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <MaterialIcon name="pin_drop" className="text-[20px]" />
                </span>
                <div className="flex flex-col">
                    <span className="font-headline-sm text-headline-sm text-on-surface">Lacak Pesananmu</span>
                    <span className="font-body-sm text-body-sm text-secondary">Masukkan nomor resi pengiriman</span>
                </div>
            </div>

            <label className="flex flex-col gap-1.5">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Nomor resi</span>
                <div className="flex gap-2">
                    <input
                        value={resi}
                        onChange={(event) => setResi(event.target.value)}
                        inputMode="text"
                        autoComplete="off"
                        placeholder="AJ-FRZ-0012345"
                        aria-invalid={error !== null}
                        className="h-11 w-full rounded-xl border border-outline-variant bg-surface px-3 font-data-mono text-data-mono text-on-surface outline-none transition-colors placeholder:text-secondary/60 focus:border-primary"
                    />
                    <button
                        type="submit"
                        disabled={loading}
                        className="h-11 shrink-0 rounded-xl bg-primary px-5 font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-60"
                    >
                        {loading ? "Mencari…" : "Lacak"}
                    </button>
                </div>
            </label>

            {error !== null ? (
                <p className="flex items-start gap-2 rounded-lg bg-error-container px-3 py-2 font-body-sm text-body-sm text-on-error-container">
                    <MaterialIcon name="error_outline" className="text-[16px]" />
                    {error}
                </p>
            ) : null}

            {result !== null ? (
                <div className="flex flex-col gap-3 rounded-xl bg-surface-container-low p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="font-data-mono font-semibold text-body-sm text-on-surface">{result.resi}</span>
                        <span
                            className={cx(
                                "rounded-full px-2.5 py-1 font-label-sm text-label-sm",
                                TONE_CHIP[result.status.tone] ?? TONE_CHIP.neutral
                            )}
                        >
                            {result.status.label}
                        </span>
                    </div>

                    <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div className="flex flex-col gap-0.5">
                            <dt className="font-label-sm text-label-sm text-secondary uppercase">Layanan</dt>
                            <dd className="font-body-sm text-body-sm text-on-surface">{result.service}</dd>
                        </div>
                        <div className="flex flex-col gap-0.5">
                            <dt className="font-label-sm text-label-sm text-secondary uppercase">Tujuan</dt>
                            <dd className="font-body-sm text-body-sm text-on-surface">
                                {result.destination_city ?? "- "}
                            </dd>
                        </div>
                        <div className="flex flex-col gap-0.5">
                            <dt className="font-label-sm text-label-sm text-secondary uppercase">Jadwal</dt>
                            <dd className="font-body-sm text-body-sm text-on-surface">{result.schedule_label}</dd>
                        </div>
                    </dl>

                    {result.timeline.length > 0 ? (
                        <ol className="flex list-none flex-col gap-2 p-0">
                            {result.timeline.map((entry) => (
                                <li key={entry.label + entry.time} className="flex items-start gap-2">
                                    <span
                                        className={cx(
                                            "mt-1.5 h-2 w-2 shrink-0 rounded-full",
                                            entry.is_accent ? "bg-primary" : "bg-outline"
                                        )}
                                    />
                                    <span className="flex flex-1 flex-col">
                                        <span className="flex flex-wrap items-center gap-2">
                                            <span
                                                className={cx(
                                                    "font-label-md text-label-md",
                                                    entry.is_accent ? "text-primary" : "text-on-surface"
                                                )}
                                            >
                                                {entry.label}
                                            </span>
                                            <span className="font-label-sm text-label-sm text-secondary">
                                                {entry.time}
                                            </span>
                                        </span>
                                        <span className="font-body-sm text-body-sm text-secondary">{entry.detail}</span>
                                    </span>
                                </li>
                            ))}
                        </ol>
                    ) : null}

                    <span className="font-body-sm text-body-sm text-secondary">
                        {result.cold_chain} dijaga selama transit. Detail kurir, konfirmasi jadwal, dan bukti serah
                        terima tampil di aplikasi pelanggan.
                    </span>

                    <a
                        href="#unduh"
                        className="inline-flex w-fit items-center gap-1.5 rounded-full bg-primary px-3.5 py-2 font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        <MaterialIcon name="android" className="text-[16px]" />
                        Unduh aplikasi
                    </a>
                </div>
            ) : null}
        </form>
    );
}
