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
import { downloadCsv } from "@/lib/download";

const PAGE_SIZE = 6;

/** Delivery windows offered when creating a shipment from the console. */
const CREATE_SCHEDULES = [
    "Hari ini, 13:00 - 15:00",
    "Hari ini, 15:00 - 17:00",
    "Besok, 09:00 - 11:00",
    "Besok, 13:00 - 15:00"
];

const COURIERS = COURIER_OPTIONS.filter((option) => option.value !== "unassigned");

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
                className="h-8 cursor-pointer appearance-none rounded-md bg-surface-container-lowest px-space-sm pr-7 font-body-sm text-body-sm text-on-surface focus:outline-none"
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
                className="pointer-events-none absolute right-1.5 top-2 text-[16px] text-secondary"
            />
        </div>
    );
}

interface ShipmentProps {
    /** Search term handed over by the console header search. */
    q?: string | null;
}

export default function Shipment({ q }: ShipmentProps) {
    const [items, setItems] = useState<ShipmentRow[]>(SHIPMENTS);
    const [query, setQuery] = useState(q ?? "");
    const [status, setStatus] = useState("");
    const [courier, setCourier] = useState("");
    const [schedule, setSchedule] = useState("today");
    const [page, setPage] = useState(1);
    const [assigning, setAssigning] = useState<string | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const [buyer, setBuyer] = useState("");
    const [location, setLocation] = useState("");
    const [window, setWindow] = useState(CREATE_SCHEDULES[0]);

    useEffect(() => {
        setQuery(q ?? "");
        setPage(1);
    }, [q]);

    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return items.filter((row) => {
            if (needle) {
                const haystack = [row.tracking, row.buyer, row.location, row.courier ?? ""].join(" ").toLowerCase();
                if (!haystack.includes(needle)) return false;
            }
            if (status && row.statusKey !== status) return false;
            if (courier === "unassigned" && row.courier !== null) return false;
            if (courier && courier !== "unassigned" && row.courier !== courier) return false;
            return true;
        });
    }, [items, query, status, courier]);

    const pageCount = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
    const currentPage = Math.min(page, pageCount);
    const first = (currentPage - 1) * PAGE_SIZE;
    const pageRows = rows.slice(first, first + PAGE_SIZE);
    const rangeLabel = rows.length === 0 ? "0" : `${first + 1}-${Math.min(first + PAGE_SIZE, rows.length)}`;

    function exportCsv() {
        downloadCsv("anteraja-frozen-shipment.csv", [
            ["Resi", "Pembeli", "Lokasi", "Jadwal", "Kurir", "Status"],
            ...rows.map((row) => [
                row.tracking,
                row.buyer,
                row.location,
                row.schedule,
                row.courier ?? "Belum Ditugaskan",
                row.status
            ])
        ]);
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
        setAssigning(null);
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
                schedule: window,
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
        setWindow(CREATE_SCHEDULES[0]);
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
                        Daftar pengiriman aktif dan penugasan kurir makanan beku
                    </p>
                </div>
                <div className="flex items-center gap-space-sm">
                    <button
                        type="button"
                        onClick={exportCsv}
                        className="flex h-9 items-center gap-space-xs rounded-md bg-surface-container-lowest px-space-md font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                    >
                        <MaterialIcon name="file_download" className="text-[16px]" />
                        <span>Ekspor</span>
                    </button>
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

            <div className="mb-space-lg flex w-full flex-col items-stretch justify-between gap-space-md rounded-lg bg-surface-container-lowest p-space-md md:flex-row md:items-center">
                <div className="flex flex-1 flex-wrap items-center gap-space-sm">
                    <div className="relative w-full md:w-72">
                        <MaterialIcon name="search" className="absolute left-2.5 top-2 text-[16px] text-secondary" />
                        <input
                            type="text"
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setPage(1);
                            }}
                            placeholder="Cari resi, pembeli, lokasi..."
                            className="h-8 w-full rounded-md bg-surface-container-lowest pl-8 pr-space-sm font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:bg-surface-container-low focus:outline-none"
                        />
                    </div>
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
                        onChange={setSchedule}
                        placeholder="Jadwal: Hari Ini"
                        options={SCHEDULE_OPTIONS}
                    />
                </div>
                <div className="flex items-center gap-space-xs self-end md:self-center">
                    <span className="font-label-sm text-label-sm text-secondary">Suhu Box Standar:</span>
                    <span className="rounded-sm bg-surface-container px-space-xs py-0.5 font-data-mono text-label-sm text-on-surface">
                        -18°C ~ -25°C
                    </span>
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
                                        className="px-space-lg font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary"
                                    >
                                        {heading}
                                    </th>
                                ))}
                                <th className="px-space-lg text-right font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">
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
                                        <span className="font-data-mono text-body-sm font-semibold text-on-surface">
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
                                            <span className="font-body-sm text-body-sm italic text-outline">
                                                Belum Ditugaskan
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        <div className="flex items-center gap-1.5">
                                            <span
                                                className={cx(
                                                    "h-2 w-2 flex-shrink-0 rounded-full",
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
                                        {row.hasIssue ? (
                                            <Link
                                                href="/admin/exception"
                                                className="inline-flex items-center font-label-md text-label-md text-primary hover:underline"
                                            >
                                                Detail Kendala
                                            </Link>
                                        ) : assigning === row.tracking ? (
                                            <select
                                                aria-label="Pilih kurir"
                                                defaultValue=""
                                                onChange={(event) => {
                                                    if (event.target.value) assign(row.tracking, event.target.value);
                                                }}
                                                onBlur={() => setAssigning(null)}
                                                className="h-7 cursor-pointer rounded-sm border border-outline-variant bg-surface-container-lowest px-1 font-label-md text-label-md text-on-surface focus:outline-none"
                                            >
                                                <option value="">Pilih kurir...</option>
                                                {COURIERS.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : row.courier === null ? (
                                            <button
                                                type="button"
                                                onClick={() => setAssigning(row.tracking)}
                                                className="inline-flex h-7 items-center gap-1 rounded-sm bg-surface-container px-space-sm font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-high"
                                            >
                                                Tugaskan
                                            </button>
                                        ) : (
                                            <Link
                                                href="/admin/exception"
                                                className="inline-flex items-center font-label-md text-label-md text-on-surface transition-colors hover:text-primary"
                                            >
                                                Detail
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

            {createOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 p-margin">
                    <form
                        onSubmit={createShipment}
                        className="flex w-full max-w-md flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg shadow-xl"
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
                            <span className="font-label-sm text-label-sm uppercase text-secondary">Pembeli</span>
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
                            <span className="font-label-sm text-label-sm uppercase text-secondary">Lokasi</span>
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
                            <span className="font-label-sm text-label-sm uppercase text-secondary">Jadwal</span>
                            <select
                                id="new-window"
                                value={window}
                                onChange={(event) => setWindow(event.target.value)}
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
