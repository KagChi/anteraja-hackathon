import { Head, router } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ShipmentDetailModal } from "@/components/ShipmentDetailModal";
import { ISSUE_CATEGORY_LABEL, type IssueCategoryKey, STATUS_TONE } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { ApiError, api, type ReportResponse } from "@/lib/api";
import { cx } from "@/lib/cx";
import { todayIso } from "@/lib/date";

interface MetricCardProps {
    label: string;
    icon: string;
    value: string;
    unit: string;
    footerLabel: string;
    footerValue: string;
    accent?: boolean;
    /** Opens the shipment queue filtered to this metric. */
    onOpen: () => void;
}

function MetricCard({ label, icon, value, unit, footerLabel, footerValue, accent = false, onOpen }: MetricCardProps) {
    return (
        <button
            type="button"
            onClick={onOpen}
            title="Buka daftar pengiriman"
            className="group flex flex-col justify-between rounded-lg border border-outline-variant bg-surface-container-lowest p-space-lg text-left transition-colors hover:border-secondary hover:bg-surface-container-low"
        >
            <span className="flex w-full items-center justify-between">
                <span className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">{label}</span>
                <MaterialIcon name={icon} className={cx("text-[18px]", accent ? "text-primary" : "text-secondary")} />
            </span>
            <span className="mt-space-md flex items-baseline gap-space-xs">
                <span className={cx("font-headline-lg text-headline-lg", accent ? "text-primary" : "text-on-surface")}>
                    {value}
                </span>
                <span className="font-body-sm text-body-sm text-secondary">{unit}</span>
            </span>
            <span className="mt-space-sm flex w-full items-center justify-between border-outline-variant border-t pt-space-xs">
                <span className="font-label-sm text-label-sm text-secondary">{footerLabel}</span>
                <span
                    className={cx(
                        "font-data-mono text-data-mono",
                        accent ? "font-semibold text-primary" : "text-on-surface"
                    )}
                >
                    {footerValue}
                </span>
            </span>
            <span className="mt-space-sm flex items-center gap-1 font-label-sm text-label-sm text-primary">
                <span>Lihat daftar pengiriman</span>
                <MaterialIcon
                    name="arrow_forward"
                    className="text-[14px] transition-transform group-hover:translate-x-0.5"
                />
            </span>
        </button>
    );
}

/**
 * Daily operations report.
 *
 * KPIs, the kendala table and the pagination all come from
 * GET /api/admin/reports, so the numbers on the cards and the rows underneath
 * are computed once, on the server.
 */
