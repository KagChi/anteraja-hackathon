import { Head } from "@inertiajs/react";
import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { TRACKING } from "@/data/frozen";
import { FrozenLayout } from "@/layouts";
import { cx } from "@/lib/cx";

export default function Tracking() {
    const data = TRACKING;
    const [copied, setCopied] = useState(false);

    async function copyResi() {
        try {
            await navigator.clipboard.writeText(data.tracking);
        } catch {
            // Clipboard access can be blocked; the label still acknowledges the intent.
        }
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    return (
        <FrozenLayout title="Pelacakan Kurir Beku">
            <Head title="Pelacakan Kurir Beku" />

            <div className="flex w-full flex-col gap-space-lg pb-space-lg">
                <section className="flex flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                    <div className="flex items-center justify-between pb-space-xs">
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Nomor Resi</span>
                            <span className="font-data-mono text-headline-sm font-semibold tracking-tight text-on-surface">
                                {data.tracking}
                            </span>
                        </div>
                        <div className="flex items-center rounded bg-surface-container-low px-space-sm py-1">
                            <span className="font-label-md text-label-md text-primary">{data.status}</span>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-space-md pt-space-xs">
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Estimasi Tiba</span>
                            <span className="mt-0.5 font-label-lg text-label-lg text-on-surface">{data.etaDate}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{data.etaTime}</span>
                        </div>
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Penerima</span>
                            <span className="mt-0.5 font-label-lg text-label-lg text-on-surface">{data.recipient}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{data.recipientArea}</span>
                        </div>
                    </div>

                    <div className="flex items-center justify-between rounded-lg bg-surface-container-low p-space-sm">
                        <div className="flex items-center gap-space-sm">
                            <MaterialIcon name="two_wheeler" className="text-[20px] text-secondary" />
                            <div className="flex flex-col">
                                <span className="font-label-sm text-label-sm text-secondary">Kurir Pengantar</span>
                                <span className="font-label-md text-label-md text-on-surface">{data.courier}</span>
                            </div>
                        </div>
                        <span className="rounded bg-surface-container-lowest px-2 py-0.5 font-label-sm text-label-sm text-secondary">
                            {data.courierBox}
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
                                    {data.temperature}
                                </span>
                                <span className="font-label-sm text-label-sm font-semibold text-tertiary">
                                    ({data.temperatureState})
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-col items-end">
                        <span className="font-label-sm text-label-sm text-secondary">Rantai Dingin</span>
                        <span className="font-label-sm text-label-sm text-tertiary">{data.coldChain}</span>
                    </div>
                </section>

                <section className="flex flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-lg">
                    <div className="flex items-center justify-between">
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Riwayat Perjalanan</h2>
                        <span className="font-label-sm text-label-sm text-secondary">WIB</span>
                    </div>
                    <div className="relative flex flex-col">
                        <div className="absolute bottom-3 left-[7px] top-3 w-[2px] bg-surface-container-highest" />
                        {data.timeline.map((node) => (
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
                    <a
                        href={data.courierTel}
                        aria-label={`Hubungi Kurir ${data.courier}`}
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
        </FrozenLayout>
    );
}
