import { Head } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ShipmentDetailModal } from "@/components/ShipmentDetailModal";
import { STATUS_TONE } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { ApiError, api, type DashboardResponse, type MessageHistoryResponse } from "@/lib/api";
import { cx } from "@/lib/cx";
import { todayIso } from "@/lib/date";

interface StatCardProps {
    label: string;
    icon: string;
    value: string;
    unit: string;
    foot: string;
    accent?: boolean;
}

function StatCard({ label, icon, value, unit, foot, accent = false }: StatCardProps) {
    return (
        <div
            className={cx(
                "flex flex-col justify-between rounded-lg border bg-surface-container-lowest p-space-md",
                accent ? "border-primary" : "border-outline-variant"
            )}
        >
            <span className="flex w-full items-center justify-between">
                <span className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">{label}</span>
                <MaterialIcon name={icon} className={cx("text-[18px]", accent ? "text-primary" : "text-secondary")} />
            </span>
            <span className="mt-space-sm flex items-baseline gap-space-xs">
                <span className={cx("font-headline-md text-headline-md", accent ? "text-primary" : "text-on-surface")}>
                    {value}
                </span>
                <span className="font-body-sm text-body-sm text-secondary">{unit}</span>
            </span>
            <span className="mt-space-sm border-outline-variant border-t pt-space-xs font-label-sm text-label-sm text-secondary">
                {foot}
            </span>
        </div>
    );
}

function Paginator({
    page,
    pageCount,
    total,
    label,
    onPage
}: {
    page: number;
    pageCount: number;
    total: number;
    label: string;
    onPage: (page: number) => void;
}) {
    return (
        <div className="flex min-h-12 flex-wrap items-center justify-between gap-space-sm px-space-lg py-space-sm">
            <span className="font-body-sm text-body-sm text-secondary">
                {total} {label}
            </span>
            <div className="flex items-center gap-space-xs">
                <button
                    type="button"
                    onClick={() => onPage(page - 1)}
                    disabled={page <= 1}
                    className="h-8 rounded-sm bg-surface-container-low px-space-sm font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface disabled:opacity-40"
                >
                    Sebelumnya
                </button>
                <span className="flex h-8 items-center px-space-sm font-label-md text-label-md text-secondary">
                    {page} / {pageCount}
                </span>
                <button
                    type="button"
                    onClick={() => onPage(page + 1)}
                    disabled={page >= pageCount}
                    className="h-8 rounded-sm bg-surface-container-low px-space-sm font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface disabled:opacity-40"
                >
                    Selanjutnya
                </button>
            </div>
        </div>
    );
}

/**
 * Beranda admin.
 *
 * The auto summary and the audit trail come from GET /api/admin/dashboard; the
 * WhatsApp tab reuses GET /api/admin/messages, so the message log that used to
 * live only behind the API now has a screen. Everything here is read-only.
 */