export default function Report() {
    const [date, setDate] = useState(todayIso());
    const [category, setCategory] = useState("");
    const [page, setPage] = useState(1);
    const [detailResi, setDetailResi] = useState<string | null>(null);

    const [data, setData] = useState<ReportResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const requestId = useRef(0);

    const load = useCallback(async () => {
        const id = requestId.current + 1;
        requestId.current = id;
        setLoading(true);

        try {
            const response = await api.reports.index({ date, category, page });

            if (requestId.current !== id) return;
            setData(response);
            setError(null);
        } catch (caught) {
            if (requestId.current !== id) return;
            setError(caught instanceof ApiError ? caught.message : "Laporan gagal dimuat.");
        } finally {
            if (requestId.current === id) setLoading(false);
        }
    }, [date, category, page]);

    useEffect(() => {
        void load();
    }, [load]);

    function openShipments(statusFilter?: string) {
        const params = new URLSearchParams({ date });
        if (statusFilter) params.set("status", statusFilter);
        router.visit(`/admin/shipment?${params.toString()}`);
    }

    const kpis = data?.kpis;
    const rows = data?.issues ?? [];
    const paginator = data?.paginator;
    const pageCount = paginator?.last_page ?? 1;
    const currentPage = paginator?.current_page ?? 1;
    const total = paginator?.total ?? 0;
    const rangeLabel = total === 0 ? "0" : `${paginator?.from ?? 1}-${paginator?.to ?? 0}`;
    const categories = data?.categoriesOnDate ?? [];

    return (
        <AdminLayout>
            <Head title="Laporan" />

            <div className="flex flex-col justify-between gap-space-md pb-space-lg md:flex-row md:items-center">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Laporan</h1>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        Kinerja harian dan penanganan kendala cold-chain
                    </p>
                </div>
                <div className="flex h-9 items-center gap-space-xs self-start rounded-md border border-outline-variant bg-surface-container-low px-space-md md:self-center">
                    <MaterialIcon name="calendar_today" className="text-[16px] text-secondary" />
                    <span className="font-body-sm text-body-sm text-secondary">Tanggal</span>
                    <input
                        type="date"
                        value={date}
                        max={todayIso()}
                        aria-label="Tanggal laporan"
                        onChange={(event) => {
                            setDate(event.target.value === "" ? todayIso() : event.target.value);
                            setCategory("");
                            setPage(1);
                        }}
                        className="cursor-pointer bg-transparent font-body-sm text-body-sm text-on-surface focus:outline-none"
                    />
                </div>
            </div>

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

            <div className="grid grid-cols-1 gap-space-md md:grid-cols-3">
                <MetricCard
                    label="Total Pengiriman"
                    icon="inventory_2"
                    value={(kpis?.total_shipments ?? 0).toLocaleString("id-ID")}
                    unit="resi"
                    footerLabel="Kapasitas Armada Cold-Chain"
                    footerValue={`${kpis?.fleet_capacity_pct ?? 0}%`}
                    onOpen={() => openShipments()}
                />
                <MetricCard
                    label="Pengiriman Berhasil Diterima"
                    icon="check_circle"
                    value={(kpis?.delivered_count ?? 0).toLocaleString("id-ID")}
                    unit="resi"
                    footerLabel="Rasio Keberhasilan"
                    footerValue={`${kpis?.success_rate_pct ?? 0}%`}
                    onOpen={() => openShipments("RECEIVED")}
                />
                <MetricCard
                    label="Total Kasus Kendala Dilaporkan"
                    icon="report_problem"
                    value={(kpis?.issue_count ?? 0).toLocaleString("id-ID")}
                    unit="kasus"
                    footerLabel="Tingkat Insiden"
                    footerValue={`${kpis?.incident_rate_pct ?? 0}%`}
                    accent
                    onOpen={() => openShipments("ISSUE_REPORTED")}
                />
            </div>

            <div className="mt-space-lg flex w-full flex-col overflow-hidden rounded-lg bg-surface-container-lowest">
                <div className="flex flex-col justify-between gap-space-sm p-space-lg pb-space-md sm:flex-row sm:items-end">
                    <div className="flex flex-col gap-space-xs">
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Daftar Kendala</h2>
                        <p className="font-body-sm text-body-sm text-secondary">
                            {data?.dateLabel ?? date} · pilih detail untuk membuka penanganan dan keputusan dispatcher
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-space-sm">
                        <div className="relative w-full sm:w-auto">
                            <select
                                value={category}
                                aria-label="Filter jenis kendala"
                                onChange={(event) => {
                                    setCategory(event.target.value);
                                    setPage(1);
                                }}
                                className="h-9 w-full cursor-pointer appearance-none rounded-md border border-outline-variant bg-surface-container-low pr-8 pl-space-md font-body-sm text-body-sm text-on-surface transition-colors hover:border-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none sm:w-auto"
                            >
                                <option value="">Semua Jenis Kendala</option>
                                {categories.map((key) => (
                                    <option key={key} value={key}>
                                        {ISSUE_CATEGORY_LABEL[key as IssueCategoryKey] ?? key}
                                    </option>
                                ))}
                            </select>
                            <MaterialIcon
                                name="expand_more"
                                className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-[18px] text-secondary"
                            />
                        </div>
                    </div>
                </div>
                <div
                    className={cx(
                        "divide-y divide-surface-container md:hidden",
                        loading && "opacity-60 transition-opacity"
                    )}
                >
                    {rows.map((row) => (
                        <button
                            key={row.id}
                            type="button"
                            onClick={() => setDetailResi(row.tracking)}
                            className="flex w-full flex-col gap-space-sm p-space-lg text-left transition-colors hover:bg-surface-container-low"
                        >
                            <div className="flex items-start justify-between gap-space-sm">
                                <div className="flex min-w-0 flex-col">
                                    <span className="font-data-mono font-semibold text-body-md text-on-surface">
                                        {row.tracking}
                                    </span>
                                    <span className="mt-0.5 truncate font-body-sm text-body-sm text-secondary">
                                        {row.buyer}
                                    </span>
                                </div>
                                <div className="flex shrink-0 items-center gap-1.5">
                                    <span
                                        className={cx(
                                            "h-2 w-2 shrink-0 rounded-full",
                                            STATUS_TONE[row.resolutionTone].dot
                                        )}
                                    />
                                    <span
                                        className={cx(
                                            "font-label-sm text-label-sm",
                                            STATUS_TONE[row.resolutionTone].text
                                        )}
                                    >
                                        {row.resolutionStatus}
                                    </span>
                                </div>
                            </div>
                            <dl className="grid grid-cols-2 gap-x-space-md gap-y-space-sm">
                                <div className="flex flex-col gap-0.5">
                                    <dt className="font-label-sm text-label-sm text-secondary uppercase tracking-wide">
                                        Jenis Kendala
                                    </dt>
                                    <dd
                                        className={cx(
                                            "font-body-sm text-body-sm",
                                            row.resolutionStatus === "Menunggu Keputusan"
                                                ? "font-medium text-primary"
                                                : "text-on-surface"
                                        )}
                                    >
                                        {row.category}
                                    </dd>
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <dt className="font-label-sm text-label-sm text-secondary uppercase tracking-wide">
                                        Kurir
                                    </dt>
                                    <dd className="font-body-sm text-body-sm text-secondary">{row.courier}</dd>
                                </div>
                                <div className="col-span-2 flex flex-col gap-0.5">
                                    <dt className="font-label-sm text-label-sm text-secondary uppercase tracking-wide">
                                        Keputusan
                                    </dt>
                                    <dd className="font-body-sm text-body-sm text-secondary">{row.decision}</dd>
                                </div>
                            </dl>
                        </button>
                    ))}
                    {!loading && rows.length === 0 ? (
                        <p className="px-space-lg py-space-xl text-center font-body-sm text-body-sm text-secondary">
                            Tidak ada kendala pada tanggal ini.
                        </p>
                    ) : null}
                </div>
                <div className="hidden w-full overflow-x-auto md:block">
                    <table className="w-full border-collapse text-left">
                        <thead>
                            <tr className="h-9 bg-surface-container-low">
                                {["Resi", "Pembeli", "Kurir", "Jenis Kendala", "Keputusan", "Status"].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider"
                                    >
                                        {heading}
                                    </th>
                                ))}
                                <th className="px-space-lg text-right font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            className={cx(
                                "divide-y divide-surface-container",
                                loading && "opacity-60 transition-opacity"
                            )}
                        >
                            {rows.map((row) => (
                                <tr key={row.id} className="h-10 transition-colors hover:bg-surface-container-low">
                                    <td className="whitespace-nowrap px-space-lg">
                                        <span className="font-data-mono font-semibold text-body-sm text-on-surface">
                                            {row.tracking}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-on-surface">
                                        {row.buyer}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-secondary">
                                        {row.courier}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        <span
                                            className={cx(
                                                "font-body-sm text-body-sm",
                                                row.resolutionStatus === "Menunggu Keputusan"
                                                    ? "font-medium text-primary"
                                                    : "text-on-surface"
                                            )}
                                        >
                                            {row.category}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-secondary">
                                        {row.decision}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        <div className="flex items-center gap-1.5">
                                            <span
                                                className={cx(
                                                    "h-2 w-2 shrink-0 rounded-full",
                                                    STATUS_TONE[row.resolutionTone].dot
                                                )}
                                            />
                                            <span
                                                className={cx(
                                                    "font-body-sm text-body-sm",
                                                    STATUS_TONE[row.resolutionTone].text
                                                )}
                                            >
                                                {row.resolutionStatus}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg text-right">
                                        <button
                                            type="button"
                                            onClick={() => setDetailResi(row.tracking)}
                                            aria-label={`Lihat detail ${row.tracking}`}
                                            title="Lihat detail"
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-secondary transition-colors hover:bg-surface-container hover:text-primary"
                                        >
                                            <MaterialIcon name="visibility" className="text-[18px]" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {!loading && rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-space-lg py-space-xl text-center font-body-sm text-body-sm text-secondary"
                                    >
                                        Tidak ada kendala pada tanggal ini.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
                <div className="flex min-h-12 flex-wrap items-center justify-between gap-space-sm px-space-lg py-space-sm">
                    <span className="font-body-sm text-body-sm text-secondary">
                        Menampilkan {rangeLabel} dari {total} kendala
                    </span>
                    <div className="flex items-center gap-space-xs">
                        <button
                            type="button"
                            onClick={() => setPage(currentPage - 1)}
                            disabled={currentPage <= 1}
                            className="h-8 rounded-sm bg-surface-container-low px-space-sm font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface disabled:opacity-40"
                        >
                            Sebelumnya
                        </button>
                        <span className="flex h-8 items-center px-space-sm font-label-md text-label-md text-secondary">
                            {currentPage} / {pageCount}
                        </span>
                        <button
                            type="button"
                            onClick={() => setPage(currentPage + 1)}
                            disabled={currentPage >= pageCount}
                            className="h-8 rounded-sm bg-surface-container-low px-space-sm font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface disabled:opacity-40"
                        >
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>

            {detailResi ? (
                <ShipmentDetailModal
                    key={detailResi}
                    resi={detailResi}
                    onClose={() => setDetailResi(null)}
                    onChanged={() => void load()}
                />
            ) : null}
        </AdminLayout>
    );
}
