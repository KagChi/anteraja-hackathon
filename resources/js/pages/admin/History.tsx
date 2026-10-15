import { Head } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ShipmentDetailModal } from "@/components/ShipmentDetailModal";
import { HISTORY_STATUS_OPTIONS, STATUS_TONE } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { ApiError, api, type HistoryResponse, type MessageRow } from "@/lib/api";
import { cx } from "@/lib/cx";

interface HistoryProps {
    q?: string | null;
    status?: string | null;
}

/**
 * Archive of deliveries that already closed — received, returned or closed out.
 *
 * The queue on the Shipment screen excludes these on purpose, so this is where
 * they are searched and exported from.
 */
export default function History({ q: initialQuery, status: initialStatus }: HistoryProps) {
    const [query, setQuery] = useState(initialQuery ?? "");
    const [status, setStatus] = useState(initialStatus ?? "");
    const [page, setPage] = useState(1);
    const [detailResi, setDetailResi] = useState<string | null>(null);

    const [data, setData] = useState<HistoryResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [messages, setMessages] = useState<MessageRow[]>([]);
    const [messagesLoading, setMessagesLoading] = useState(true);

    const requestId = useRef(0);

    const load = useCallback(async () => {
        const id = requestId.current + 1;
        requestId.current = id;
        setLoading(true);

        try {
            const response = await api.history.index({ q: query.trim(), status, page });

            if (requestId.current !== id) return;
            setData(response);
            setError(null);
        } catch (caught) {
            if (requestId.current !== id) return;
            setError(caught instanceof ApiError ? caught.message : "Riwayat gagal dimuat.");
        } finally {
            if (requestId.current === id) setLoading(false);
        }
    }, [query, status, page]);

    // Debounced so typing in the search box does not fire a request per keystroke.
    useEffect(() => {
        const handle = window.setTimeout(() => {
            void load();
        }, 250);

        return () => window.clearTimeout(handle);
    }, [load]);

    // Riwayat pesan WhatsApp mengikuti kata kunci yang sama dengan riwayat
    // pengiriman: satu kolom pencarian untuk keduanya.
    useEffect(() => {
        let active = true;
        const handle = window.setTimeout(() => {
            setMessagesLoading(true);
            void (async () => {
                try {
                    const resi = query.trim();
                    const response = await api.messages.index(resi === "" ? {} : { resi });
                    if (active) setMessages(response.messages);
                } catch {
                    if (active) setMessages([]);
                } finally {
                    if (active) setMessagesLoading(false);
                }
            })();
        }, 250);

        return () => {
            active = false;
            window.clearTimeout(handle);
        };
    }, [query]);

    const rows = data?.history ?? [];
    const paginator = data?.paginator;
    const pageCount = paginator?.last_page ?? 1;
    const currentPage = paginator?.current_page ?? 1;
    const total = paginator?.total ?? 0;
    const rangeLabel =
        total === 0
            ? "0"
            : `${((currentPage - 1) * (paginator?.per_page ?? 15) + 1).toLocaleString("id-ID")}-${Math.min(currentPage * (paginator?.per_page ?? 15), total).toLocaleString("id-ID")}`;

    return (
        <AdminLayout>
            <Head title="Riwayat" />

            <div className="flex flex-col justify-between gap-space-md pb-space-lg md:flex-row md:items-center">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Riwayat</h1>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        Arsip pengiriman yang sudah selesai, diterima, atau dikembalikan
                    </p>
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

            <div className="mb-space-lg flex w-full flex-wrap items-center gap-space-sm rounded-lg border border-outline-variant bg-surface-container-lowest p-space-md">
                <div className="relative w-full md:max-w-sm md:flex-1">
                    <MaterialIcon
                        name="search"
                        className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[18px] text-secondary"
                    />
                    <input
                        type="text"
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setPage(1);
                        }}
                        placeholder="Cari resi, pembeli, atau kurir..."
                        className="h-9 w-full rounded-md border border-outline-variant bg-surface-container-low pr-space-md pl-9 font-body-sm text-body-sm text-on-surface transition-colors placeholder:text-secondary hover:border-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                    />
                </div>
                <div className="flex flex-wrap items-center gap-space-sm md:ml-auto">
                    <div className="relative">
                        <select
                            value={status}
                            aria-label="Filter status riwayat"
                            onChange={(event) => {
                                setStatus(event.target.value);
                                setPage(1);
                            }}
                            className="h-9 cursor-pointer appearance-none rounded-md border border-outline-variant bg-surface-container-low pr-8 pl-space-md font-body-sm text-body-sm text-on-surface transition-colors hover:border-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                        >
                            <option value="">Semua Status</option>
                            {HISTORY_STATUS_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
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

            <div className="flex w-full flex-col overflow-hidden rounded-lg bg-surface-container-lowest">
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse text-left">
                        <thead>
                            <tr className="h-9 bg-surface-container-low">
                                {["Resi", "Pembeli", "Kurir", "Selesai", "Status"].map((heading) => (
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
                                    <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-secondary">
                                        {row.completed_at}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        <div className="flex items-center gap-1.5">
                                            <span
                                                className={cx(
                                                    "h-2 w-2 shrink-0 rounded-full",
                                                    STATUS_TONE[row.tone].dot
                                                )}
                                            />
                                            <span
                                                className={cx("font-body-sm text-body-sm", STATUS_TONE[row.tone].text)}
                                            >
                                                {row.status}
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
                                        colSpan={6}
                                        className="px-space-lg py-space-xl text-center font-body-sm text-body-sm text-secondary"
                                    >
                                        Belum ada pengiriman yang cocok dengan filter.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
                <div className="flex h-12 items-center justify-between px-space-lg">
                    <span className="font-body-sm text-body-sm text-secondary">
                        Menampilkan {rangeLabel} dari {total.toLocaleString("id-ID")} pengiriman
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

            <div className="mt-space-xl overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest">
                <div className="flex items-center justify-between border-outline-variant border-b px-space-lg py-space-md">
                    <div>
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Riwayat Pesan WhatsApp</h2>
                        <p className="font-body-sm text-body-sm text-secondary">
                            Pesan masuk dan balasan otomatis, terbaru di atas
                        </p>
                    </div>
                    <MaterialIcon name="forum" className="text-[20px] text-secondary" />
                </div>
                <ul className={cx("divide-y divide-surface-container", messagesLoading && "opacity-60")}>
                    {messages.map((message) => (
                        <li key={message.id} className="flex flex-col gap-1 px-space-lg py-space-md">
                            <span className="flex flex-wrap items-center gap-space-sm">
                                <span
                                    className={cx(
                                        "rounded-sm px-space-xs py-0.5 font-label-sm text-label-sm",
                                        message.direction === "IN"
                                            ? "bg-secondary-container text-on-secondary-container"
                                            : "bg-tertiary-fixed text-on-tertiary-fixed"
                                    )}
                                >
                                    {message.direction === "IN" ? "Masuk" : "Keluar"}
                                </span>
                                <span className="font-data-mono text-body-sm text-on-surface">{message.phone}</span>
                                {message.role ? (
                                    <span className="font-label-sm text-label-sm text-secondary">{message.role}</span>
                                ) : null}
                                {message.tracking_number ? (
                                    <span className="font-data-mono text-body-sm text-secondary">
                                        {message.tracking_number}
                                    </span>
                                ) : null}
                                <span className="ml-auto font-body-sm text-body-sm text-secondary">{message.at}</span>
                            </span>
                            <span className="font-body-sm text-body-sm text-on-surface">{message.body}</span>
                            {message.reply_body ? (
                                <span className="font-body-sm text-body-sm text-secondary">
                                    Balasan: {message.reply_body}
                                </span>
                            ) : null}
                            <span
                                className={cx(
                                    "font-label-sm text-label-sm",
                                    message.status === "FAILED" ? "text-error" : "text-secondary"
                                )}
                            >
                                {message.status === "FAILED"
                                    ? "Gagal terkirim"
                                    : message.status === "SENT"
                                      ? "Terkirim"
                                      : "Diterima"}
                            </span>
                        </li>
                    ))}
                    {!messagesLoading && messages.length === 0 ? (
                        <li className="px-space-lg py-space-xl text-center font-body-sm text-body-sm text-secondary">
                            Belum ada pesan WhatsApp yang tercatat.
                        </li>
                    ) : null}
                </ul>
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