export default function Dashboard() {
    const [date, setDate] = useState(todayIso());
    const [page, setPage] = useState(1);
    const [q, setQ] = useState("");
    const [tab, setTab] = useState<"activity" | "messages">("activity");

    const [data, setData] = useState<DashboardResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [detailResi, setDetailResi] = useState<string | null>(null);

    const [messages, setMessages] = useState<MessageHistoryResponse | null>(null);
    const [messagesLoading, setMessagesLoading] = useState(false);
    const [messagesError, setMessagesError] = useState<string | null>(null);
    const [messagePage, setMessagePage] = useState(1);
    const [messageQ, setMessageQ] = useState("");
    const [direction, setDirection] = useState<"" | "IN" | "OUT">("");

    const requestId = useRef(0);

    const load = useCallback(
        async (options?: { silent?: boolean }) => {
            const id = requestId.current + 1;
            requestId.current = id;
            if (!options?.silent) setLoading(true);

            try {
                const response = await api.dashboard.index({ date, q, page });
                if (requestId.current !== id) return;
                setData(response);
                setError(null);
            } catch (caught) {
                if (requestId.current !== id) return;
                setError(caught instanceof ApiError ? caught.message : "Ringkasan gagal dimuat.");
            } finally {
                if (requestId.current === id && !options?.silent) setLoading(false);
            }
        },
        [date, q, page]
    );

    const loadMessages = useCallback(async () => {
        setMessagesLoading(true);
        try {
            const response = await api.messages.index({
                resi: messageQ,
                direction: direction || undefined,
                page: messagePage
            });
            setMessages(response);
            setMessagesError(null);
        } catch (caught) {
            setMessagesError(caught instanceof ApiError ? caught.message : "Riwayat pesan gagal dimuat.");
        } finally {
            setMessagesLoading(false);
        }
    }, [messageQ, direction, messagePage]);

    useEffect(() => {
        void load();
    }, [load]);

    // Auto refresh: the summary is "auto" because the screen keeps itself current.
    useEffect(() => {
        const timer = window.setInterval(() => void load({ silent: true }), 60_000);
        return () => window.clearInterval(timer);
    }, [load]);

    useEffect(() => {
        if (tab === "messages") void loadMessages();
    }, [tab, loadMessages]);

    const summary = data?.summary;
    const activity = data?.activity ?? [];
    const paginator = data?.paginator;
    const pageCount = paginator?.last_page ?? 1;
    const currentPage = paginator?.current_page ?? 1;
    const messageRows = messages?.messages ?? [];
    const messagePages = messages?.paginator?.last_page ?? 1;
    const messageCurrent = messages?.paginator?.current_page ?? 1;

    function changeDate(next: string) {
        setDate(next === "" ? todayIso() : next);
        setPage(1);
        setQ("");
    }

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-space-md pb-space-lg xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Dashboard</h1>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        Ringkasan operasi otomatis dan riwayat aktivitas hub cold-chain
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-space-sm">
                    <div className="flex h-9 items-center gap-space-xs rounded-md border border-outline-variant bg-surface-container-low px-space-md">
                        <MaterialIcon name="calendar_today" className="text-[16px] text-secondary" />
                        <input
                            type="date"
                            value={date}
                            max={todayIso()}
                            aria-label="Tanggal ringkasan"
                            onChange={(event) => changeDate(event.target.value)}
                            className="cursor-pointer bg-transparent font-body-sm text-body-sm text-on-surface focus:outline-none"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => void load()}
                        aria-label="Muat ulang ringkasan"
                        title="Muat ulang"
                        className="flex h-9 w-9 items-center justify-center rounded-md border border-outline-variant bg-surface-container-low text-secondary transition-colors hover:border-secondary hover:text-on-surface"
                    >
                        <MaterialIcon name={loading ? "progress_activity" : "refresh"} className="text-[18px]" />
                    </button>
                    <a
                        href={api.dashboard.exportUrl(date)}
                        className="flex h-9 items-center gap-space-xs rounded-md bg-primary px-space-md font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        <MaterialIcon name="picture_as_pdf" className="text-[16px]" />
                        Ekspor PDF
                    </a>
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

            <div className={cx("grid grid-cols-2 gap-space-md md:grid-cols-3 xl:grid-cols-6", loading && "opacity-60")}>
                <StatCard
                    label="Total Pengiriman"
                    icon="inventory_2"
                    value={(summary?.total_shipments ?? 0).toLocaleString("id-ID")}
                    unit="resi"
                    foot={`Utilisasi armada ${summary?.fleet_capacity_pct ?? 0}%`}
                />
                <StatCard
                    label="Terkirim"
                    icon="check_circle"
                    value={(summary?.delivered_count ?? 0).toLocaleString("id-ID")}
                    unit="resi"
                    foot={`Rasio ${summary?.success_rate_pct ?? 0}%`}
                />
                <StatCard
                    label="Dalam Proses"
                    icon="local_shipping"
                    value={(summary?.in_process_count ?? 0).toLocaleString("id-ID")}
                    unit="resi"
                    foot="Belum selesai"
                />
                <StatCard
                    label="Kendala"
                    icon="report_problem"
                    value={(summary?.issue_count ?? 0).toLocaleString("id-ID")}
                    unit="kasus"
                    foot={`Insiden ${summary?.incident_rate_pct ?? 0}%`}
                    accent
                />
                <StatCard
                    label="Menunggu Keputusan"
                    icon="pending_actions"
                    value={(summary?.pending_decision_count ?? 0).toLocaleString("id-ID")}
                    unit="kasus"
                    foot={`${summary?.decided_count ?? 0} sudah diputuskan`}
                    accent
                />
                <StatCard
                    label="Dikembalikan"
                    icon="assignment_return"
                    value={(summary?.returned_count ?? 0).toLocaleString("id-ID")}
                    unit="resi"
                    foot="Ditarik ke hub"
                />
            </div>

            <div className="mt-space-lg rounded-lg border border-outline-variant border-l-2 border-l-primary bg-surface-container-lowest p-space-lg">
                <div className="flex items-center justify-between gap-space-sm">
                    <div className="flex items-center gap-space-sm">
                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-fixed">
                            <MaterialIcon name="auto_awesome" className="text-[18px] text-primary" />
                        </span>
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Ikhtisar Otomatis</h2>
                    </div>
                    <span className="flex items-center gap-space-xs font-label-sm text-label-sm text-secondary">
                        <span className="relative flex h-2 w-2">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-tertiary-container opacity-60" />
                            <span className="relative inline-flex h-2 w-2 rounded-full bg-tertiary-container" />
                        </span>
                        Diperbarui {data?.refreshedAt ?? "-"}
                    </span>
                </div>
                <p className="mt-space-sm font-body-md text-body-md text-on-surface-variant">
                    {data?.narrative ?? "Memuat ringkasan..."}
                </p>
            </div>

            <div className="mt-space-lg flex w-full flex-col overflow-hidden rounded-lg bg-surface-container-lowest">
                <div className="flex flex-col justify-between gap-space-sm p-space-lg pb-space-md sm:flex-row sm:items-end">
                    <div className="flex flex-col gap-space-xs">
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Log History</h2>
                        <p className="font-body-sm text-body-sm text-secondary">
                            Jejak audit perubahan status paket dan percakapan WhatsApp
                        </p>
                    </div>

                    {tab === "activity" ? (
                        <div className="flex items-center gap-space-xs rounded-md border border-outline-variant bg-surface-container-low px-space-md">
                            <MaterialIcon name="search" className="text-[16px] text-secondary" />
                            <input
                                type="text"
                                value={q}
                                placeholder="Cari resi"
                                aria-label="Cari resi pada aktivitas"
                                onChange={(event) => {
                                    setQ(event.target.value);
                                    setPage(1);
                                }}
                                className="h-9 bg-transparent font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none"
                            />
                        </div>
                    ) : (
                        <div className="flex flex-wrap items-center gap-space-sm">
                            <div className="flex items-center gap-space-xs rounded-md border border-outline-variant bg-surface-container-low px-space-md">
                                <MaterialIcon name="search" className="text-[16px] text-secondary" />
                                <input
                                    type="text"
                                    value={messageQ}
                                    placeholder="Cari resi"
                                    aria-label="Cari resi pada pesan"
                                    onChange={(event) => {
                                        setMessageQ(event.target.value);
                                        setMessagePage(1);
                                    }}
                                    className="h-9 bg-transparent font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none"
                                />
                            </div>
                            <select
                                value={direction}
                                aria-label="Filter arah pesan"
                                onChange={(event) => {
                                    setDirection(event.target.value as "" | "IN" | "OUT");
                                    setMessagePage(1);
                                }}
                                className="h-9 cursor-pointer rounded-md border border-outline-variant bg-surface-container-low px-space-md font-body-sm text-body-sm text-on-surface focus:outline-none"
                            >
                                <option value="">Semua arah</option>
                                <option value="IN">Masuk</option>
                                <option value="OUT">Keluar</option>
                            </select>
                        </div>
                    )}
                </div>

                <div className="flex gap-space-xs border-outline-variant border-b px-space-lg">
                    {(
                        [
                            { key: "activity", label: "Aktivitas Paket", icon: "history" },
                            { key: "messages", label: "Pesan WhatsApp", icon: "chat" }
                        ] as const
                    ).map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setTab(item.key)}
                            className={cx(
                                "-mb-px flex items-center gap-space-xs border-b-2 px-space-sm py-space-sm font-label-md text-label-md transition-colors",
                                tab === item.key
                                    ? "border-primary text-primary"
                                    : "border-transparent text-secondary hover:text-on-surface"
                            )}
                        >
                            <MaterialIcon name={item.icon} className="text-[16px]" />
                            {item.label}
                        </button>
                    ))}
                </div>

                {tab === "activity" ? (
                    <>
                        <div className="w-full overflow-x-auto">
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="h-9 bg-surface-container-low">
                                        {["Waktu", "Resi", "Pembeli", "Perubahan Status", "Pelaku", ""].map(
                                            (heading) => (
                                                <th
                                                    key={heading}
                                                    className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider"
                                                >
                                                    {heading}
                                                </th>
                                            )
                                        )}
                                    </tr>
                                </thead>
                                <tbody className={cx("divide-y divide-surface-container", loading && "opacity-60")}>
                                    {activity.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="h-10 transition-colors hover:bg-surface-container-low"
                                        >
                                            <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-secondary">
                                                {row.at}
                                            </td>
                                            <td className="whitespace-nowrap px-space-lg">
                                                <span className="font-data-mono font-semibold text-body-sm text-on-surface">
                                                    {row.tracking}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-on-surface">
                                                {row.buyer}
                                            </td>
                                            <td className="whitespace-nowrap px-space-lg">
                                                <div className="flex items-center gap-1.5">
                                                    {row.fromLabel ? (
                                                        <>
                                                            <span className="font-body-sm text-body-sm text-secondary">
                                                                {row.fromLabel}
                                                            </span>
                                                            <MaterialIcon
                                                                name="arrow_forward"
                                                                className="text-[14px] text-outline"
                                                            />
                                                        </>
                                                    ) : null}
                                                    <span
                                                        className={cx(
                                                            "h-2 w-2 shrink-0 rounded-full",
                                                            STATUS_TONE[row.tone].dot
                                                        )}
                                                    />
                                                    <span
                                                        className={cx(
                                                            "font-body-sm text-body-sm",
                                                            STATUS_TONE[row.tone].text
                                                        )}
                                                    >
                                                        {row.toLabel}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-secondary">
                                                {row.actor}
                                            </td>
                                            <td className="whitespace-nowrap px-space-lg text-right">
                                                {row.tracking !== "-" ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetailResi(row.tracking)}
                                                        aria-label={`Lihat detail ${row.tracking}`}
                                                        title="Lihat detail"
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-secondary transition-colors hover:bg-surface-container hover:text-primary"
                                                    >
                                                        <MaterialIcon name="visibility" className="text-[18px]" />
                                                    </button>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                    {!loading && activity.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-space-lg py-space-xl text-center font-body-sm text-body-sm text-secondary"
                                            >
                                                Belum ada perubahan status pada tanggal ini.
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>
                        <Paginator
                            page={currentPage}
                            pageCount={pageCount}
                            total={paginator?.total ?? 0}
                            label="aktivitas"
                            onPage={setPage}
                        />
                    </>
                ) : (
                    <>
                        <div className={cx("w-full overflow-x-auto", messagesLoading && "opacity-60")}>
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="h-9 bg-surface-container-low">
                                        {["Waktu", "Arah", "Nomor", "Pesan", "Status", "Resi"].map((heading) => (
                                            <th
                                                key={heading}
                                                className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider"
                                            >
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {messageRows.map((row) => (
                                        <tr key={row.id} className="transition-colors hover:bg-surface-container-low">
                                            <td className="whitespace-nowrap px-space-lg align-top font-body-sm text-body-sm text-secondary">
                                                {row.at}
                                            </td>
                                            <td className="whitespace-nowrap px-space-lg align-top">
                                                <span
                                                    className={cx(
                                                        "inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-label-sm text-label-sm",
                                                        row.direction === "IN"
                                                            ? "bg-secondary-container text-on-secondary-container"
                                                            : "bg-tertiary-container/40 text-tertiary"
                                                    )}
                                                >
                                                    <MaterialIcon
                                                        name={row.direction === "IN" ? "call_received" : "call_made"}
                                                        className="text-[14px]"
                                                    />
                                                    {row.direction === "IN" ? "Masuk" : "Keluar"}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-space-lg align-top font-data-mono text-body-sm text-on-surface">
                                                {row.phone}
                                            </td>
                                            <td className="px-space-lg py-space-sm align-top font-body-sm text-body-sm text-on-surface">
                                                <span className="line-clamp-2" title={row.body}>
                                                    {row.body}
                                                </span>
                                                {row.template ? (
                                                    <span className="mt-0.5 block font-label-sm text-label-sm text-secondary">
                                                        {row.template}
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="whitespace-nowrap px-space-lg align-top font-label-sm text-label-sm text-secondary uppercase">
                                                {row.status}
                                            </td>
                                            <td className="whitespace-nowrap px-space-lg align-top">
                                                {row.tracking_number ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetailResi(row.tracking_number as string)}
                                                        className="font-data-mono text-body-sm font-semibold text-primary hover:underline"
                                                    >
                                                        {row.tracking_number}
                                                    </button>
                                                ) : (
                                                    <span className="font-body-sm text-body-sm text-secondary">-</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {!messagesLoading && messageRows.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-space-lg py-space-xl text-center font-body-sm text-body-sm text-secondary"
                                            >
                                                {messagesError ?? "Belum ada pesan WhatsApp."}
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>
                        <Paginator
                            page={messageCurrent}
                            pageCount={messagePages}
                            total={messages?.paginator?.total ?? 0}
                            label="pesan"
                            onPage={setMessagePage}
                        />
                    </>
                )}
            </div>

            {detailResi ? (
                <ShipmentDetailModal
                    key={detailResi}
                    resi={detailResi}
                    onClose={() => setDetailResi(null)}
                    onChanged={() => {
                        void load();
                        if (tab === "messages") void loadMessages();
                    }}
                />
            ) : null}
        </AdminLayout>
    );
}
