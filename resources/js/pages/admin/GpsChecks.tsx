import { Head } from "@inertiajs/react";
import { type FormEvent, useCallback, useEffect, useRef, useState } from "react";
import { Dialog } from "@/components/Dialog";
import { LeafletMap, type MapCircle, type MapMarker } from "@/components/LeafletMap";
import { MaterialIcon } from "@/components/MaterialIcon";
import { STATUS_TONE } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { ApiError, api, type GpsChecksResponse, type GpsLockRow } from "@/lib/api";
import { cx } from "@/lib/cx";

/** Escape API text before it goes into a Leaflet popup, which renders as HTML. */
function escapeHtml(value: string): string {
    return value.replace(
        /[&<>"']/g,
        (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[char] ?? char
    );
}

/**
 * Validasi GPS.
 *
 * Two lists on one screen: the verdicts the server refused or flagged, and the
 * review requests couriers raised from the field. Approving a request is what
 * unlocks that action for that shipment; the incident stays on record either way.
 */
export default function GpsChecks() {
    const [tab, setTab] = useState<"pending" | "resolved">("pending");
    const [level, setLevel] = useState("");
    const [data, setData] = useState<GpsChecksResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const [decideTarget, setDecideTarget] = useState<GpsLockRow | null>(null);
    const [decision, setDecision] = useState<"APPROVED" | "REJECTED">("APPROVED");
    const [reason, setReason] = useState("");
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);

    const requestId = useRef(0);

    const load = useCallback(async () => {
        const id = requestId.current + 1;
        requestId.current = id;
        setLoading(true);

        try {
            const response = await api.gps.checks({ status: tab, level });

            if (requestId.current !== id) return;
            setData(response);
            setError(null);
        } catch (caught) {
            if (requestId.current !== id) return;
            setError(caught instanceof ApiError ? caught.message : "Data validasi GPS gagal dimuat.");
        } finally {
            if (requestId.current === id) setLoading(false);
        }
    }, [tab, level]);

    useEffect(() => {
        void load();
    }, [load]);

    function openDecision(lock: GpsLockRow, next: "APPROVED" | "REJECTED") {
        setDecideTarget(lock);
        setDecision(next);
        setReason("");
        setFormError(null);
    }

    async function submitDecision(event: FormEvent) {
        event.preventDefault();

        if (!decideTarget) return;

        if (reason.trim().length < 10) {
            setFormError("Alasan keputusan minimal 10 karakter supaya bisa diaudit.");
            return;
        }

        setSaving(true);
        setFormError(null);

        try {
            const response = await api.gps.decide(decideTarget.id, {
                decision,
                decision_reason: reason.trim()
            });
            setDecideTarget(null);
            setNotice(response.message);
            await load();
        } catch (caught) {
            setFormError(caught instanceof ApiError ? caught.message : "Keputusan gagal disimpan.");
        } finally {
            setSaving(false);
        }
    }

    const summary = data?.summary;
    const checks = data?.checks ?? [];
    const locks = data?.locks ?? [];

    // Every verdict that carried a device fix, plotted with the same tone the
    // table uses. The geofence comes from the most recent check that recorded a
    // destination and a radius.
    const mapMarkers: MapMarker[] = checks
        .filter((check) => check.latitude !== null && check.longitude !== null)
        .map((check) => {
            const distance =
                check.distance_m === null ? "" : `<br/>${check.distance_m.toLocaleString("id-ID")} m dari penerima`;

            return {
                lat: check.latitude as number,
                lng: check.longitude as number,
                tone: check.tone,
                popup: `<strong>${escapeHtml(check.tracking)}</strong><br/>${escapeHtml(check.courier)} · ${escapeHtml(
                    check.action_label ?? "-"
                )}<br/>${escapeHtml(check.level_label ?? "-")}${distance}`
            };
        });

    const fenceCheck = checks.find(
        (check) =>
            check.destination_latitude !== null && check.destination_longitude !== null && check.radius_m !== null
    );
    const mapCircle: MapCircle | null = fenceCheck
        ? {
              lat: fenceCheck.destination_latitude as number,
              lng: fenceCheck.destination_longitude as number,
              radiusM: fenceCheck.radius_m as number
          }
        : null;

    const kpis = [
        {
            label: "Menunggu tinjauan",
            value: summary?.pending_requests ?? 0,
            icon: "pending_actions",
            tone: "warning" as const
        },
        { label: "Diblokir hari ini", value: summary?.blocked_today ?? 0, icon: "block", tone: "danger" as const },
        {
            label: "Perlu ditinjau hari ini",
            value: summary?.suspect_today ?? 0,
            icon: "visibility",
            tone: "info" as const
        },
        {
            label: "Di luar radius hari ini",
            value: summary?.outside_radius_today ?? 0,
            icon: "wrong_location",
            tone: "danger" as const
        }
    ];

    return (
        <AdminLayout>
            <Head title="Validasi GPS" />

            <div className="flex flex-col justify-between gap-space-md pb-space-lg md:flex-row md:items-center">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Validasi GPS</h1>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        Bukti lokasi kurir, radius titik penerima, dan tinjauan yang menunggu keputusan hub
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="flex rounded-lg border border-outline-variant bg-surface-container-lowest p-0.5">
                        {(["pending", "resolved"] as const).map((value) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setTab(value)}
                                className={cx(
                                    "rounded-md px-3 py-1.5 font-label-md text-label-md transition-colors",
                                    tab === value
                                        ? "bg-primary text-on-primary"
                                        : "text-secondary hover:text-on-surface"
                                )}
                            >
                                {value === "pending" ? "Menunggu Tinjauan" : "Sudah Diputuskan"}
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={() => void load()}
                        className="flex h-9 w-9 items-center justify-center rounded-md border border-outline-variant text-on-surface transition-colors hover:bg-surface-container"
                        aria-label="Muat ulang"
                    >
                        <MaterialIcon name="refresh" className="text-[18px]" />
                    </button>
                </div>
            </div>

            {notice ? (
                <div className="mb-space-md flex items-center justify-between gap-space-sm rounded-lg bg-tertiary-fixed p-space-md font-body-sm text-body-sm text-on-tertiary-fixed">
                    <span>{notice}</span>
                    <button
                        type="button"
                        onClick={() => setNotice(null)}
                        className="shrink-0 rounded-md px-2 py-1 font-label-sm text-label-sm"
                    >
                        Tutup
                    </button>
                </div>
            ) : null}

            {error ? (
                <div
                    role="alert"
                    className="mb-space-md flex items-center justify-between gap-space-sm rounded-lg bg-error-container p-space-md font-body-sm text-body-sm text-on-error-container"
                >
                    <span>{error}</span>
                    <button
                        type="button"
                        onClick={() => void load()}
                        className="shrink-0 rounded-md border border-on-error-container/30 px-2 py-1 font-label-sm text-label-sm"
                    >
                        Coba lagi
                    </button>
                </div>
            ) : null}

            <div className="grid gap-space-md pb-space-lg sm:grid-cols-2 lg:grid-cols-4">
                {kpis.map((kpi) => (
                    <div
                        key={kpi.label}
                        className="flex flex-col gap-1 rounded-lg border border-outline-variant bg-surface-container-lowest p-space-md"
                    >
                        <span className="flex items-center gap-1.5 font-label-sm text-label-sm text-secondary uppercase">
                            <MaterialIcon name={kpi.icon} className={cx("text-[16px]", STATUS_TONE[kpi.tone].text)} />
                            {kpi.label}
                        </span>
                        <span className="font-headline-lg text-headline-lg text-on-surface">{kpi.value}</span>
                    </div>
                ))}
            </div>

            <section className="mb-space-xl flex flex-col gap-space-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="font-headline-sm text-headline-sm text-on-surface">Peta Pemeriksaan Lokasi</h2>
                    <div className="flex items-center gap-3 font-label-sm text-label-sm text-secondary">
                        <span className="flex items-center gap-1.5">
                            <span className="h-2.5 w-2.5 rounded-full border border-white bg-error" />
                            Di luar radius
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="h-2.5 w-2.5 rounded-full border border-white bg-tertiary-container" />
                            Normal
                        </span>
                    </div>
                </div>
                <LeafletMap
                    markers={mapMarkers}
                    circle={mapCircle}
                    emptyLabel="Belum ada pemeriksaan lokasi yang membawa koordinat."
                />
            </section>

            <section className="mb-space-xl flex flex-col gap-space-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="font-headline-sm text-headline-sm text-on-surface">Permintaan Tinjauan</h2>
                    <div className="relative">
                        <select
                            value={level}
                            onChange={(event) => setLevel(event.target.value)}
                            className="h-9 cursor-pointer appearance-none rounded-md border border-outline-variant bg-surface-container-low pr-8 pl-space-md font-body-sm text-body-sm text-on-surface hover:border-secondary focus:border-primary focus:outline-none"
                            aria-label="Filter tingkat temuan"
                        >
                            <option value="">Semua tingkat temuan</option>
                            <option value="BLOCKED">Diblokir</option>
                            <option value="SUSPECTED">Perlu ditinjau</option>
                        </select>
                        <MaterialIcon
                            name="expand_more"
                            className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-[18px] text-secondary"
                        />
                    </div>
                </div>

                {loading && data === null ? (
                    <p className="font-body-sm text-body-sm text-secondary">Memuat data…</p>
                ) : locks.length === 0 ? (
                    <p className="rounded-lg border border-outline-variant bg-surface-container-lowest p-space-lg font-body-sm text-body-sm text-secondary">
                        {tab === "pending"
                            ? "Tidak ada permintaan tinjauan yang menunggu."
                            : "Belum ada permintaan tinjauan yang diputuskan."}
                    </p>
                ) : (
                    <div className="flex flex-col gap-space-sm">
                        {locks.map((lock) => (
                            <article
                                key={lock.id}
                                className="flex flex-col gap-space-sm rounded-lg border border-outline-variant bg-surface-container-lowest p-space-md"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="flex flex-col gap-0.5">
                                        <div className="flex items-center gap-2">
                                            <span className="font-data-mono font-semibold text-body-sm text-on-surface">
                                                {lock.tracking}
                                            </span>
                                            <span
                                                className={cx(
                                                    "rounded-full px-2 py-0.5 font-label-sm text-label-sm",
                                                    lock.status === "PENDING" &&
                                                        "bg-secondary-container text-on-secondary-container",
                                                    lock.status === "APPROVED" &&
                                                        "bg-tertiary-fixed text-on-tertiary-fixed",
                                                    lock.status === "REJECTED" &&
                                                        "bg-error-container text-on-error-container"
                                                )}
                                            >
                                                {lock.status_label}
                                            </span>
                                        </div>
                                        <span className="font-body-sm text-body-sm text-secondary">
                                            {lock.buyer} • {lock.courier} • {lock.action_label}
                                        </span>
                                    </div>
                                    {lock.status === "PENDING" ? (
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => openDecision(lock, "APPROVED")}
                                                className="flex h-9 items-center gap-1.5 rounded-md bg-primary px-space-md font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                                            >
                                                <MaterialIcon name="check" className="text-[16px]" />
                                                Setujui
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => openDecision(lock, "REJECTED")}
                                                className="flex h-9 items-center gap-1.5 rounded-md border border-outline-variant px-space-md font-label-md text-label-md text-on-surface transition-colors hover:border-error hover:text-error"
                                            >
                                                <MaterialIcon name="close" className="text-[16px]" />
                                                Tolak
                                            </button>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-end gap-0.5">
                                            <span className="font-label-sm text-label-sm text-secondary">
                                                Diputuskan oleh {lock.decided_by}
                                            </span>
                                            <span className="max-w-sm text-right font-body-sm text-body-sm text-on-surface">
                                                {lock.decision_reason}
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <ul className="flex list-none flex-col gap-1 p-0">
                                    {lock.reasons.map((item) => (
                                        <li
                                            key={item.code + item.detail}
                                            className="flex items-start gap-2 font-body-sm text-body-sm text-secondary"
                                        >
                                            <MaterialIcon
                                                name={item.severity === "strong" ? "error" : "warning"}
                                                className={cx(
                                                    "mt-0.5 text-[15px]",
                                                    item.severity === "strong" ? "text-error" : "text-secondary"
                                                )}
                                            />
                                            <span>
                                                <span className="font-medium text-on-surface">{item.label}</span> —{" "}
                                                {item.detail}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </article>
                        ))}
                    </div>
                )}
            </section>

            <section className="flex flex-col gap-space-sm">
                <h2 className="font-headline-sm text-headline-sm text-on-surface">Riwayat Pemeriksaan Lokasi</h2>

                {checks.length === 0 ? (
                    <p className="rounded-lg border border-outline-variant bg-surface-container-lowest p-space-lg font-body-sm text-body-sm text-secondary">
                        Belum ada pemeriksaan lokasi yang perlu ditinjau.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-outline-variant bg-surface-container-lowest">
                        <table className="w-full border-collapse text-left">
                            <thead>
                                <tr className="border-black/5 border-b bg-surface-container-low">
                                    <th className="px-space-lg py-2 font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                        Resi
                                    </th>
                                    <th className="px-space-lg py-2 font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                        Kurir
                                    </th>
                                    <th className="px-space-lg py-2 font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                        Aksi
                                    </th>
                                    <th className="px-space-lg py-2 font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                        Temuan
                                    </th>
                                    <th className="px-space-lg py-2 font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                        Jarak / Radius
                                    </th>
                                    <th className="px-space-lg py-2 font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                        Hasil
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {checks.map((check) => (
                                    <tr
                                        key={check.id}
                                        className="border-black/5 border-b align-top last:border-b-0 hover:bg-surface-container-low"
                                    >
                                        <td className="px-space-lg py-2">
                                            <span className="font-data-mono font-semibold text-body-sm text-on-surface">
                                                {check.tracking}
                                            </span>
                                            <div className="font-label-sm text-label-sm text-secondary">
                                                {check.buyer}
                                            </div>
                                        </td>
                                        <td className="px-space-lg py-2 font-body-sm text-body-sm text-on-surface">
                                            {check.courier}
                                        </td>
                                        <td className="px-space-lg py-2 font-body-sm text-body-sm text-on-surface">
                                            {check.action_label}
                                        </td>
                                        <td className="max-w-md px-space-lg py-2">
                                            <div className="flex items-center gap-1.5">
                                                <span
                                                    className={cx("h-2 w-2 rounded-full", STATUS_TONE[check.tone].dot)}
                                                />
                                                <span
                                                    className={cx(
                                                        "font-body-sm text-body-sm",
                                                        STATUS_TONE[check.tone].text
                                                    )}
                                                >
                                                    {check.level_label}
                                                </span>
                                                {check.inside === false ? (
                                                    <span className="rounded bg-error-container px-1.5 py-0.5 font-label-sm text-label-sm text-on-error-container">
                                                        Di luar radius
                                                    </span>
                                                ) : null}
                                            </div>
                                            <ul className="mt-1 flex list-none flex-col gap-0.5 p-0">
                                                {check.reasons.slice(0, 2).map((item) => (
                                                    <li
                                                        key={item.code + item.detail}
                                                        className="font-label-sm text-label-sm text-secondary"
                                                    >
                                                        {item.label} — {item.detail}
                                                    </li>
                                                ))}
                                            </ul>
                                        </td>
                                        <td className="whitespace-nowrap px-space-lg py-2 font-body-sm text-body-sm text-on-surface">
                                            {check.distance_m === null
                                                ? "-"
                                                : check.distance_m.toLocaleString("id-ID") + " m"}
                                            <span className="text-secondary">
                                                {" / "}
                                                {check.radius_m === null
                                                    ? "-"
                                                    : check.radius_m.toLocaleString("id-ID") + " m"}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-space-lg py-2">
                                            <span
                                                className={cx(
                                                    "rounded-full px-2 py-0.5 font-label-sm text-label-sm",
                                                    check.applied
                                                        ? "bg-secondary-container text-on-secondary-container"
                                                        : "bg-error-container text-on-error-container"
                                                )}
                                            >
                                                {check.applied ? "Dilanjutkan" : "Ditahan"}
                                            </span>
                                            {check.override_status === "APPROVED" ? (
                                                <div className="mt-1 font-label-sm text-label-sm text-tertiary">
                                                    Disetujui hub
                                                </div>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {decideTarget ? (
                <Dialog onClose={() => setDecideTarget(null)} label="Keputusan tinjauan GPS">
                    <form onSubmit={submitDecision} className="flex flex-col gap-space-md">
                        <div>
                            <h2 className="font-headline-sm text-headline-sm text-on-surface">
                                {decision === "APPROVED" ? "Setujui tinjauan GPS" : "Tolak tinjauan GPS"}
                            </h2>
                            <p className="mt-1 font-body-sm text-body-sm text-secondary">
                                {decideTarget.tracking} • {decideTarget.courier} • {decideTarget.action_label}
                            </p>
                        </div>

                        <p
                            className={cx(
                                "rounded-md p-space-sm font-body-sm text-body-sm",
                                decision === "APPROVED"
                                    ? "bg-tertiary-fixed text-on-tertiary-fixed"
                                    : "bg-error-container text-on-error-container"
                            )}
                        >
                            {decision === "APPROVED"
                                ? "Kurir dapat mengulang aksi ini. Insiden tetap tercatat di riwayat validasi."
                                : "Kurir tetap tidak bisa mengulang aksi ini sampai ada bukti lain."}
                        </p>

                        <label className="flex flex-col gap-1.5">
                            <span className="font-label-sm text-label-sm text-secondary uppercase">
                                Alasan keputusan
                            </span>
                            <textarea
                                value={reason}
                                onChange={(event) => setReason(event.target.value)}
                                rows={4}
                                maxLength={1000}
                                placeholder="Contoh: petugas hub memverifikasi kurir memang berada di lokasi penerima."
                                className="w-full rounded-md border border-outline-variant bg-surface-container-low p-space-sm font-body-sm text-body-sm text-on-surface transition-colors placeholder:text-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                            />
                        </label>

                        {formError ? (
                            <p role="alert" className="font-body-sm text-body-sm text-error">
                                {formError}
                            </p>
                        ) : null}

                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setDecideTarget(null)}
                                className="h-9 rounded-md px-space-lg font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={saving}
                                className={cx(
                                    "h-9 rounded-md px-space-lg font-label-md text-label-md text-on-primary transition-colors disabled:opacity-60",
                                    decision === "APPROVED"
                                        ? "bg-primary hover:bg-primary-container"
                                        : "bg-error hover:opacity-90"
                                )}
                            >
                                {saving ? "Menyimpan…" : decision === "APPROVED" ? "Setujui" : "Tolak"}
                            </button>
                        </div>
                    </form>
                </Dialog>
            ) : null}
        </AdminLayout>
    );
}
