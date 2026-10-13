import { Head, Link } from "@inertiajs/react";
import { type FormEvent, type ReactNode, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { AUDIT_TIMELINE, REPORT_ISSUES, SHIPMENTS } from "@/data/admin";
import { AdminLayout } from "@/layouts";
import { cx } from "@/lib/cx";

const DECISION_OPTIONS = [
    { value: "kembalikan", label: "Kembalikan Paket", hint: "Paket ditarik ke DC Cakung untuk klaim retur" },
    { value: "kirim_ulang", label: "Kirim Ulang", hint: "Buat resi baru slot pengiriman terdekat dari hub asal" },
    { value: "lanjutkan", label: "Lanjutkan", hint: "Kompensasi parsial jika disetujui pembeli" }
];

const CHECKLIST = [
    { label: "Kemasan Basah/Rusak", value: "Ya (Terbukti)" },
    { label: "Es Krim Melunak", value: "Ya (Meleleh)" },
    { label: "Segel Terbuka", value: "Ya (Bocor)" }
];

const MANIFEST = [
    { name: "Nugget Ayam Prima (1 kg)", quantity: "1 Qty / Beku" },
    { name: "Es Krim Vanilla & Choco Box", quantity: "2 Qty / Beku" }
];

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col">
            <span className="font-label-sm text-label-sm text-secondary uppercase">{label}</span>
            <div className="mt-0.5 flex flex-col">{children}</div>
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

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSaved(true);
        window.setTimeout(() => setSaved(false), 3500);
    }

    return (
        <AdminLayout>
            <Head title="Detail Kendala" />

            <div className="mb-space-lg flex flex-col gap-space-xs border-outline-variant border-b pb-space-lg">
                <div className="flex items-center justify-between">
                    <nav className="flex items-center gap-space-xs font-label-sm text-label-sm text-secondary">
                        <Link href="/admin/report" className="transition-colors hover:text-primary">
                            Laporan
                        </Link>
                        <span>/</span>
                        <Link href="/admin/report" className="transition-colors hover:text-primary">
                            Kendala
                        </Link>
                        <span>/</span>
                        <span className="font-semibold text-on-surface">{resi}</span>
                    </nav>
                    <Link
                        href="/admin/report"
                        className="flex items-center gap-1 font-label-md text-label-md text-secondary transition-colors hover:text-primary"
                    >
                        <MaterialIcon name="arrow_back" className="text-[16px]" />
                        <span>Kembali ke Laporan</span>
                    </Link>
                </div>
                <div className="mt-space-xs flex flex-wrap items-center justify-between gap-space-md">
                    <div className="flex items-center gap-space-md">
                        <h1 className="font-headline-lg text-headline-lg text-on-surface">Detail Kendala: {resi}</h1>
                        <div className="flex items-center gap-1.5 rounded-md bg-surface-container-high px-2 py-0.5 font-label-sm text-label-sm text-primary uppercase tracking-wide">
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-primary" />
                            <span>Kendala Aktif</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-space-sm font-data-mono text-data-mono text-secondary">
                        <MaterialIcon name="schedule" className="text-[18px]" />
                        <span>Dilaporkan: Hari ini, 14:18 WIB</span>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 items-start gap-margin lg:grid-cols-12">
                <div className="flex flex-col gap-margin lg:col-span-7">
                    <section className="rounded-xl border border-outline-variant bg-surface-container-lowest p-space-lg">
                        <div className="mb-space-md flex items-center justify-between border-outline-variant border-b pb-space-md">
                            <div className="flex items-center gap-space-sm">
                                <MaterialIcon name="local_shipping" className="text-[20px] text-secondary" />
                                <h2 className="font-headline-sm text-headline-sm text-on-surface">
                                    Informasi Pengiriman
                                </h2>
                            </div>
                            <span className="rounded-sm bg-surface-container px-2 py-0.5 font-label-sm text-label-sm text-secondary">
                                Cold Box L3
                            </span>
                        </div>
                        <div className="grid grid-cols-1 gap-x-margin gap-y-space-md md:grid-cols-2">
                            <Field label="Nomor Resi / AWB">
                                <span className="font-data-mono font-semibold text-data-mono text-on-surface">
                                    {resi}
                                </span>
                            </Field>
                            <Field label="Suhu Target Toleransi">
                                <div className="flex items-center gap-1.5">
                                    <span className="h-2 w-2 rounded-full bg-tertiary" />
                                    <span className="font-data-mono text-data-mono text-on-surface">
                                        -18°C (Frozen Strict)
                                    </span>
                                </div>
                            </Field>
                            <Field label="Pembeli">
                                <span className="font-body-md font-medium text-body-md text-on-surface">
                                    {buyerName}
                                </span>
                                <span className="font-body-sm text-body-sm text-secondary">{buyerAddress}</span>
                            </Field>
                            <Field label="Kontak Pembeli">
                                <span className="font-data-mono text-data-mono text-on-surface">0812-9876-5432</span>
                            </Field>
                            <Field label="Kurir Ditugaskan">
                                <span className="font-body-md font-medium text-body-md text-on-surface">
                                    {courierName}
                                </span>
                                <span className="font-body-sm text-body-sm text-secondary">
                                    Armada: Motor Box #04 (Hub Jaksel)
                                </span>
                            </Field>
                            <Field label="Waktu Pelaporan">
                                <span className="font-data-mono text-data-mono text-on-surface">14:18:22 WIB</span>
                            </Field>
                            <Field label="Jenis Kendala">
                                <span className="font-body-md font-medium text-body-md text-primary">{issueLabel}</span>
                                <span className="font-body-sm text-body-sm text-secondary">
                                    Dilaporkan oleh {courierName}
                                </span>
                            </Field>
                        </div>
                        <div className="mt-space-lg border-outline-variant border-t pt-space-md">
                            <span className="mb-space-xs block font-label-sm text-label-sm text-secondary uppercase">
                                Manifest Produk
                            </span>
                            <div className="flex flex-col gap-1.5 rounded-lg bg-surface-container-low p-space-md">
                                {MANIFEST.map((product) => (
                                    <div
                                        key={product.name}
                                        className="flex items-center justify-between text-on-surface"
                                    >
                                        <span className="font-body-md font-medium text-body-md">• {product.name}</span>
                                        <span className="font-data-mono text-data-mono text-secondary">
                                            {product.quantity}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="flex flex-col gap-space-md rounded-xl border border-outline-variant bg-surface-container-lowest p-space-lg">
                        <div className="flex items-center justify-between border-outline-variant border-b pb-space-md">
                            <div className="flex items-center gap-space-sm">
                                <MaterialIcon name="report_problem" className="text-[20px] text-primary" />
                                <h2 className="font-headline-sm text-headline-sm text-on-surface">
                                    Laporan Kurir & Bukti Visual
                                </h2>
                            </div>
                            <span className="rounded-sm bg-error-container px-2 py-0.5 font-label-sm font-semibold text-label-sm text-on-error-container">
                                Tolak Penerimaan
                            </span>
                        </div>
                        <div className="flex flex-col gap-1">
                            <span className="font-label-sm text-label-sm text-secondary uppercase">
                                Keterangan Suara / Catatan Kurir
                            </span>
                            <div className="rounded-lg border-primary border-l-2 bg-surface-container-low p-space-md">
                                <p className="font-body-md text-body-md text-on-surface italic">
                                    "Kemasan luar kardus basah dan es krim bocor di bagian tutup saat hendak diserahkan
                                    ke pembeli. Pembeli menolak paket."
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-col gap-space-xs">
                            <span className="font-label-sm text-label-sm text-secondary uppercase">
                                Bukti Foto Lapangan
                            </span>
                            <div className="relative overflow-hidden rounded-lg border border-outline-variant bg-surface-container">
                                <img
                                    src="/images/proof-placeholder.svg"
                                    alt="Bukti foto lapangan paket rusak"
                                    className="h-80 w-full object-cover"
                                />
                                <div className="flex items-center justify-between border-outline-variant border-t bg-surface-container-lowest p-space-sm">
                                    <span className="flex items-center gap-1 font-body-sm text-body-sm text-secondary">
                                        <MaterialIcon name="photo_camera" className="text-[16px]" />
                                        Foto pemeriksaan kurir: Kemasan basah dan bocor
                                    </span>
                                    <span className="font-data-mono text-data-mono text-secondary">14:15 WIB</span>
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-col gap-space-xs pt-space-xs">
                            <span className="font-label-sm text-label-sm text-secondary uppercase">
                                Checklist Verifikasi Kondisi Paket
                            </span>
                            <div className="grid grid-cols-1 gap-space-sm sm:grid-cols-3">
                                {CHECKLIST.map((item) => (
                                    <div
                                        key={item.label}
                                        className="flex items-center gap-space-sm rounded-sm border border-outline-variant bg-surface-container-low p-space-sm"
                                    >
                                        <MaterialIcon name="check_circle" className="text-[18px] text-primary" />
                                        <div className="flex flex-col">
                                            <span className="font-label-sm text-label-sm text-secondary">
                                                {item.label}
                                            </span>
                                            <span className="font-label-md text-label-md text-on-surface">
                                                {item.value}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                </div>

                <div className="flex flex-col gap-margin lg:col-span-5">
                    <section className="rounded-xl border border-outline-variant bg-surface-container-lowest p-space-lg">
                        <div className="mb-space-md flex items-center gap-space-sm border-outline-variant border-b pb-space-md">
                            <MaterialIcon name="gavel" className="text-[20px] text-primary" />
                            <h2 className="font-headline-sm text-headline-sm text-on-surface">Keputusan Dispatcher</h2>
                        </div>
                        <form onSubmit={handleSubmit} className="flex flex-col gap-space-lg">
                            <div className="flex flex-col gap-space-sm">
                                <span className="font-label-sm text-label-sm text-secondary uppercase">
                                    Pilih Tindakan Operasional *
                                </span>
                                <div className="flex flex-col gap-2">
                                    {DECISION_OPTIONS.map((option) => {
                                        const active = decision === option.value;
                                        return (
                                            <label
                                                key={option.value}
                                                className={cx(
                                                    "flex cursor-pointer items-start gap-space-sm rounded-lg border p-space-md transition-all",
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
                                                    className="mt-0.5 accent-primary"
                                                />
                                                <div className="flex flex-col">
                                                    <span className="font-label-md text-label-md text-on-surface">
                                                        {option.label}
                                                    </span>
                                                    <span className="font-body-sm text-body-sm text-secondary">
                                                        {option.hint}
                                                    </span>
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <div className="flex items-center justify-between">
                                    <label
                                        htmlFor="reasonText"
                                        className="font-label-sm text-label-sm text-secondary uppercase"
                                    >
                                        Alasan Keputusan *
                                    </label>
                                    <span className="font-label-sm text-label-sm text-secondary">Wajib diisi</span>
                                </div>
                                <textarea
                                    id="reasonText"
                                    rows={4}
                                    value={reason}
                                    onChange={(event) => setReason(event.target.value)}
                                    placeholder="Tuliskan alasan penanganan dan instruksi untuk kurir..."
                                    className="w-full resize-none rounded-lg border border-outline-variant bg-surface-container-lowest p-space-md font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
                                />
                            </div>
                            <div className="pt-space-xs">
                                <button
                                    type="submit"
                                    className="flex h-10 w-full items-center justify-center gap-space-sm rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary uppercase tracking-wider transition-colors hover:bg-primary-container"
                                >
                                    <MaterialIcon name="verified" className="text-[18px]" />
                                    <span>Simpan Keputusan</span>
                                </button>
                                {saved ? (
                                    <div className="mt-space-sm rounded-sm bg-tertiary-fixed p-space-sm text-center font-label-sm text-label-sm text-on-tertiary-fixed">
                                        Keputusan berhasil disimpan & disinkronkan ke aplikasi kurir.
                                    </div>
                                ) : null}
                            </div>
                        </form>
                    </section>

                    <section className="rounded-xl border border-outline-variant bg-surface-container-lowest p-space-lg">
                        <div className="mb-space-md flex items-center justify-between border-outline-variant border-b pb-space-md">
                            <div className="flex items-center gap-space-sm">
                                <MaterialIcon name="history" className="text-[20px] text-secondary" />
                                <h2 className="font-headline-sm text-headline-sm text-on-surface">
                                    Riwayat Penanganan (Audit)
                                </h2>
                            </div>
                            <span className="font-label-sm text-label-sm text-secondary">
                                {AUDIT_TIMELINE.length} Catatan
                            </span>
                        </div>
                        <div className="relative flex flex-col space-y-4 border-outline-variant border-l pl-4">
                            {AUDIT_TIMELINE.map((entry) => (
                                <div key={entry.time + entry.actor} className="relative flex flex-col gap-0.5">
                                    <span
                                        className={cx(
                                            "absolute top-1.5 -left-[21px] h-2 w-2 rounded-full",
                                            entry.highlight ? "bg-primary" : "bg-outline"
                                        )}
                                    />
                                    <div className="flex items-center gap-space-xs font-data-mono text-data-mono text-secondary">
                                        <span>{entry.time}</span>
                                        <span>•</span>
                                        <span className="font-semibold text-on-surface">{entry.actor}</span>
                                    </div>
                                    <p className="font-body-sm text-body-sm text-secondary">{entry.action}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AdminLayout>
    );
}
