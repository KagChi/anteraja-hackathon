import { Head } from "@inertiajs/react";
import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { DELIVERY_HISTORY } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { cx } from "@/lib/cx";
import { downloadCsv } from "@/lib/download";

export default function History() {
    const [query, setQuery] = useState("");

    const needle = query.trim().toLowerCase();
    const rows = DELIVERY_HISTORY.filter((row) => {
        if (!needle) return true;
        return [row.tracking, row.buyer, row.courier, row.status].join(" ").toLowerCase().includes(needle);
    });

    return (
        <AdminLayout>
            <Head title="Riwayat Pengiriman" />

            <div className="flex flex-col justify-between gap-space-md pb-space-lg md:flex-row md:items-center">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Riwayat Pengiriman</h1>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        Arsip pengiriman cold-chain yang sudah selesai
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() =>
                        downloadCsv("anteraja-frozen-riwayat.csv", [
                            ["Resi", "Pembeli", "Kurir", "Selesai", "Status"],
                            ...rows.map((row) => [row.tracking, row.buyer, row.courier, row.completedAt, row.status])
                        ])
                    }
                    className="flex h-9 items-center gap-space-xs self-start rounded-md bg-surface-container-lowest px-space-md font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                >
                    <MaterialIcon name="file_download" className="text-[16px]" />
                    <span>Ekspor</span>
                </button>
            </div>

            <div className="mb-space-lg flex w-full items-center rounded-lg bg-surface-container-lowest p-space-md">
                <div className="relative w-full md:w-72">
                    <MaterialIcon name="search" className="absolute left-2.5 top-2 text-[16px] text-secondary" />
                    <input
                        type="text"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Cari resi, pembeli, kurir..."
                        className="h-8 w-full rounded-md bg-surface-container-lowest pl-8 pr-space-sm font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:bg-surface-container-low focus:outline-none"
                    />
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
                                    Kurir
                                </th>
                                <th className="px-space-lg font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">
                                    Selesai
                                </th>
                                <th className="px-space-lg font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-surface-container">
                            {rows.map((row) => {
                                const done = row.status === "Diterima";
                                return (
                                    <tr
                                        key={row.tracking}
                                        className="h-10 transition-colors hover:bg-surface-container-low"
                                    >
                                        <td className="whitespace-nowrap px-space-lg">
                                            <span className="font-data-mono text-body-sm font-semibold text-on-surface">
                                                {row.tracking}
                                            </span>
                                        </td>
                                        <td className="px-space-lg font-body-sm text-body-sm text-on-surface">
                                            {row.buyer}
                                        </td>
                                        <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-on-surface">
                                            {row.courier}
                                        </td>
                                        <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-secondary">
                                            {row.completedAt}
                                        </td>
                                        <td className="whitespace-nowrap px-space-lg">
                                            <div className="flex items-center gap-1.5">
                                                <span
                                                    className={cx(
                                                        "h-2 w-2 flex-shrink-0 rounded-full",
                                                        done ? "bg-tertiary-container" : "bg-outline"
                                                    )}
                                                />
                                                <span
                                                    className={cx(
                                                        "font-body-sm text-body-sm",
                                                        done ? "text-tertiary" : "text-secondary"
                                                    )}
                                                >
                                                    {row.status}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-space-lg py-space-xl text-center font-body-sm text-body-sm text-secondary"
                                    >
                                        Tidak ada riwayat yang cocok.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
                <div className="flex h-12 items-center px-space-lg">
                    <span className="font-body-sm text-body-sm text-secondary">
                        Menampilkan {rows.length} pengiriman terakhir
                    </span>
                </div>
            </div>
        </AdminLayout>
    );
}
