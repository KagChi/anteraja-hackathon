import { Head, Link } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { COURIER_OPTIONS, SCHEDULE_OPTIONS, SHIPMENTS, STATUS_OPTIONS, STATUS_TONE } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { cx } from "@/lib/cx";

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

export default function Shipment() {
    const [query, setQuery] = useState("");
    const [status, setStatus] = useState("");
    const [courier, setCourier] = useState("");
    const [schedule, setSchedule] = useState("today");

    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return SHIPMENTS.filter((row) => {
            if (needle) {
                const haystack = [row.tracking, row.buyer, row.location, row.courier ?? ""].join(" ").toLowerCase();
                if (!haystack.includes(needle)) return false;
            }
            if (status && row.statusKey !== status) return false;
            if (courier === "unassigned" && row.courier !== null) return false;
            if (courier && courier !== "unassigned" && row.courier !== courier) return false;
            return true;
        });
    }, [query, status, courier]);

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
                        className="flex h-9 items-center gap-space-xs rounded-md bg-surface-container-lowest px-space-md font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                    >
                        <MaterialIcon name="file_download" className="text-[16px]" />
                        <span>Ekspor</span>
                    </button>
                    <button
                        type="button"
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
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Cari resi, pembeli, lokasi..."
                            className="h-8 w-full rounded-md bg-surface-container-lowest pl-8 pr-space-sm font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:bg-surface-container-low focus:outline-none"
                        />
                    </div>
                    <FilterSelect
                        value={status}
                        onChange={setStatus}
                        placeholder="Semua Status"
                        options={STATUS_OPTIONS}
                    />
                    <FilterSelect
                        value={courier}
                        onChange={setCourier}
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
                                <th className="px-space-lg font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">
                                    Resi
                                </th>
                                <th className="px-space-lg font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">
                                    Pembeli
                                </th>
                                <th className="px-space-lg font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">
                                    Jadwal
                                </th>
                                <th className="px-space-lg font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">
                                    Kurir
                                </th>
                                <th className="px-space-lg font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">
                                    Status
                                </th>
                                <th className="px-space-lg text-right font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-surface-container">
                            {rows.map((row) => (
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
                                        ) : row.courier === null ? (
                                            <button
                                                type="button"
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
                        Menampilkan {rows.length === 0 ? 0 : "1-" + rows.length} dari 142 pengiriman
                    </span>
                    <div className="flex items-center gap-space-xs">
                        <button
                            type="button"
                            disabled
                            className="h-8 rounded-sm bg-surface-container-low px-space-sm font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface disabled:opacity-40"
                        >
                            Sebelumnya
                        </button>
                        <button
                            type="button"
                            className="flex h-8 w-8 items-center justify-center rounded-sm bg-primary font-label-md text-label-md text-on-primary"
                        >
                            1
                        </button>
                        {["2", "3"].map((page) => (
                            <button
                                key={page}
                                type="button"
                                className="flex h-8 w-8 items-center justify-center rounded-sm bg-surface-container-lowest font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                            >
                                {page}
                            </button>
                        ))}
                        <span className="px-1 font-body-sm text-secondary">...</span>
                        <button
                            type="button"
                            className="flex h-8 w-8 items-center justify-center rounded-sm bg-surface-container-lowest font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                        >
                            24
                        </button>
                        <button
                            type="button"
                            className="h-8 rounded-sm bg-surface-container-low px-space-sm font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                        >
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
