import { Head } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ISSUE } from "@/data/frozen";
import { FrozenLayout } from "@/layouts";
import { cx } from "@/lib/cx";

export default function Issue() {
    const data = ISSUE;

    return (
        <FrozenLayout title="Detail Produk" backTo="/frozen/tracking">
            <Head title="Kendala Pengiriman" />

            <div className="flex w-full flex-col gap-space-lg pb-space-xl pt-space-md">
                <section className="flex flex-col gap-space-sm rounded-lg bg-surface-container-lowest p-space-md">
                    <div className="flex items-center justify-between">
                        <span className="font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                            No. Resi Pengiriman
                        </span>
                        <span className="font-data-mono font-label-md text-label-md font-semibold text-on-surface">
                            {data.tracking}
                        </span>
                    </div>
                    <div className="h-[1px] w-full bg-surface-container-highest" />
                    <div className="grid grid-cols-2 gap-space-sm pt-space-xs">
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Penerima</span>
                            <span className="font-label-lg text-label-lg text-on-surface">{data.recipient}</span>
                        </div>
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Kurir Bertugas</span>
                            <span className="font-label-lg text-label-lg text-on-surface">{data.courier}</span>
                        </div>
                    </div>
                    <div className="flex flex-col pt-space-xs">
                        <span className="font-label-sm text-label-sm text-secondary">Produk Beku</span>
                        <span className="font-body-md text-body-md font-semibold text-on-surface">{data.product}</span>
                    </div>
                </section>

                <section className="flex flex-col gap-space-md rounded-lg bg-surface-container-lowest p-space-md">
                    <div className="flex items-center justify-between">
                        <div className="inline-flex items-center rounded bg-error-container px-space-sm py-0.5 font-label-md text-label-md text-on-error-container">
                            {data.statusBadge}
                        </div>
                        <div className="inline-flex items-center rounded bg-surface-container px-space-sm py-0.5 font-label-sm text-label-sm text-secondary">
                            {data.tempBadge}
                        </div>
                    </div>
                    <div className="flex flex-col gap-space-xs">
                        <span className="font-label-sm text-label-sm text-secondary">{data.problemLabel}</span>
                        <p className="font-headline-sm text-headline-sm text-on-surface">{data.problem}</p>
                    </div>
                    <div className="flex flex-col gap-space-xs rounded-lg bg-surface-container-low p-space-md">
                        <span className="font-label-sm text-label-sm font-semibold text-primary">
                            {data.decisionLabel}
                        </span>
                        <p className="font-body-md text-body-md text-on-surface">{data.decision}</p>
                    </div>
                </section>

                <section className="flex flex-col gap-space-xs rounded-lg bg-surface-container-lowest p-space-md">
                    <span className="font-label-md text-label-md text-tertiary">{data.qualityLabel}</span>
                    <p className="font-body-sm text-body-sm leading-relaxed text-secondary">{data.quality}</p>
                </section>

                <section className="flex flex-col gap-space-md rounded-lg bg-surface-container-lowest p-space-md">
                    <span className="font-label-lg text-label-lg text-on-surface">Riwayat Penanganan Hub</span>
                    <div className="relative flex flex-col gap-space-lg pl-space-lg">
                        <div className="absolute bottom-2 left-[5px] top-2 w-[2px] bg-surface-container-highest" />
                        {data.history.map((node) => (
                            <div key={node.time + node.label} className="relative flex flex-col">
                                <div
                                    className={cx(
                                        "absolute -left-[19px] top-1.5 h-3 w-3 rounded-full ring-4 ring-surface-container-lowest",
                                        node.active ? "bg-primary" : "bg-secondary"
                                    )}
                                />
                                <div className="flex items-baseline justify-between gap-space-sm">
                                    <span
                                        className={cx(
                                            "font-label-md text-label-md",
                                            node.active ? "text-primary" : "text-on-surface"
                                        )}
                                    >
                                        {node.label}
                                    </span>
                                    <span className="font-label-sm text-label-sm text-secondary">{node.time}</span>
                                </div>
                                <p
                                    className={cx(
                                        "mt-space-xs font-body-sm text-body-sm",
                                        node.active ? "text-on-surface" : "text-secondary"
                                    )}
                                >
                                    {node.detail}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="flex flex-col items-center gap-space-md pt-space-xs">
                    <button
                        type="button"
                        className="flex h-12 w-full items-center justify-center rounded-lg bg-primary font-label-lg text-label-lg text-on-primary transition-colors active:bg-primary-container"
                    >
                        Ajukan Pengiriman Ulang
                    </button>
                    <p className="px-space-sm text-center font-body-sm text-body-sm leading-normal text-secondary">
                        Butuh bantuan langsung? Hubungi{" "}
                        <button type="button" className="font-semibold text-primary hover:underline">
                            Layanan Pelanggan Anteraja Frozen
                        </button>
                        .
                    </p>
                </div>
            </div>
        </FrozenLayout>
    );
}
