import { Head, Link } from "@inertiajs/react";
import { type FormEvent, useEffect, useMemo, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import {
    COURIER_OPTIONS,
    SCHEDULE_OPTIONS,
    SHIPMENTS,
    type ShipmentRow,
    STATUS_OPTIONS,
    STATUS_TONE
} from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { cx } from "@/lib/cx";

const PAGE_SIZE = 6;

/** Delivery windows offered when creating a shipment from the console. */
const CREATE_SCHEDULES = [
    "Hari ini, 13:00 - 15:00",
    "Hari ini, 15:00 - 17:00",
    "Besok, 09:00 - 11:00",
    "Besok, 13:00 - 15:00"
];

const COURIERS = COURIER_OPTIONS.filter((option) => option.value !== "unassigned");

/** Deliveries that already closed; the `Selesai` status filter collects them. */
const CLOSED_STATUSES = ["Diterima", "Dikembalikan"];

const SCHEDULE_DAY: Record<string, string> = { today: "Hari ini", tomorrow: "Besok" };

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

export default function Shipment() {
    const [items, setItems] = useState<ShipmentRow[]>(SHIPMENTS);
    const [query, setQuery] = useState("");
    const [status, setStatus] = useState("");
    const [courier, setCourier] = useState("");
    const [schedule, setSchedule] = useState("");
    const [page, setPage] = useState(1);
    const [assignTarget, setAssignTarget] = useState<ShipmentRow | null>(null);
    const [assignCourier, setAssignCourier] = useState("");
    const [courierSearch, setCourierSearch] = useState("");
    const [createOpen, setCreateOpen] = useState(false);
    const [buyer, setBuyer] = useState("");
    const [location, setLocation] = useState("");
    const [deliveryWindow, setDeliveryWindow] = useState(CREATE_SCHEDULES[0]);

    useEffect(() => {
        if (!assignTarget && !createOpen) return;

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key !== "Escape") return;
            setCreateOpen(false);
            setAssignTarget(null);
        }

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [assignTarget, createOpen]);

    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return items.filter((row) => {
            if (needle) {
                const haystack = [row.tracking, row.buyer, row.location, row.courier ?? ""].join(" ").toLowerCase();
                if (!haystack.includes(needle)) return false;
            }
            if (status === "selesai") {
                if (!CLOSED_STATUSES.includes(row.status)) return false;
            } else if (status && row.statusKey !== status) {
                return false;
            }
            if (courier === "unassigned" && row.courier !== null) return false;
            if (courier && courier !== "unassigned" && row.courier !== courier) return false;
            if (schedule && !row.schedule.startsWith(SCHEDULE_DAY[schedule])) return false;
            return true;
        });
    }, [items, query, status, courier, schedule]);

    const courierMatches = useMemo(() => {
        const needle = courierSearch.trim().toLowerCase();
        if (!needle) return COURIERS;
        return COURIERS.filter((option) => option.label.toLowerCase().includes(needle));
    }, [courierSearch]);

    const pageCount = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
    const currentPage = Math.min(page, pageCount);
    const first = (currentPage - 1) * PAGE_SIZE;
    const pageRows = rows.slice(first, first + PAGE_SIZE);
    const rangeLabel = rows.length === 0 ? "0" : `${first + 1}-${Math.min(first + PAGE_SIZE, rows.length)}`;
    const filtersActive = query.trim() !== "" || status !== "" || courier !== "" || schedule !== "";

    function clearFilters() {
        setQuery("");
        setStatus("");
        setCourier("");
        setSchedule("");
        setPage(1);
    }

    function assign(tracking: string, name: string) {
        setItems((prev) =>
            prev.map((row) =>
                row.tracking === tracking
                    ? {
                          ...row,
                          courier: name,
                          status: "Menunggu Konfirmasi",
                          statusKey: "waiting",
                          tone: "waiting" as const
                      }
                    : row
            )
        );
        setAssignTarget(null);
    }

    function openAssign(row: ShipmentRow) {
        setAssignCourier("");
        setCourierSearch("");
        setAssignTarget(row);
    }

    function createShipment(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!buyer.trim() || !location.trim()) return;
        const tracking = `AJ-FRZ-${String(12300 + items.length + 1).padStart(7, "0")}`;
        setItems((prev) => [
            {
                tracking,
                buyer: buyer.trim(),
                location: location.trim(),
                schedule: deliveryWindow,
                courier: null,
                status: "Dijadwalkan",
                statusKey: "scheduled",
                tone: "scheduled",
                hasIssue: false
            },
            ...prev
        ]);
        setBuyer("");
        setLocation("");
        setDeliveryWindow(CREATE_SCHEDULES[0]);
        setPage(1);
        setCreateOpen(false);
    }

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
                <button
                    type="button"
                    onClick={() => setCreateOpen(true)}
                    className="flex h-9 items-center gap-space-xs self-start rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                >
                    <MaterialIcon name="add" className="text-[16px]" />
                    <span>Buat Shipment</span>
                </button>
            </div>

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
                        options={STATUS_OPTIONS}
                    />
                    <FilterSelect
                        value={courier}
                        onChange={(value) => {
                            setCourier(value);
                            setPage(1);
                        }}
                        placeholder="Semua Kurir"
                        options={COURIER_OPTIONS}
                    />
                    <FilterSelect
                        value={schedule}
                        onChange={(value) => {
                            setSchedule(value);
                            setPage(1);
                        }}
                        placeholder="Semua Jadwal"
                        options={SCHEDULE_OPTIONS}
                    />
                    {filtersActive ? (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="flex h-9 items-center gap-1 rounded-md px-space-sm font-label-md text-label-md text-primary transition-colors hover:bg-surface-container"
                        >
                            <MaterialIcon name="close" className="text-[16px]" />
                            <span>Bersihkan filter</span>
                        </button>
                    ) : null}
                </div>
            </div>

            <div className="flex w-full flex-col overflow-hidden rounded-lg bg-surface-container-lowest">
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse text-left">
                        <thead>
                            <tr className="h-9 bg-surface-container-low">
                                {["Resi", "Pembeli", "Jadwal", "Kurir", "Status"].map((heading) => (
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
                        <tbody className="divide-y divide-surface-container">
                            {pageRows.map((row) => (
                                <tr
                                    key={row.tracking}
                                    className="group h-10 transition-colors hover:bg-surface-container-low"
                                >
                                    <td className="whitespace-nowrap px-space-lg">
                                        <span className="font-data-mono font-semibold text-body-sm text-on-surface">
                                            {row.tracking}
                                        </span>
                                    </td>
                                    <td className="px-space-lg">
                                        <div className="font-body-sm text-body-sm text-on-surface">{row.buyer}</div>
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
                                        {row.hasIssue ? (
                                            <Link
                                                href={`/admin/exception/${row.tracking}`}
                                                aria-label={`Detail kendala ${row.tracking}`}
                                                title="Detail kendala"
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-primary transition-colors hover:bg-surface-container"
                                            >
                                                <MaterialIcon name="report_problem" className="text-[18px]" />
                                            </Link>
                                        ) : row.courier === null ? (
                                            <button
                                                type="button"
                                                onClick={() => openAssign(row)}
                                                aria-label={`Tugaskan kurir untuk ${row.tracking}`}
                                                title="Tugaskan kurir"
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-on-surface transition-colors hover:bg-surface-container hover:text-primary"
                                            >
                                                <MaterialIcon name="person_add" className="text-[18px]" />
                                            </button>
                                        ) : (
                                            <Link
                                                href={`/admin/exception/${row.tracking}`}
                                                aria-label={`Lihat detail ${row.tracking}`}
                                                title="Lihat detail"
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-secondary transition-colors hover:bg-surface-container hover:text-primary"
                                            >
                                                <MaterialIcon name="visibility" className="text-[18px]" />
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 ? (
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
                        Menampilkan {rangeLabel} dari {rows.length} pengiriman
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

            {assignTarget ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-margin">
                    <button
                        type="button"
                        aria-label="Tutup dialog"
                        tabIndex={-1}
                        onClick={() => setAssignTarget(null)}
                        className="absolute inset-0 cursor-default bg-inverse-surface/40"
                    />
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!assignCourier) return;
                            assign(assignTarget.tracking, assignCourier);
                        }}
                        className="relative flex w-full max-w-md flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg shadow-xl"
                    >
                        <div className="flex items-start justify-between gap-space-md">
                            <div className="flex flex-col">
                                <h2 className="font-headline-sm text-headline-sm text-on-surface">Tugaskan Kurir</h2>
                                <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                                    {assignTarget.tracking} • {assignTarget.buyer} ({assignTarget.location})
                                </p>
                            </div>
                            <button
                                type="button"
                                aria-label="Tutup"
                                onClick={() => setAssignTarget(null)}
                                className="flex h-8 w-8 shrink-0 items-center justify-center text-secondary transition-colors hover:text-on-surface"
                            >
                                <MaterialIcon name="close" className="text-[20px]" />
                            </button>
                        </div>
                        <div className="flex flex-col gap-space-sm">
                            <label
                                htmlFor="assign-courier-search"
                                className="font-label-sm text-label-sm text-secondary uppercase"
                            >
                                Pilih kurir *
                            </label>
                            <div className="relative">
                                <MaterialIcon
                                    name="search"
                                    className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[18px] text-secondary"
                                />
                                <input
                                    id="assign-courier-search"
                                    type="text"
                                    value={courierSearch}
                                    onChange={(event) => setCourierSearch(event.target.value)}
                                    placeholder="Cari nama kurir..."
                                    autoComplete="off"
                                    className="h-9 w-full rounded-md border border-outline-variant bg-surface-container-low pr-space-md pl-9 font-body-sm text-body-sm text-on-surface transition-colors placeholder:text-secondary hover:border-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                                />
                            </div>
                            <div className="flex max-h-56 flex-col overflow-y-auto rounded-lg border border-outline-variant">
                                {courierMatches.map((option) => {
                                    const active = assignCourier === option.value;
                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            aria-pressed={active}
                                            onClick={() => setAssignCourier(option.value)}
                                            className={cx(
                                                "flex h-10 shrink-0 items-center justify-between px-space-md text-left font-label-md text-label-md transition-colors",
                                                active
                                                    ? "bg-surface-container-low font-semibold text-primary"
                                                    : "text-on-surface hover:bg-surface-container"
                                            )}
                                        >
                                            <span>{option.label}</span>
                                            {active ? (
                                                <MaterialIcon name="check" className="text-[18px] text-primary" />
                                            ) : null}
                                        </button>
                                    );
                                })}
                                {courierMatches.length === 0 ? (
                                    <span className="px-space-md py-space-md font-body-sm text-body-sm text-secondary">
                                        Kurir tidak ditemukan.
                                    </span>
                                ) : null}
                            </div>
                        </div>
                        <p className="font-body-sm text-body-sm text-secondary">
                            {assignCourier
                                ? `Kurir terpilih: ${assignCourier}. Notifikasi tugas dikirim begitu shipment disimpan.`
                                : "Pilih kurir dari daftar untuk melanjutkan."}
                        </p>
                        <div className="flex items-center justify-end gap-space-sm pt-space-xs">
                            <button
                                type="button"
                                onClick={() => setAssignTarget(null)}
                                className="h-9 rounded-md px-space-md font-label-md text-label-md text-secondary transition-colors hover:text-on-surface"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={!assignCourier}
                                className="flex h-9 items-center gap-space-xs rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-40"
                            >
                                <MaterialIcon name="check" className="text-[16px]" />
                                <span>Tugaskan Kurir</span>
                            </button>
                        </div>
                    </form>
                </div>
            ) : null}

            {createOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-margin">
                    <button
                        type="button"
                        aria-label="Tutup dialog"
                        tabIndex={-1}
                        onClick={() => setCreateOpen(false)}
                        className="absolute inset-0 cursor-default bg-inverse-surface/40"
                    />
                    <form
                        onSubmit={createShipment}
                        className="relative flex w-full max-w-md flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg shadow-xl"
                    >
                        <div className="flex items-center justify-between">
                            <h2 className="font-headline-sm text-headline-sm text-on-surface">Buat Shipment</h2>
                            <button
                                type="button"
                                aria-label="Tutup"
                                onClick={() => setCreateOpen(false)}
                                className="flex h-8 w-8 items-center justify-center text-secondary transition-colors hover:text-on-surface"
                            >
                                <MaterialIcon name="close" className="text-[20px]" />
                            </button>
                        </div>
                        <label className="flex flex-col gap-1" htmlFor="new-buyer">
                            <span className="font-label-sm text-label-sm text-secondary uppercase">Pembeli</span>
                            <input
                                id="new-buyer"
                                value={buyer}
                                onChange={(event) => setBuyer(event.target.value)}
                                required
                                placeholder="Nama pembeli"
                                className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                            />
                        </label>
                        <label className="flex flex-col gap-1" htmlFor="new-location">
                            <span className="font-label-sm text-label-sm text-secondary uppercase">Lokasi</span>
                            <input
                                id="new-location"
                                value={location}
                                onChange={(event) => setLocation(event.target.value)}
                                required
                                placeholder="Kecamatan, kota"
                                className="h-9 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                            />
                        </label>
                        <label className="flex flex-col gap-1" htmlFor="new-window">
                            <span className="font-label-sm text-label-sm text-secondary uppercase">Jadwal</span>
                            <select
                                id="new-window"
                                value={deliveryWindow}
                                onChange={(event) => setDeliveryWindow(event.target.value)}
                                className="h-9 cursor-pointer rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                            >
                                {CREATE_SCHEDULES.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <p className="font-body-sm text-body-sm text-secondary">
                            Resi dibuat otomatis dan kurir ditugaskan setelah paket masuk sistem.
                        </p>
                        <div className="flex items-center justify-end gap-space-sm pt-space-xs">
                            <button
                                type="button"
                                onClick={() => setCreateOpen(false)}
                                className="h-9 rounded-md px-space-md font-label-md text-label-md text-secondary transition-colors hover:text-on-surface"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                className="flex h-9 items-center gap-space-xs rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                            >
                                <MaterialIcon name="check" className="text-[16px]" />
                                <span>Simpan Shipment</span>
                            </button>
                        </div>
                    </form>
                </div>
            ) : null}
        </AdminLayout>
    );
}
