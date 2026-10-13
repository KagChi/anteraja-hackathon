import { Head, Link } from "@inertiajs/react";
import { type FormEvent, type ReactNode, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { AUDIT_TIMELINE, REPORT_ISSUES, SHIPMENTS } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { cx } from "@/lib/cx";

const DECISION_OPTIONS = [
    { value: "kembalikan", label: "Kembalikan paket", hint: "Tarik ke DC Cakung untuk klaim retur" },
    { value: "kirim_ulang", label: "Kirim ulang", hint: "Buat resi baru dari hub asal" },
    { value: "lanjutkan", label: "Lanjutkan", hint: "Kompensasi parsial jika pembeli setuju" }
];

const CONDITION = ["Kemasan basah", "Es krim meleleh", "Segel bocor"];

const MANIFEST = [
    { name: "Nugget Ayam Prima (1 kg)", quantity: "1 bungkus" },
    { name: "Es Krim Vanilla & Choco Box", quantity: "2 bungkus" }
];

function Card({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-xl border border-outline-variant bg-surface-container-lowest p-space-lg">
            <h2 className="mb-space-md border-outline-variant border-b pb-space-sm font-headline-sm text-headline-sm text-on-surface">
                {title}
            </h2>
            {children}
        </section>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="font-body-sm text-body-sm text-secondary">{label}</span>
            <div className="flex flex-col font-body-md text-body-md text-on-surface">{children}</div>
        </div>
    );
}

interface ExceptionProps {
    /** Resi opened from the `Kendala` table on the report screen. */
    resi: string;
}

export default function Exception({ resi }: ExceptionProps) {
    const [decision, setDecision] = useState("kembalikan");
    const [reason, setReason] = useState(
        "Kemasan bocor di lokasi penerima, barang tidak layak konsumsi. Tarik kembali ke gudang pusat untuk proses klaim vendor."
    );
    const [saved, setSaved] = useState(false);

    const issue = REPORT_ISSUES.find((row) => row.tracking === resi);
    const shipment = SHIPMENTS.find((row) => row.tracking === resi);
    const buyerName = issue?.buyer ?? shipment?.buyer ?? "Rina Putri";
    const buyerAddress = issue?.address ?? shipment?.location ?? "Jakarta";
    const courierName = issue?.courier ?? shipment?.courier ?? "Budi Santoso";
    const issueLabel = issue?.issue ?? "Kemasan bocor";
    const schedule = shipment?.schedule ?? "Hari ini, 16:00 - 18:00";

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSaved(true);
        window.setTimeout(() => setSaved(false), 3500);
    }

    return (
        <AdminLayout>
            <Head title="Detail Kendala" />

            <div className="mb-space-lg flex flex-col gap-space-xs">
                <Link
                    href="/admin/report"
                    className="flex items-center gap-1 self-start font-body-sm text-body-sm text-secondary transition-colors hover:text-primary"
                >
                    <MaterialIcon name="arrow_back" className="text-[16px]" />
                    <span>Kembali ke Laporan</span>
                </Link>
                <h1 className="font-headline-lg text-headline-lg text-on-surface">Detail Kendala</h1>
                <p className="font-body-sm text-body-sm text-secondary">
                    <span className="font-data-mono text-on-surface">{resi}</span> · {issueLabel} · Dilaporkan hari ini,
                    14:18 WIB
                </p>
            </div>

            <div className="grid grid-cols-1 items-start gap-margin lg:grid-cols-12">
                <div className="flex flex-col gap-margin lg:col-span-7">
                    <Card title="Pengiriman">
                        <div className="grid grid-cols-1 gap-x-margin gap-y-space-md sm:grid-cols-2">
                            <Detail label="Pembeli">
                                {buyerName}
                                <span className="font-body-sm text-body-sm text-secondary">{buyerAddress}</span>
                            </Detail>
                            <Detail label="Kurir">
                                {courierName}
                                <span className="font-body-sm text-body-sm text-secondary">
                                    Motor Box #04 · Hub Jaksel
                                </span>
                            </Detail>
                            <Detail label="Jadwal">{schedule}</Detail>
                            <Detail label="Suhu target">-18°C</Detail>
                        </div>
                        <div className="mt-space-md flex flex-col gap-1 border-outline-variant border-t pt-space-md">
                            <span className="font-body-sm text-body-sm text-secondary">Isi paket</span>
                            {MANIFEST.map((product) => (
                                <div key={product.name} className="flex items-baseline justify-between gap-space-md">
                                    <span className="font-body-md text-body-md text-on-surface">{product.name}</span>
                                    <span className="font-body-sm text-body-sm text-secondary">{product.quantity}</span>
                                </div>
                            ))}
                        </div>
                    </Card>

                    <Card title="Laporan kurir">
                        <p className="font-body-md text-body-md text-on-surface italic">
                            “Kemasan luar kardus basah dan es krim bocor di bagian tutup saat hendak diserahkan ke
                            pembeli. Pembeli menolak paket.”
                        </p>
                        <div className="mt-space-md overflow-hidden rounded-lg border border-outline-variant">
                            <img
                                src="/images/proof-placeholder.svg"
                                alt="Bukti foto lapangan paket rusak"
                                className="h-64 w-full object-cover"
                            />
                            <div className="flex items-center justify-between border-outline-variant border-t bg-surface-container-low px-space-md py-space-sm">
                                <span className="font-body-sm text-body-sm text-secondary">
                                    Foto pemeriksaan · 14:15 WIB
                                </span>
                                <span className="font-body-sm text-body-sm text-secondary">Tolak penerimaan</span>
                            </div>
                        </div>
                        <p className="mt-space-md font-body-sm text-body-sm text-secondary">
                            Kondisi: {CONDITION.join(" · ")}
                        </p>
                    </Card>
                </div>

                <div className="flex flex-col gap-margin lg:col-span-5">
                    <Card title="Keputusan">
                        <form onSubmit={handleSubmit} className="flex flex-col gap-space-md">
                            <div className="flex flex-col gap-1.5">
                                {DECISION_OPTIONS.map((option) => {
                                    const active = decision === option.value;
                                    return (
                                        <label
                                            key={option.value}
                                            className={cx(
                                                "flex cursor-pointer items-start gap-space-sm rounded-lg border px-space-md py-space-sm transition-colors",
                                                active
                                                    ? "border-primary bg-surface-container-low"
                                                    : "border-outline-variant hover:border-secondary"
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="action_type"
                                                value={option.value}
                                                checked={active}
                                                onChange={() => setDecision(option.value)}
                                                className="mt-1 accent-primary"
                                            />
                                            <span className="flex flex-col">
                                                <span className="font-body-md font-medium text-body-md text-on-surface">
                                                    {option.label}
                                                </span>
                                                <span className="font-body-sm text-body-sm text-secondary">
                                                    {option.hint}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            <label className="flex flex-col gap-1" htmlFor="reasonText">
                                <span className="font-body-sm text-body-sm text-secondary">Alasan keputusan</span>
                                <textarea
                                    id="reasonText"
                                    rows={3}
                                    value={reason}
                                    onChange={(event) => setReason(event.target.value)}
                                    placeholder="Tuliskan alasan dan instruksi untuk kurir..."
                                    className="w-full resize-none rounded-lg border border-outline-variant bg-surface-container-lowest p-space-md font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
                                />
                            </label>
                            <button
                                type="submit"
                                className="flex h-10 w-full items-center justify-center gap-space-sm rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                            >
                                <MaterialIcon name="check" className="text-[18px]" />
                                <span>Simpan Keputusan</span>
                            </button>
                            {saved ? (
                                <div className="rounded-lg bg-tertiary-fixed p-space-sm text-center font-body-sm text-body-sm text-on-tertiary-fixed">
                                    Keputusan tersimpan dan tersinkron ke aplikasi kurir.
                                </div>
                            ) : null}
                        </form>
                    </Card>

                    <Card title="Riwayat">
                        <ol className="flex flex-col gap-space-sm">
                            {AUDIT_TIMELINE.map((entry) => (
                                <li key={entry.time + entry.actor} className="flex flex-col gap-0.5">
                                    <div className="flex items-center gap-space-xs font-body-sm text-body-sm text-secondary">
                                        <span className="font-data-mono">{entry.time}</span>
                                        <span>·</span>
                                        <span
                                            className={cx(
                                                "font-medium",
                                                entry.highlight ? "text-primary" : "text-on-surface"
                                            )}
                                        >
                                            {entry.actor}
                                        </span>
                                    </div>
                                    <p className="font-body-sm text-body-sm text-secondary">{entry.action}</p>
                                </li>
                            ))}
                        </ol>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
