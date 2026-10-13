import { Head, Link } from "@inertiajs/react";
import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { REPORT_ISSUES } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { cx } from "@/lib/cx";

const PAGE_SIZE = 5;

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
                <span className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">{label}</span>
                <MaterialIcon name={icon} className={cx("text-[18px]", accent ? "text-primary" : "text-secondary")} />
            </div>
            <div className="mt-space-md flex items-baseline gap-space-xs">
                <span className={cx("font-headline-lg text-headline-lg", accent ? "text-primary" : "text-on-surface")}>
                    {value}
                </span>
                <span className="font-body-sm text-body-sm text-secondary">{unit}</span>
            </div>
            <div className="mt-space-sm flex items-center justify-between border-outline-variant border-t pt-space-xs">
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
    const [page, setPage] = useState(1);

    const pageCount = Math.max(1, Math.ceil(REPORT_ISSUES.length / PAGE_SIZE));
    const currentPage = Math.min(page, pageCount);
    const first = (currentPage - 1) * PAGE_SIZE;
    const pageRows = REPORT_ISSUES.slice(first, first + PAGE_SIZE);
    const rangeLabel = `${first + 1}-${Math.min(first + PAGE_SIZE, REPORT_ISSUES.length)}`;

    return (
        <AdminLayout>
            <Head title="Laporan" />

            <div className="flex flex-col justify-between gap-space-md pb-space-lg md:flex-row md:items-center">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Laporan</h1>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        Ringkasan performa dan penanganan kendala cold-chain harian
                    </p>
                </div>
                <div className="flex h-9 items-center gap-space-xs self-start rounded-md border border-outline-variant bg-surface-container-low px-space-md font-body-sm text-body-sm text-on-surface md:self-center">
                    <MaterialIcon name="calendar_today" className="text-[16px] text-secondary" />
                    <span>Hari ini: 13 Oktober 2026</span>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-space-md md:grid-cols-3">
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

            <div className="mt-space-lg flex w-full flex-col overflow-hidden rounded-lg bg-surface-container-lowest">
                <div className="flex flex-col justify-between gap-space-xs p-space-lg pb-space-md sm:flex-row sm:items-center">
                    <div>
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Daftar Kendala</h2>
                        <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                            Pilih detail untuk membuka penanganan dan keputusan dispatcher
                        </p>
                    </div>
                </div>
                <div className="w-full overflow-x-auto">
                    <table className="w-full border-collapse text-left">
                        <thead>
                            <tr className="h-9 bg-surface-container-low">
                                <th className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                    Resi
                                </th>
                                <th className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                    Pembeli
                                </th>
                                <th className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                    Kurir
                                </th>
                                <th className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                    Jenis Kendala
                                </th>
                                <th className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                    Keputusan
                                </th>
                                <th className="px-space-lg font-label-sm font-semibold text-label-sm text-secondary uppercase tracking-wider">
                                    Status
                                </th>
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
                                        {row.courier}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        <span
                                            className={cx(
                                                "font-body-sm text-body-sm",
                                                row.issueAlert ? "font-medium text-primary" : "text-on-surface"
                                            )}
                                        >
                                            {row.issue}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg font-body-sm text-body-sm text-secondary">
                                        {row.decision}
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg">
                                        <span
                                            className={cx(
                                                "font-label-sm text-label-sm",
                                                row.statusActive ? "text-secondary" : "text-on-surface"
                                            )}
                                        >
                                            {row.status}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-space-lg text-right">
                                        <Link
                                            href={`/admin/exception/${row.tracking}`}
                                            aria-label={`Lihat detail ${row.tracking}`}
                                            title="Lihat detail"
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-secondary transition-colors hover:bg-surface-container hover:text-primary"
                                        >
                                            <MaterialIcon name="visibility" className="text-[18px]" />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="flex h-12 items-center justify-between px-space-lg">
                    <span className="font-body-sm text-body-sm text-secondary">
                        Menampilkan {rangeLabel} dari {REPORT_ISSUES.length} kendala
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
        </AdminLayout>
    );
}
