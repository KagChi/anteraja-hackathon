import { Head, router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ShipmentDetailModal } from "@/components/ShipmentDetailModal";
import {
    DAILY_FLEET_CAPACITY,
    ISSUE_CATEGORY_LABEL,
    ISSUE_DECISIONS,
    type IssueCategoryKey,
    type IssueDecisionKey,
    issueCategoriesOn,
    RESOLUTION_TONE,
    type ResolutionStatus,
    reportDates,
    reportIssues,
    reportKpis,
    STATUS_TONE
} from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { cx } from "@/lib/cx";
import { formatLongDate, todayIso } from "@/lib/date";
import { downloadCsv } from "@/lib/download";

const PAGE_SIZE = 5;

/** Today when it has activity, otherwise the newest delivery day in the data. */
function defaultReportDate(): string {
    const today = todayIso();
    const dates = reportDates();
    if (dates.includes(today)) return today;
    return dates[dates.length - 1] ?? today;
}

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

export default function Report() {
    const [date, setDate] = useState(defaultReportDate);
    const [category, setCategory] = useState<IssueCategoryKey | "">("");
    const [page, setPage] = useState(1);
    const [detailResi, setDetailResi] = useState<string | null>(null);
    const [decisions, setDecisions] = useState<
        Record<string, { decision: IssueDecisionKey | null; resolution: ResolutionStatus }>
    >({});

    const kpis = useMemo(() => reportKpis(date), [date]);
    const categories = useMemo(() => issueCategoriesOn(date), [date]);
    const rows = useMemo(
        () =>
            reportIssues(date, category).map((row) => {
                const update = decisions[row.tracking];
                if (!update || !row.issue) return row;
                return { ...row, issue: { ...row.issue, decision: update.decision, resolution: update.resolution } };
            }),
        [date, category, decisions]
    );

    const pageCount = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
    const currentPage = Math.min(page, pageCount);
    const first = (currentPage - 1) * PAGE_SIZE;
    const pageRows = rows.slice(first, first + PAGE_SIZE);
    const rangeLabel = rows.length === 0 ? "0" : `${first + 1}-${Math.min(first + PAGE_SIZE, rows.length)}`;

    function openShipments(statusFilter?: string) {
        const params = new URLSearchParams({ date });
        if (statusFilter) params.set("status", statusFilter);
        router.visit(`/admin/shipment?${params.toString()}`);
    }

    function exportCsv() {
        const header = ["RESI", "PEMBELI", "KURIR", "JENIS_KENDALA", "KEPUTUSAN", "STATUS_RESOLUSI", "DILAPORKAN"];
        const body = rows.map((row) => [
            row.tracking,
            row.buyer,
            row.courier ?? "-",
            row.issue?.label ?? "-",
            row.issue?.decision ? ISSUE_DECISIONS[row.issue.decision].label : "Menunggu Keputusan",
            row.issue?.resolution ?? "-",
            row.issue?.reportedAt ?? "-"
        ]);
        downloadCsv(`laporan-kendala-${date}.csv`, [header, ...body]);
    }

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
                        aria-label="Tanggal laporan"
                        onChange={(event) => {
                            setDate(event.target.value);
                            setCategory("");
                            setPage(1);
                        }}
                        className="cursor-pointer bg-transparent font-body-sm text-body-sm text-on-surface focus:outline-none"
                    />
                </div>
            </div>

            <div className="grid grid-cols-1 gap-space-md md:grid-cols-3">
                <MetricCard
                    label="Total Pengiriman"
                    icon="inventory_2"
                    value={kpis.total.toLocaleString("id-ID")}
                    unit="resi"
                    footerLabel="Kapasitas Armada Cold-Chain"
                    footerValue={`${kpis.total} / ${DAILY_FLEET_CAPACITY.toLocaleString("id-ID")}`}
                    onOpen={() => openShipments()}
                />
                <MetricCard
                    label="Pengiriman Berhasil Diterima"
                    icon="check_circle"
                    value={kpis.delivered.toLocaleString("id-ID")}
                    unit="resi"
                    footerLabel="Rasio Keberhasilan"
                    footerValue={`${kpis.successRatePct}%`}
                    onOpen={() => openShipments("delivered")}
                />
                <MetricCard
                    label="Total Kasus Kendala Dilaporkan"
                    icon="report_problem"
                    value={kpis.issues.toLocaleString("id-ID")}
                    unit="kasus"
                    footerLabel="Tingkat Insiden"
                    footerValue={`${kpis.incidentRatePct}%`}
                    accent
                    onOpen={() => openShipments("issue")}
                />
            </div>

            <div className="mt-space-lg flex w-full flex-col overflow-hidden rounded-lg bg-surface-container-lowest">
                <div className="flex flex-col justify-between gap-space-sm p-space-lg pb-space-md sm:flex-row sm:items-end">
                    <div className="flex flex-col gap-space-xs">
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Daftar Kendala</h2>
                        <p className="font-body-sm text-body-sm text-secondary">
                            {formatLongDate(date)} · pilih detail untuk membuka penanganan dan keputusan dispatcher
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-space-sm">
                        <div className="relative">
                            <select
                                value={category}
                                aria-label="Filter jenis kendala"
                                onChange={(event) => {
                                    setCategory(event.target.value as IssueCategoryKey | "");
                                    setPage(1);
                                }}
                                className="h-9 cursor-pointer appearance-none rounded-md border border-outline-variant bg-surface-container-low pr-8 pl-space-md font-body-sm text-body-sm text-on-surface transition-colors hover:border-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                            >
                                <option value="">Semua Jenis Kendala</option>
                                {categories.map((key) => (
                                    <option key={key} value={key}>
                                        {ISSUE_CATEGORY_LABEL[key]}
                                    </option>
                                ))}
                            </select>
                            <MaterialIcon
                                name="expand_more"
                                className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-[18px] text-secondary"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={exportCsv}
                            disabled={rows.length === 0}
                            className="flex h-9 items-center gap-space-xs rounded-md border border-outline-variant px-space-md font-label-md text-label-md text-on-surface transition-colors hover:border-secondary hover:bg-surface-container disabled:opacity-40"
                        >
                            <MaterialIcon name="download" className="text-[16px]" />
                            <span>Ekspor CSV</span>
                        </button>
                    </div>
                </div>
                <div className="w-full overflow-x-auto">
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
                        <tbody className="divide-y divide-surface-container">
                            {pageRows.map((row) => (
                                <tr
                                    key={row.tracking}
                                    className="h-10 transition-colors hover:bg-surface-container-low"
                                >
                                    <td className="whitespace-nowrap px-space-lg">
                                        <span className="font-data-mono font-semibold text-body-sm text-on-surface">
                                            {row.tracking}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-on-surface">
                                        {row.buyer}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-secondary">
                                        {row.courier ?? "-"}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        <span
                                            className={cx(
                                                "font-body-sm text-body-sm",
                                                row.issue?.resolution === "Menunggu Keputusan"
                                                    ? "font-medium text-primary"
                                                    : "text-on-surface"
                                            )}
                                        >
                                            {row.issue?.label ?? "-"}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-secondary">
                                        {row.issue?.decision
                                            ? ISSUE_DECISIONS[row.issue.decision].label
                                            : "Menunggu Keputusan"}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        <div className="flex items-center gap-1.5">
                                            <span
                                                className={cx(
                                                    "h-2 w-2 shrink-0 rounded-full",
                                                    STATUS_TONE[
                                                        row.issue ? RESOLUTION_TONE[row.issue.resolution] : "waiting"
                                                    ].dot
                                                )}
                                            />
                                            <span
                                                className={cx(
                                                    "font-body-sm text-body-sm",
                                                    STATUS_TONE[
                                                        row.issue ? RESOLUTION_TONE[row.issue.resolution] : "waiting"
                                                    ].text
                                                )}
                                            >
                                                {row.issue?.resolution ?? "-"}
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
                            {rows.length === 0 ? (
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
                <div className="flex h-12 items-center justify-between px-space-lg">
                    <span className="font-body-sm text-body-sm text-secondary">
                        Menampilkan {rangeLabel} dari {rows.length} kendala
                    </span>
                    <div className="flex items-center gap-space-xs">
                        <button
                            type="button"
                            onClick={() => setPage(currentPage - 1)}
                            disabled={currentPage === 1}
                            className="h-8 rounded-sm bg-surface-container-low px-space-sm font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface disabled:opacity-40"
                        >
                            Sebelumnya
                        </button>
                        {Array.from({ length: pageCount }, (_, index) => index + 1).map((pageNumber) => (
                            <button
                                key={pageNumber}
                                type="button"
                                onClick={() => setPage(pageNumber)}
                                aria-current={pageNumber === currentPage ? "page" : undefined}
                                className={cx(
                                    "flex h-8 w-8 items-center justify-center rounded-sm font-label-md text-label-md",
                                    pageNumber === currentPage
                                        ? "bg-primary text-on-primary"
                                        : "bg-surface-container-lowest text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                                )}
                            >
                                {pageNumber}
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={() => setPage(currentPage + 1)}
                            disabled={currentPage === pageCount}
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
                    row={rows.find((item) => item.tracking === detailResi)}
                    onClose={() => setDetailResi(null)}
                    onDecisionChange={(resi, update) => setDecisions((prev) => ({ ...prev, [resi]: { ...update } }))}
                />
            ) : null}
        </AdminLayout>
    );
}
