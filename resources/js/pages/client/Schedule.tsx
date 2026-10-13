import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { SCHEDULE } from "@/data/client";
import { ClientLayout } from "@/layouts";
import { cx } from "@/lib/cx";

type ConfirmState = "idle" | "saving" | "confirmed";

export default function Schedule() {
    const data = SCHEDULE;
    const [confirmState, setConfirmState] = useState<ConfirmState>("idle");
    const [rescheduling, setRescheduling] = useState(false);
    const [copied, setCopied] = useState(false);

    function confirmSchedule() {
        if (confirmState !== "idle") return;
        setConfirmState("saving");
        window.setTimeout(() => setConfirmState("confirmed"), 900);
    }

    function requestReschedule() {
        if (rescheduling) return;
        setRescheduling(true);
        window.setTimeout(() => setRescheduling(false), 1200);
    }

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
        <ClientLayout title="Detail Produk" backTo="/client/tracking">
            <div className="flex w-full flex-col pb-36">
                <div className="flex items-center justify-between pb-space-sm pt-space-md">
                    <div className="inline-flex items-center gap-space-xs rounded bg-secondary-container px-2.5 py-1 text-on-secondary-container">
                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-primary" />
                        <span className="font-label-sm text-label-sm uppercase tracking-wider">{data.status}</span>
                    </div>
                    <span className="font-label-sm text-label-sm text-secondary">ID: {data.tracking}</span>
                </div>

                <div className="flex flex-col gap-space-md">
                    <section className="flex flex-col gap-space-sm rounded-lg bg-surface-container-lowest p-space-md">
                        <div className="flex items-center justify-between pb-space-xs">
                            <span className="font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                                Tujuan Pengiriman
                            </span>
                            <button
                                type="button"
                                onClick={copyResi}
                                className="font-label-sm text-label-sm text-primary hover:underline"
                            >
                                {copied ? "Resi Tersalin" : "Salin Resi"}
                            </button>
                        </div>
                        <div className="flex items-start gap-space-sm">
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-surface-container text-secondary">
                                <MaterialIcon name="location_on" className="text-[18px]" />
                            </div>
                            <div className="flex min-w-0 flex-col">
                                <span className="truncate font-headline-sm text-headline-sm text-on-surface">
                                    {data.recipient}
                                </span>
                                <span className="font-body-sm text-body-sm text-secondary">{data.address}</span>
                            </div>
                        </div>
                    </section>

                    <section className="flex flex-col gap-space-md rounded-lg bg-surface-container-lowest p-space-md">
                        <div className="flex items-center justify-between">
                            <span className="font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                                Detail Muatan Beku
                            </span>
                            <div className="inline-flex items-center gap-1 rounded bg-tertiary-fixed px-2 py-0.5 font-label-sm text-label-sm text-on-tertiary-fixed">
                                <MaterialIcon name="ac_unit" className="text-[13px]" />
                                <span>{data.coldBadge}</span>
                            </div>
                        </div>
                        <div className="flex items-center gap-space-md">
                            <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-container text-secondary">
                                <img
                                    src="/images/product-placeholder.svg"
                                    alt={data.productName}
                                    className="h-full w-full object-cover"
                                />
                            </div>
                            <div className="flex min-w-0 flex-1 flex-col">
                                <span className="font-label-sm text-label-sm text-tertiary">
                                    {data.productCategory}
                                </span>
                                <h2 className="truncate font-headline-sm text-headline-sm text-on-surface">
                                    {data.productName}
                                </h2>
                                <span className="font-body-sm text-body-sm text-secondary">{data.productMeta}</span>
                            </div>
                        </div>
                        <div className="flex items-start gap-2 rounded bg-surface-container-low p-2.5">
                            <MaterialIcon name="inventory_2" className="mt-0.5 shrink-0 text-[16px] text-tertiary" />
                            <p className="font-body-sm text-body-sm leading-relaxed text-on-surface-variant">
                                {data.productNote}
                            </p>
                        </div>
                    </section>

                    <section className="flex flex-col gap-space-sm rounded-lg bg-surface-container-lowest p-space-md">
                        <div className="flex items-center justify-between">
                            <span className="font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                                Jadwal Pengantaran Terpilih
                            </span>
                            <span className="font-label-sm text-label-sm font-semibold text-primary">
                                {data.priority}
                            </span>
                        </div>
                        <div className="flex flex-col gap-space-xs rounded bg-surface-container p-space-md">
                            <div className="flex items-center gap-space-xs text-on-surface">
                                <MaterialIcon name="schedule" className="text-[20px] text-primary" />
                                <span className="font-headline-md text-headline-md font-bold text-on-surface">
                                    {data.window}
                                </span>
                            </div>
                            <span className="ml-7 font-body-md text-body-md text-secondary">{data.windowDate}</span>
                        </div>
                        <div className="flex items-center justify-between pt-space-xs">
                            <div className="flex items-center gap-space-sm">
                                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-secondary-container text-on-secondary-container">
                                    <MaterialIcon name="two_wheeler" className="text-[20px]" />
                                </div>
                                <div className="flex min-w-0 flex-col">
                                    <span className="font-label-md text-label-md text-on-surface">{data.courier}</span>
                                    <span className="font-body-sm text-body-sm text-secondary">{data.courierRole}</span>
                                </div>
                            </div>
                            <div
                                title="Sertifikasi Cold-Chain"
                                className="flex h-6 w-6 items-center justify-center rounded-full bg-tertiary-fixed text-on-tertiary-fixed"
                            >
                                <MaterialIcon name="verified" className="text-[14px]" />
                            </div>
                        </div>
                    </section>

                    <div className="flex items-start gap-space-sm rounded-lg bg-surface-container-low p-space-md">
                        <MaterialIcon name="info" className="mt-0.5 shrink-0 text-[20px] text-primary" />
                        <div className="flex flex-col gap-0.5">
                            <span className="font-label-md text-label-md text-on-surface">{data.noticeTitle}</span>
                            <p className="font-body-sm text-body-sm leading-relaxed text-secondary">{data.notice}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 bg-surface-container-lowest px-margin pt-space-md pb-safe">
                <div className="flex w-full flex-col gap-2.5">
                    <button
                        type="button"
                        onClick={confirmSchedule}
                        disabled={confirmState !== "idle"}
                        className={cx(
                            "flex h-12 w-full items-center justify-center gap-2 rounded font-label-lg text-label-lg transition-colors",
                            confirmState === "confirmed"
                                ? "bg-tertiary text-on-tertiary"
                                : "bg-primary text-on-primary active:bg-primary-container"
                        )}
                    >
                        {confirmState === "confirmed" ? (
                            <>
                                <MaterialIcon name="check" className="text-[18px]" />
                                Jadwal Terkonfirmasi
                            </>
                        ) : confirmState === "saving" ? (
                            <>
                                <MaterialIcon name="sync" className="animate-spin text-[18px]" />
                                Menyimpan Jadwal...
                            </>
                        ) : (
                            <>
                                <MaterialIcon name="check_circle" className="text-[18px]" />
                                Siap Menerima
                            </>
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={requestReschedule}
                        className="flex h-12 w-full items-center justify-center rounded bg-surface-container-lowest font-label-lg text-label-lg text-on-surface transition-colors hover:bg-surface-container"
                    >
                        {rescheduling ? "Membuka Kalender..." : "Minta Jadwal Lain"}
                    </button>
                </div>
            </div>
        </ClientLayout>
    );
}
