import { Head } from "@inertiajs/react";
import { type FormEvent, useCallback, useEffect, useRef, useState } from "react";
import { CourierPickerDialog } from "@/components/CourierPickerDialog";
import { Dialog } from "@/components/Dialog";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ShipmentDetailModal } from "@/components/ShipmentDetailModal";
import { DELIVERY_SLOTS, STATUS_TONE } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { ApiError, api, type ShipmentCreateOptions, type ShipmentIndexResponse, type ShipmentRow } from "@/lib/api";
import { cx } from "@/lib/cx";
import { todayIso } from "@/lib/date";

const PER_PAGE = 10;

interface FilterSelectProps {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    options: Array<{ value: string; label: string }>;
}

function FilterSelect({ value, onChange, placeholder, options }: FilterSelectProps) {
    return (
        <div className="relative">
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 cursor-pointer appearance-none rounded-md border border-outline-variant bg-surface-container-low pr-8 pl-space-md font-body-sm text-body-sm text-on-surface transition-colors hover:border-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
            >
                <option value="">{placeholder}</option>
                {options.map((option) => (
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
    );
}

interface ShipmentProps {
    /** Filters carried in by a Laporan drill-down. */
    status?: string | null;
    date?: string | null;
}

/**
 * The delivery queue.
 *
 * Rows, filters, pagination and the courier list all come from
 * GET /api/admin/shipments; every action posts back to the API and refetches,
 * so the table can never drift from the server.
 */
export default function Shipment({ status: initialStatus, date: initialDate }: ShipmentProps) {
    const [query, setQuery] = useState("");
    const [status, setStatus] = useState(initialStatus ?? "");
    const [courier, setCourier] = useState("");
    // "ADMIN" or "CLIENT_APP": orders the customer app opened are tagged, so
    // the hub can work that queue on its own.
    const [source, setSource] = useState("");
    const [date, setDate] = useState(initialDate ?? todayIso());
    const [page, setPage] = useState(1);

    const [data, setData] = useState<ShipmentIndexResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const [assignTarget, setAssignTarget] = useState<ShipmentRow | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [detailResi, setDetailResi] = useState<string | null>(null);
    const [createOpen, setCreateOpen] = useState(false);

    // Only the newest response may land: filter edits fire overlapping requests.
    const requestId = useRef(0);

    const load = useCallback(async () => {
        const id = requestId.current + 1;
        requestId.current = id;
        setLoading(true);

        try {
            const response = await api.shipments.index({
                q: query.trim(),
                status,
                courier,
                source,
                date,
                page,
                per_page: PER_PAGE
            });

            if (requestId.current !== id) return;
            setData(response);
            setError(null);
        } catch (caught) {
            if (requestId.current !== id) return;
            setError(caught instanceof ApiError ? caught.message : "Daftar pengiriman gagal dimuat.");
        } finally {
            if (requestId.current === id) setLoading(false);
        }
    }, [query, status, courier, source, date, page]);

    // Debounced so typing in the search box does not fire a request per keystroke.
    useEffect(() => {
        const handle = window.setTimeout(() => {
            void load();
        }, 250);

        return () => window.clearTimeout(handle);
    }, [load]);

    function resetFilters() {
        setQuery("");
        setStatus("");
        setCourier("");
        setSource("");
        setDate(todayIso());
        setPage(1);
    }

    async function assign(courierId: number) {
        if (!assignTarget) return;

        setActionError(null);

        try {
            const response = await api.shipments.assign(assignTarget.tracking, courierId);
            setAssignTarget(null);
            setNotice(response.message);
            await load();
        } catch (caught) {
            setActionError(caught instanceof ApiError ? caught.message : "Kurir gagal ditugaskan.");
        }
    }

    const rows = data?.shipments ?? [];
    const paginator = data?.paginator;
    const pageCount = paginator?.last_page ?? 1;
    const currentPage = paginator?.current_page ?? 1;
    const total = paginator?.total ?? 0;
    const rangeLabel = total === 0 ? "0" : `${paginator?.from ?? 1}-${paginator?.to ?? 0}`;
    const filtersActive =
        query.trim() !== "" || status !== "" || courier !== "" || source !== "" || date !== todayIso();

    return (
        <AdminLayout>
            <Head title="Shipment" />

            <div className="flex flex-col justify-between gap-space-md pb-space-lg md:flex-row md:items-center">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Shipment</h1>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        Daftar pengiriman dan penugasan kurir makanan beku
                    </p>
                </div>
                <div className="flex items-center gap-space-sm self-start">
                    <button
                        type="button"
                        onClick={() => setCreateOpen(true)}
                        className="flex h-9 items-center gap-space-xs rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        <MaterialIcon name="add" className="text-[16px]" />
                        <span>Buat Shipment</span>
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
                        placeholder="Cari resi, pembeli, atau lokasi..."
                        className="h-9 w-full rounded-md border border-outline-variant bg-surface-container-low pr-space-md pl-9 font-body-sm text-body-sm text-on-surface transition-colors placeholder:text-secondary hover:border-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                    />
                </div>
                <div className="flex flex-wrap items-center gap-space-sm md:ml-auto">
                    <FilterSelect
                        value={status}
                        onChange={(value) => {
                            setStatus(value);
                            setPage(1);
                        }}
                        placeholder="Semua Status"
                        options={data?.statusOptions ?? []}
                    />
                    <FilterSelect
                        value={courier}
                        onChange={(value) => {
                            setCourier(value);
                            setPage(1);
                        }}
                        placeholder="Semua Kurir"
                        options={[
                            ...(data?.couriers ?? []).map((option) => ({
                                value: String(option.id),
                                label: option.name
                            })),
                            { value: "unassigned", label: "Belum Ditugaskan" }
                        ]}
                    />
                    <FilterSelect
                        value={source}
                        onChange={(value) => {
                            setSource(value);
                            setPage(1);
                        }}
                        placeholder="Semua Asal Pesanan"
                        options={[
                            { value: "ADMIN", label: "Hub Console" },
                            { value: "CLIENT_APP", label: "Aplikasi Pelanggan" }
                        ]}
                    />
                    <div className="relative">
                        <MaterialIcon
                            name="calendar_today"
                            className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[16px] text-secondary"
                        />
                        <input
                            type="date"
                            value={date}
                            aria-label="Filter tanggal pengiriman"
                            onChange={(event) => {
                                setDate(event.target.value === "" ? todayIso() : event.target.value);
                                setPage(1);
                            }}
                            className="h-9 cursor-pointer rounded-md border border-outline-variant bg-surface-container-low pr-2 pl-9 font-body-sm text-body-sm text-on-surface transition-colors hover:border-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                        />
                    </div>
                    {filtersActive ? (
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="flex h-9 items-center gap-1 rounded-md px-space-sm font-label-md text-label-md text-primary transition-colors hover:bg-surface-container"
                        >
                            <MaterialIcon name="close" className="text-[16px]" />
                            <span>Bersihkan filter</span>
                        </button>
                    ) : null}
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

            <div className="flex w-full flex-col overflow-hidden rounded-lg bg-surface-container-lowest">
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse text-left">
                        <thead>
                            <tr className="h-9 bg-surface-container-low">
                                {["Resi", "Pengirim / Penerima", "Jadwal", "Kurir", "Status"].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider"
                                    >
                                        {heading}
                                    </th>
                                ))}
                                <th className="sticky right-0 z-10 border-black/5 border-l bg-surface-container-low px-space-lg text-right font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
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
                                <tr
                                    key={row.tracking}
                                    className="group h-10 transition-colors hover:bg-surface-container-low"
                                >
                                    <td className="whitespace-nowrap px-space-lg">
                                        <div className="flex items-center gap-2">
                                            <span className="font-data-mono font-semibold text-body-sm text-on-surface">
                                                {row.tracking}
                                            </span>
                                            {row.source === "CLIENT_APP" ? (
                                                <span
                                                    title="Dipesan dari aplikasi pelanggan"
                                                    className="rounded bg-primary/10 px-1.5 py-0.5 font-label-sm text-label-sm text-primary"
                                                >
                                                    APP
                                                </span>
                                            ) : null}
                                        </div>
                                    </td>
                                    <td className="px-space-lg">
                                        <div className="font-body-sm text-body-sm text-on-surface">
                                            {row.recipient}
                                            <span className="font-label-sm text-label-sm text-secondary">
                                                {" "}
                                                dari {row.buyer}
                                            </span>
                                        </div>
                                        <div className="font-label-sm text-label-sm text-secondary">{row.location}</div>
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        <span className="font-body-sm text-body-sm text-on-surface">
                                            {row.schedule}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        {row.courier ? (
                                            <span className="font-body-sm text-body-sm text-on-surface">
                                                {row.courier}
                                            </span>
                                        ) : (
                                            <span className="font-body-sm text-body-sm text-outline italic">
                                                Belum Ditugaskan
                                            </span>
                                        )}
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
                                    <td className="sticky right-0 z-10 whitespace-nowrap border-black/5 border-l bg-surface-container-lowest px-space-md text-right transition-colors group-hover:bg-surface-container-low">
                                        {row.availableAction === "assign_courier" ? (
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setActionError(null);
                                                    setAssignTarget(row);
                                                }}
                                                aria-label={`Tugaskan kurir untuk ${row.tracking}`}
                                                title="Tugaskan kurir"
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-on-surface transition-colors hover:bg-surface-container hover:text-primary"
                                            >
                                                <MaterialIcon name="person_add" className="text-[18px]" />
                                            </button>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() => setDetailResi(row.tracking)}
                                                aria-label={
                                                    row.availableAction === "issue_detail"
                                                        ? `Detail kendala ${row.tracking}`
                                                        : `Lihat detail ${row.tracking}`
                                                }
                                                title={
                                                    row.availableAction === "issue_detail"
                                                        ? "Detail kendala"
                                                        : "Lihat detail"
                                                }
                                                className={cx(
                                                    "inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-surface-container hover:text-primary",
                                                    row.availableAction === "issue_detail"
                                                        ? "text-primary"
                                                        : "text-secondary"
                                                )}
                                            >
                                                <MaterialIcon
                                                    name={
                                                        row.availableAction === "issue_detail"
                                                            ? "report_problem"
                                                            : "visibility"
                                                    }
                                                    className="text-[18px]"
                                                />
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {!loading && rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-space-lg py-space-xl text-center font-body-sm text-body-sm text-secondary"
                                    >
                                        Tidak ada pengiriman yang cocok dengan filter.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                <div className="flex h-12 items-center justify-between px-space-lg">
                    <span className="font-body-sm text-body-sm text-secondary">
                        Menampilkan {rangeLabel} dari {total} pengiriman
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

            <CourierPickerDialog
                open={assignTarget !== null}
                couriers={data?.couriers ?? []}
                currentId={assignTarget?.courier_id ?? null}
                subtitle={
                    assignTarget
                        ? `${assignTarget.tracking} • ${assignTarget.recipient} (${assignTarget.location})`
                        : undefined
                }
                onClose={() => setAssignTarget(null)}
                onConfirm={(courierId) => void assign(courierId)}
            />

            {actionError && assignTarget !== null ? (
                <Dialog onClose={() => setActionError(null)} label="Penugasan gagal">
                    <div className="flex flex-col items-center gap-space-sm text-center">
                        <MaterialIcon name="error" className="text-[24px] text-error" />
                        <span className="font-headline-sm text-headline-sm text-on-surface">Penugasan gagal</span>
                        <p className="font-body-sm text-body-sm text-secondary">{actionError}</p>
                        <button
                            type="button"
                            onClick={() => setActionError(null)}
                            className="mt-space-xs flex h-9 items-center rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                        >
                            Tutup
                        </button>
                    </div>
                </Dialog>
            ) : null}

            {detailResi ? (
                <ShipmentDetailModal
                    key={detailResi}
                    resi={detailResi}
                    onClose={() => setDetailResi(null)}
                    onChanged={() => void load()}
                />
            ) : null}

            {createOpen ? (
                <CreateShipmentDialog
                    onClose={() => setCreateOpen(false)}
                    onCreated={async (message) => {
                        setCreateOpen(false);
                        setNotice(message);
                        setPage(1);
                        await load();
                    }}
                />
            ) : null}
        </AdminLayout>
    );
}

interface CreateShipmentDialogProps {
    onClose: () => void;
    onCreated: (message: string) => Promise<void>;
}

/** One product line while it is being drafted; the id keeps React keys stable. */
interface ItemDraft {
    id: number;
    product_id: string;
    quantity: string;
}

/**
 * Create form for a new shipment. The API needs a buyer (existing or new), at
 * least one product line, a delivery day and window, and a weight, so the form
 * collects exactly that.
 */
function CreateShipmentDialog({ onClose, onCreated }: CreateShipmentDialogProps) {
    const [options, setOptions] = useState<ShipmentCreateOptions | null>(null);
    const [optionsError, setOptionsError] = useState<string | null>(null);
    const [buyerMode, setBuyerMode] = useState<"new" | "existing">("new");
    const [buyerId, setBuyerId] = useState("");
    const [buyer, setBuyer] = useState({ name: "", phone: "", address: "", area: "" });
    // Most counter shipments go to the buyer themselves, so the recipient block
    // stays off and the API copies the buyer's details. Tick it off when the
    // parcel is meant for someone else — family at another address.
    const [recipientGiven, setRecipientGiven] = useState(false);
    const [recipient, setRecipient] = useState({ name: "", phone: "", address: "", area: "" });
    const [items, setItems] = useState<ItemDraft[]>([{ id: 1, product_id: "", quantity: "1" }]);
    const [scheduledDate, setScheduledDate] = useState(todayIso());
    const [slot, setSlot] = useState(DELIVERY_SLOTS[2]);
    const [weightKg, setWeightKg] = useState("1");
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    const nextItemId = useRef(2);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await api.shipments.createOptions();
                if (cancelled) return;
                setOptions(response);
                setItems([{ id: 1, product_id: String(response.products[0]?.id ?? ""), quantity: "1" }]);
            } catch (caught) {
                if (cancelled) return;
                setOptionsError(caught instanceof ApiError ? caught.message : "Data master gagal dimuat.");
            }
        })();

        return () => {
            cancelled = true;
        };
    }, []);

    function updateItem(id: number, patch: Partial<Omit<ItemDraft, "id">>) {
        setItems((previous) => previous.map((item) => (item.id === id ? { ...item, ...patch } : item)));
    }

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (saving) return;

        const [windowStart, windowEnd] = slot.split("-");
        const parsedWeight = Number(weightKg);

        if (!Number.isFinite(parsedWeight) || parsedWeight <= 0) {
            setError("Berat paket harus lebih dari 0 kg.");
            return;
        }

        setSaving(true);
        setError(null);
        setErrors({});

        try {
            const response = await api.shipments.store({
                ...(buyerMode === "existing"
                    ? { buyer_id: Number(buyerId) }
                    : {
                          buyer: {
                              name: buyer.name.trim(),
                              phone: buyer.phone.trim(),
                              address: buyer.address.trim(),
                              ...(buyer.area.trim() === "" ? {} : { area: buyer.area.trim() })
                          }
                      }),
                ...(recipientGiven
                    ? {
                          recipient: {
                              name: recipient.name.trim(),
                              phone: recipient.phone.trim(),
                              address: recipient.address.trim(),
                              ...(recipient.area.trim() === "" ? {} : { area: recipient.area.trim() })
                          }
                      }
                    : {}),
                items: items.map((item) => ({
                    product_id: Number(item.product_id),
                    quantity: Number(item.quantity)
                })),
                scheduled_date: scheduledDate,
                window_start: windowStart,
                window_end: windowEnd,
                weight_kg: parsedWeight
            });

            await onCreated(response.message);
        } catch (caught) {
            if (caught instanceof ApiError) {
                setErrors(caught.errors);
                setError(caught.message);
            } else {
                setError("Shipment gagal dibuat.");
            }
            setSaving(false);
        }
    }

    const fieldError = (key: string) => errors[key]?.[0];

    return (
        <Dialog
            onClose={onClose}
            label="Buat Shipment"
            width="2xl"
            className="max-h-[calc(100dvh-3rem)] overflow-y-auto"
        >
            <form onSubmit={handleSubmit} className="flex flex-col gap-space-md">
                <div className="flex items-center justify-between">
                    <h2 className="font-headline-sm text-headline-sm text-on-surface">Buat Shipment</h2>
                    <button
                        type="button"
                        aria-label="Tutup"
                        onClick={onClose}
                        className="flex h-8 w-8 items-center justify-center text-secondary transition-colors hover:text-on-surface"
                    >
                        <MaterialIcon name="close" className="text-[20px]" />
                    </button>
                </div>

                {optionsError ? (
                    <div className="rounded-lg bg-error-container p-space-sm font-body-sm text-body-sm text-on-error-container">
                        {optionsError}
                    </div>
                ) : null}

                <section className="flex flex-col gap-space-sm">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Pembeli</span>
                    <div className="flex items-center gap-space-sm">
                        {(["new", "existing"] as const).map((mode) => (
                            <button
                                key={mode}
                                type="button"
                                onClick={() => setBuyerMode(mode)}
                                aria-pressed={buyerMode === mode}
                                className={cx(
                                    "h-8 rounded-md px-space-md font-label-md text-label-md transition-colors",
                                    buyerMode === mode
                                        ? "bg-primary text-on-primary"
                                        : "bg-surface-container-low text-secondary hover:text-on-surface"
                                )}
                            >
                                {mode === "new" ? "Pembeli Baru" : "Pembeli Terdaftar"}
                            </button>
                        ))}
                    </div>

                    {buyerMode === "existing" ? (
                        <label className="flex flex-col gap-1" htmlFor="existing-buyer">
                            <select
                                id="existing-buyer"
                                value={buyerId}
                                onChange={(event) => setBuyerId(event.target.value)}
                                required
                                className="h-9 cursor-pointer rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                            >
                                <option value="">Pilih pembeli…</option>
                                {(options?.buyers ?? []).map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name} · {option.area ?? option.address}
                                    </option>
                                ))}
                            </select>
                            {fieldError("buyer_id") ? (
                                <span className="font-body-sm text-body-sm text-error">{fieldError("buyer_id")}</span>
                            ) : null}
                        </label>
                    ) : (
                        <div className="grid grid-cols-1 gap-space-sm sm:grid-cols-2">
                            <label className="flex flex-col gap-1" htmlFor="new-buyer-name">
                                <span className="font-body-sm text-body-sm text-secondary">Nama</span>
                                <input
                                    id="new-buyer-name"
                                    value={buyer.name}
                                    onChange={(event) => setBuyer({ ...buyer, name: event.target.value })}
                                    required
                                    className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                                {fieldError("buyer.name") ? (
                                    <span className="font-body-sm text-body-sm text-error">
                                        {fieldError("buyer.name")}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1" htmlFor="new-buyer-phone">
                                <span className="font-body-sm text-body-sm text-secondary">Telepon</span>
                                <input
                                    id="new-buyer-phone"
                                    value={buyer.phone}
                                    onChange={(event) => setBuyer({ ...buyer, phone: event.target.value })}
                                    required
                                    className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                                {fieldError("buyer.phone") ? (
                                    <span className="font-body-sm text-body-sm text-error">
                                        {fieldError("buyer.phone")}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 sm:col-span-2" htmlFor="new-buyer-address">
                                <span className="font-body-sm text-body-sm text-secondary">Alamat</span>
                                <input
                                    id="new-buyer-address"
                                    value={buyer.address}
                                    onChange={(event) => setBuyer({ ...buyer, address: event.target.value })}
                                    required
                                    className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                                {fieldError("buyer.address") ? (
                                    <span className="font-body-sm text-body-sm text-error">
                                        {fieldError("buyer.address")}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 sm:col-span-2" htmlFor="new-buyer-area">
                                <span className="font-body-sm text-body-sm text-secondary">
                                    Area (opsional, mis. Tebet, Jaksel)
                                </span>
                                <input
                                    id="new-buyer-area"
                                    value={buyer.area}
                                    onChange={(event) => setBuyer({ ...buyer, area: event.target.value })}
                                    className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                            </label>
                        </div>
                    )}
                </section>

                <section className="flex flex-col gap-space-sm">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Penerima</span>
                    <label className="flex items-start gap-space-sm" htmlFor="recipient-same-as-buyer">
                        <input
                            id="recipient-same-as-buyer"
                            type="checkbox"
                            checked={!recipientGiven}
                            onChange={(event) => setRecipientGiven(!event.target.checked)}
                            className="mt-0.5 h-4 w-4 cursor-pointer accent-primary"
                        />
                        <span className="font-body-sm text-body-sm text-secondary">
                            Sama dengan pengirim — biarkan tercentang kalau paket diantar ke alamat pengirim sendiri.
                        </span>
                    </label>

                    {recipientGiven ? (
                        <div className="grid grid-cols-1 gap-space-sm sm:grid-cols-2">
                            <label className="flex flex-col gap-1" htmlFor="new-recipient-name">
                                <span className="font-body-sm text-body-sm text-secondary">Nama penerima</span>
                                <input
                                    id="new-recipient-name"
                                    value={recipient.name}
                                    onChange={(event) => setRecipient({ ...recipient, name: event.target.value })}
                                    required
                                    className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                                {fieldError("recipient.name") ? (
                                    <span className="font-body-sm text-body-sm text-error">
                                        {fieldError("recipient.name")}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1" htmlFor="new-recipient-phone">
                                <span className="font-body-sm text-body-sm text-secondary">Telepon penerima</span>
                                <input
                                    id="new-recipient-phone"
                                    value={recipient.phone}
                                    onChange={(event) => setRecipient({ ...recipient, phone: event.target.value })}
                                    required
                                    className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                                {fieldError("recipient.phone") ? (
                                    <span className="font-body-sm text-body-sm text-error">
                                        {fieldError("recipient.phone")}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 sm:col-span-2" htmlFor="new-recipient-address">
                                <span className="font-body-sm text-body-sm text-secondary">Alamat penerima</span>
                                <input
                                    id="new-recipient-address"
                                    value={recipient.address}
                                    onChange={(event) => setRecipient({ ...recipient, address: event.target.value })}
                                    required
                                    className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                                {fieldError("recipient.address") ? (
                                    <span className="font-body-sm text-body-sm text-error">
                                        {fieldError("recipient.address")}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 sm:col-span-2" htmlFor="new-recipient-area">
                                <span className="font-body-sm text-body-sm text-secondary">
                                    Area penerima (opsional, mis. Tebet, Jaksel)
                                </span>
                                <input
                                    id="new-recipient-area"
                                    value={recipient.area}
                                    onChange={(event) => setRecipient({ ...recipient, area: event.target.value })}
                                    className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                            </label>
                        </div>
                    ) : null}
                </section>

                <section className="flex flex-col gap-space-sm">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Produk</span>
                    {items.map((item, index) => (
                        <div key={item.id} className="flex items-center gap-space-sm">
                            <select
                                value={item.product_id}
                                onChange={(event) => updateItem(item.id, { product_id: event.target.value })}
                                required
                                aria-label={`Produk ${index + 1}`}
                                className="h-9 flex-1 cursor-pointer rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                            >
                                <option value="">Pilih produk…</option>
                                {(options?.products ?? []).map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            <input
                                type="number"
                                min={1}
                                max={999}
                                value={item.quantity}
                                onChange={(event) => updateItem(item.id, { quantity: event.target.value })}
                                required
                                aria-label={`Jumlah produk ${index + 1}`}
                                className="h-9 w-20 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                            />
                            {items.length > 1 ? (
                                <button
                                    type="button"
                                    aria-label={`Hapus produk ${index + 1}`}
                                    onClick={() =>
                                        setItems((previous) => previous.filter((candidate) => candidate.id !== item.id))
                                    }
                                    className="flex h-9 w-9 items-center justify-center text-secondary transition-colors hover:text-error"
                                >
                                    <MaterialIcon name="delete" className="text-[18px]" />
                                </button>
                            ) : null}
                        </div>
                    ))}
                    <button
                        type="button"
                        onClick={() => {
                            const id = nextItemId.current;
                            nextItemId.current += 1;
                            setItems((previous) => [...previous, { id, product_id: "", quantity: "1" }]);
                        }}
                        className="flex h-8 items-center gap-1 self-start rounded-md px-space-sm font-label-md text-label-md text-primary transition-colors hover:bg-surface-container"
                    >
                        <MaterialIcon name="add" className="text-[16px]" />
                        <span>Tambah produk</span>
                    </button>
                    {fieldError("items") ? (
                        <span className="font-body-sm text-body-sm text-error">{fieldError("items")}</span>
                    ) : null}
                </section>

                <section className="grid grid-cols-1 gap-space-sm sm:grid-cols-3">
                    <label className="flex flex-col gap-1" htmlFor="new-date">
                        <span className="font-body-sm text-body-sm text-secondary">Tanggal</span>
                        <input
                            id="new-date"
                            type="date"
                            value={scheduledDate}
                            min={todayIso()}
                            onChange={(event) => setScheduledDate(event.target.value)}
                            required
                            className="h-9 cursor-pointer rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                        />
                        {fieldError("scheduled_date") ? (
                            <span className="font-body-sm text-body-sm text-error">{fieldError("scheduled_date")}</span>
                        ) : null}
                    </label>
                    <label className="flex flex-col gap-1" htmlFor="new-window">
                        <span className="font-body-sm text-body-sm text-secondary">Jendela</span>
                        <select
                            id="new-window"
                            value={slot}
                            onChange={(event) => setSlot(event.target.value)}
                            className="h-9 cursor-pointer rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                        >
                            {DELIVERY_SLOTS.map((option) => (
                                <option key={option} value={option}>
                                    {option} WIB
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex flex-col gap-1" htmlFor="new-weight">
                        <span className="font-body-sm text-body-sm text-secondary">Berat (kg)</span>
                        <input
                            id="new-weight"
                            type="number"
                            min={0.01}
                            step="0.01"
                            value={weightKg}
                            onChange={(event) => setWeightKg(event.target.value)}
                            required
                            className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                        />
                        {fieldError("weight_kg") ? (
                            <span className="font-body-sm text-body-sm text-error">{fieldError("weight_kg")}</span>
                        ) : null}
                    </label>
                </section>

                <p className="font-body-sm text-body-sm text-secondary">
                    Resi dibuat otomatis dan konfirmasi jadwal dikirim ke pembeli. Kurir ditugaskan setelah pembeli
                    mengonfirmasi.
                </p>

                {error ? (
                    <div
                        role="alert"
                        className="flex items-start gap-space-xs rounded-lg bg-error-container p-space-sm font-body-sm text-body-sm text-on-error-container"
                    >
                        <MaterialIcon name="error" className="text-[18px]" />
                        <span>{error}</span>
                    </div>
                ) : null}

                <div className="flex items-center justify-end gap-space-sm pt-space-xs">
                    <button
                        type="button"
                        onClick={onClose}
                        className="h-9 rounded-md px-space-md font-label-md text-label-md text-secondary transition-colors hover:text-on-surface"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={saving || options === null}
                        className="flex h-9 items-center gap-space-xs rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-50"
                    >
                        <MaterialIcon name={saving ? "refresh" : "check"} className="text-[16px]" />
                        <span>{saving ? "Menyimpan…" : "Simpan Shipment"}</span>
                    </button>
                </div>
            </form>
        </Dialog>
    );
}
