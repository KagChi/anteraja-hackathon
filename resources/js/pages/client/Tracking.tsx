import { Link } from "@inertiajs/react";
import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { MissingOrder } from "@/components/MissingOrder";
import { findOrder } from "@/data/client";
import { ClientLayout } from "@/layouts";
import { cx } from "@/lib/cx";

interface TrackingProps {
    /** Resi of the order being tracked. */
    resi?: string;
}

export default function Tracking({ resi }: TrackingProps) {
    const order = findOrder(resi);
    const [copied, setCopied] = useState(false);

    async function copyResi() {
        if (!order) return;
        try {
            await navigator.clipboard.writeText(order.resi);
        } catch {
            // Clipboard access can be blocked; the label still acknowledges the intent.
        }
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    if (!order) {
        return (
            <ClientLayout title="Pelacakan Kurir Beku" backTo="/client/orders">
                <MissingOrder resi={resi} />
            </ClientLayout>
        );
    }

    return (
        <ClientLayout title="Pelacakan Kurir Beku" backTo="/client/orders">
            <div className="flex w-full flex-col gap-space-lg pb-space-lg">
                <section className="flex flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                    <div className="flex items-center justify-between pb-space-xs">
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Nomor Resi</span>
                            <span className="font-data-mono text-headline-sm font-semibold tracking-tight text-on-surface">
                                {order.resi}
                            </span>
                        </div>
                        <div className="flex items-center rounded bg-surface-container-low px-space-sm py-1">
                            <span className="font-label-md text-label-md text-primary">{order.status}</span>
                        </div>
                    </div>

                    <div className="flex flex-col gap-0.5">
                        <span className="font-label-sm text-label-sm text-secondary">Produk</span>
                        <span className="font-label-lg text-label-lg text-on-surface">{order.product}</span>
                        <span className="font-body-sm text-body-sm text-secondary">{order.productMeta}</span>
                    </div>

                    <div className="grid grid-cols-2 gap-space-md pt-space-xs">
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Estimasi Tiba</span>
                            <span className="mt-0.5 font-label-lg text-label-lg text-on-surface">{order.etaDate}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{order.etaTime}</span>
                        </div>
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Penerima</span>
                            <span className="mt-0.5 font-label-lg text-label-lg text-on-surface">
                                {order.recipient}
                            </span>
                            <span className="font-body-sm text-body-sm text-secondary">{order.recipientArea}</span>
                        </div>
                    </div>

                    <div className="flex items-center justify-between rounded-lg bg-surface-container-low p-space-sm">
                        <div className="flex items-center gap-space-sm">
                            <MaterialIcon name="two_wheeler" className="text-[20px] text-secondary" />
                            <div className="flex flex-col">
                                <span className="font-label-sm text-label-sm text-secondary">Kurir Pengantar</span>
                                <span className="font-label-md text-label-md text-on-surface">{order.courier}</span>
                            </div>
                        </div>
                        <span className="rounded bg-surface-container-lowest px-2 py-0.5 font-label-sm text-label-sm text-secondary">
                            {order.courierBox}
                        </span>
                    </div>
                </section>

                <section className="flex items-center justify-between rounded-xl bg-surface-container-lowest p-space-lg">
                    <div className="flex items-center gap-space-md">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-container-low text-tertiary">
                            <MaterialIcon name="ac_unit" className="text-[20px]" />
                        </div>
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Suhu Cooler Box</span>
                            <div className="flex items-baseline gap-space-xs">
                                <span className="font-headline-md text-headline-md text-on-surface">
                                    {order.temperature}
                                </span>
                                <span className="font-label-sm text-label-sm font-semibold text-tertiary">
                                    ({order.temperatureState})
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-col items-end">
                        <span className="font-label-sm text-label-sm text-secondary">Rantai Dingin</span>
                        <span className="font-label-sm text-label-sm text-tertiary">{order.coldChain}</span>
                    </div>
                </section>

                <section className="flex flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-lg">
                    <div className="flex items-center justify-between">
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Riwayat Perjalanan</h2>
                        <span className="font-label-sm text-label-sm text-secondary">WIB</span>
                    </div>
                    <div className="relative flex flex-col">
                        <div className="absolute bottom-3 left-[7px] top-3 w-[2px] bg-surface-container-highest" />
                        {order.timeline.map((node) => (
                            <div
                                key={node.time + node.label}
                                className="relative flex items-start gap-space-md pb-space-lg last:pb-0"
                            >
                                <div className="relative z-10 mt-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-surface-container-lowest">
                                    <div
                                        className={cx(
                                            "rounded-full",
                                            node.active ? "h-2.5 w-2.5 bg-primary" : "h-2 w-2 bg-secondary"
                                        )}
                                    />
                                </div>
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <div className="flex items-baseline justify-between gap-space-sm">
                                        <span
                                            className={cx(
                                                node.active
                                                    ? "font-label-lg text-label-lg text-primary"
                                                    : "font-label-md text-label-md text-on-surface"
                                            )}
                                        >
                                            {node.label}
                                        </span>
                                        <span className="font-label-sm text-label-sm text-secondary">{node.time}</span>
                                    </div>
                                    <p
                                        className={cx(
                                            "mt-0.5 font-body-sm text-body-sm leading-relaxed",
                                            node.active ? "text-on-surface" : "text-secondary"
                                        )}
                                    >
                                        {node.detail}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="flex flex-col gap-space-sm pt-space-xs">
                    {order.statusKey === "waiting" ? (
                        <Link
                            href={`/client/schedule/${order.resi}`}
                            className="flex h-12 w-full items-center justify-center gap-space-sm rounded-lg border border-primary bg-primary-fixed/50 font-label-lg text-label-lg text-primary transition-colors active:bg-primary-fixed"
                        >
                            <MaterialIcon name="event_available" className="text-[20px]" />
                            Konfirmasi Jadwal
                        </Link>
                    ) : null}
                    {order.statusKey === "issue" ? (
                        <Link
                            href={`/client/issue/${order.resi}`}
                            className="flex h-12 w-full items-center justify-center gap-space-sm rounded-lg border border-error/30 bg-error-container font-label-lg text-label-lg text-on-error-container transition-colors"
                        >
                            <MaterialIcon name="report_problem" className="text-[20px]" />
                            Lihat Detail Kendala
                        </Link>
                    ) : null}
                    <a
                        href={order.courierTel}
                        aria-label={`Hubungi Kurir ${order.courier}`}
                        className="flex h-12 w-full items-center justify-center gap-space-sm rounded-lg bg-primary font-label-lg text-label-lg text-on-primary transition-colors active:bg-primary-container"
                    >
                        <MaterialIcon name="call" className="text-[20px]" />
                        Hubungi Kurir
                    </a>
                    <button
                        type="button"
                        onClick={copyResi}
                        className="flex h-10 w-full items-center justify-center gap-space-xs rounded font-label-md text-label-md text-secondary transition-colors active:text-on-surface"
                    >
                        <MaterialIcon name="content_copy" className="text-[16px]" />
                        {copied ? "Resi Tersalin" : "Salin No. Resi"}
                    </button>
                </div>
            </div>
        </ClientLayout>
    );
}
