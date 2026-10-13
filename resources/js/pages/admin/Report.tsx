import { Head } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { REPORT_ISSUES } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { cx } from "@/lib/cx";

interface MetricCardProps {
    label: string;
    icon: string;
    value: string;
    unit: string;
    footerLabel: string;
    footerValue: string;
    accent?: boolean;
}

function MetricCard({ label, icon, value, unit, footerLabel, footerValue, accent = false }: MetricCardProps) {
    return (
        <div className="flex flex-col justify-between rounded-lg border border-outline-variant bg-surface-container-lowest p-space-lg">
            <div className="flex items-center justify-between">
                <span className="font-label-sm text-label-sm uppercase tracking-wider text-secondary">{label}</span>
                <MaterialIcon name={icon} className={cx("text-[18px]", accent ? "text-primary" : "text-secondary")} />
            </div>
            <div className="mt-space-md flex items-baseline gap-space-xs">
                <span className={cx("font-headline-lg text-headline-lg", accent ? "text-primary" : "text-on-surface")}>
                    {value}
                </span>
                <span className="font-body-sm text-body-sm text-secondary">{unit}</span>
            </div>
            <div className="mt-space-sm flex items-center justify-between border-t border-outline-variant pt-space-xs">
                <span className="font-label-sm text-label-sm text-secondary">{footerLabel}</span>
                <span
                    className={cx(
                        "font-data-mono text-data-mono",
                        accent ? "font-semibold text-primary" : "text-on-surface"
                    )}
                >
                    {footerValue}
                </span>
            </div>
        </div>
    );
}

export default function Report() {
    return (
        <AdminLayout>
            <Head title="Laporan Harian" />

            <div className="flex flex-col justify-between gap-space-sm rounded-lg border border-outline-variant bg-surface-container-lowest p-space-lg md:flex-row md:items-center">
                <div className="flex flex-col">
                    <h1 className="font-headline-md text-headline-md text-on-surface">Laporan Harian</h1>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        Ringkasan performa dan audit cold-chain harian
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-space-md">
                    <div className="flex items-center gap-space-xs rounded-sm border border-outline-variant bg-surface-container-low px-space-md py-1.5">
                        <MaterialIcon name="calendar_today" className="text-[16px] text-secondary" />
                        <span className="font-body-sm text-body-sm text-on-surface">Hari ini: 13 Oktober 2026</span>
                    </div>
                    <button
                        type="button"
                        className="px-1 py-1 font-label-md text-label-md text-primary transition-colors hover:underline"
                    >
                        Ubah Tanggal
                    </button>
                    <div className="h-4 w-px bg-outline-variant" />
                    <button
                        type="button"
                        className="flex items-center gap-space-xs rounded-sm border border-outline-variant bg-surface-container-lowest px-space-md py-1.5 font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                    >
                        <MaterialIcon name="download" className="text-[16px]" />
                        <span>Unduh CSV</span>
                    </button>
                </div>
            </div>

            <div className="mt-space-lg grid grid-cols-1 gap-space-md md:grid-cols-3">
                <MetricCard
                    label="Total Pengiriman Hari Ini"
                    icon="inventory_2"
                    value="1,428"
                    unit="resi"
                    footerLabel="Kapasitas Armada Cold-Chain"
                    footerValue="94.2%"
                />
                <MetricCard
                    label="Pengiriman Berhasil Diterima"
                    icon="check_circle"
                    value="1,142"
                    unit="resi"
                    footerLabel="Rasio Keberhasilan"
                    footerValue="79.9%"
                />
                <MetricCard
                    label="Total Kasus Kendala Dilaporkan"
                    icon="report_problem"
                    value="7"
                    unit="kasus"
                    footerLabel="Tingkat Insiden"
                    footerValue="0.49%"
                    accent
                />
            </div>

            <div className="mt-space-lg flex flex-col overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest">
                <div className="flex flex-col justify-between gap-space-xs border-b border-outline-variant p-space-lg sm:flex-row sm:items-center">
                    <div>
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Daftar Paket Berkendala</h2>
                        <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                            Rekapitulasi pengiriman dengan isu operasional dan status resolusi
                        </p>
                    </div>
                    <div className="flex items-center gap-space-xs">
                        <span className="font-label-sm text-label-sm text-secondary">Filter Kategori:</span>
                        <span className="rounded-sm border border-outline-variant bg-surface-container-low px-2 py-0.5 font-label-sm text-label-sm text-on-surface">
                            Semua Isu (5 Tampil)
                        </span>
                    </div>
                </div>
                <div className="w-full overflow-x-auto">
                    <table className="w-full border-collapse text-left">
                        <thead>
                            <tr className="h-9 border-b border-outline-variant bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                                <th className="px-space-lg font-medium">Resi</th>
                                <th className="px-space-md font-medium">Pembeli</th>
                                <th className="px-space-md font-medium">Kurir</th>
                                <th className="px-space-md font-medium">Jenis Kendala</th>
                                <th className="px-space-md font-medium">Keputusan</th>
                                <th className="px-space-lg text-right font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-outline-variant font-body-sm text-body-sm text-on-surface">
                            {REPORT_ISSUES.map((row) => (
                                <tr
                                    key={row.tracking}
                                    className="h-10 transition-colors hover:bg-surface-container-low"
                                >
                                    <td className="whitespace-nowrap px-space-lg font-data-mono text-data-mono text-on-surface">
                                        {row.tracking}
                                    </td>
                                    <td className="px-space-md">{row.buyer}</td>
                                    <td className="px-space-md text-secondary">{row.courier}</td>
                                    <td className="px-space-md">
                                        <span
                                            className={row.issueAlert ? "font-medium text-primary" : "text-on-surface"}
                                        >
                                            {row.issue}
                                        </span>
                                    </td>
                                    <td className="px-space-md text-secondary">{row.decision}</td>
                                    <td className="whitespace-nowrap px-space-lg text-right">
                                        <span
                                            className={cx(
                                                "font-label-sm text-label-sm",
                                                row.statusActive ? "text-secondary" : "text-on-surface"
                                            )}
                                        >
                                            {row.status}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-outline-variant bg-surface-container-lowest px-space-lg py-space-sm">
                    <span className="font-label-sm text-label-sm text-secondary">
                        Menampilkan 5 dari 7 total kendala hari ini
                    </span>
                    <div className="flex items-center gap-space-xs">
                        <button
                            type="button"
                            disabled
                            className="rounded-sm border border-outline-variant px-2 py-1 font-label-sm text-label-sm text-secondary hover:text-on-surface disabled:opacity-50"
                        >
                            Sebelumnya
                        </button>
                        <button
                            type="button"
                            className="rounded-sm border border-outline-variant px-2 py-1 font-label-sm text-label-sm text-secondary hover:text-on-surface"
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
