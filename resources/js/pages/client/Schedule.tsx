import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { MissingOrder } from "@/components/MissingOrder";
import { findOrder, whatsappLink } from "@/data/client";
import { ClientLayout } from "@/layouts";
import { cx } from "@/lib/cx";

type ConfirmState = "idle" | "saving" | "confirmed";

interface ScheduleProps {
    /** Resi of the order whose delivery window is being confirmed. */
    resi?: string;
}

export default function Schedule({ resi }: ScheduleProps) {
    const order = findOrder(resi);
    const schedule = order?.schedule;
    const [confirmState, setConfirmState] = useState<ConfirmState>("idle");
    const [copied, setCopied] = useState(false);

    function confirmSchedule() {
        if (confirmState !== "idle") return;
        setConfirmState("saving");
        window.setTimeout(() => setConfirmState("confirmed"), 900);
    }

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

    if (!order || !schedule) {
        return (
            <ClientLayout title="Konfirmasi Jadwal" backTo="/client/orders">
                <MissingOrder resi={resi} />
            </ClientLayout>
        );
    }

    return (
        <ClientLayout title="Konfirmasi Jadwal" backTo={`/client/tracking/${order.resi}`}>
            <div className="flex w-full flex-col pb-52">
                <div className="flex items-center justify-between pt-space-md pb-space-sm">
                    <div className="inline-flex items-center gap-space-xs rounded bg-secondary-container px-2.5 py-1 text-on-secondary-container">
                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-primary" />
                        <span className="font-label-sm text-label-sm uppercase tracking-wider">{schedule.status}</span>
                    </div>
                    <span className="font-label-sm text-label-sm text-secondary">ID: {order.resi}</span>
                </div>

                <div className="flex flex-col gap-space-md">
                    <section className="flex flex-col gap-space-sm rounded-lg bg-surface-container-lowest p-space-md">
                        <div className="flex items-center justify-between pb-space-xs">
                            <span className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
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
                                    {order.recipient}
                                </span>
                                <span className="font-body-sm text-body-sm text-secondary">{order.address}</span>
                            </div>
                        </div>
                    </section>

                    <section className="flex flex-col gap-space-md rounded-lg bg-surface-container-lowest p-space-md">
                        <div className="flex items-center justify-between">
                            <span className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                                Detail Muatan Beku
                            </span>
                            <div className="inline-flex items-center gap-1 rounded bg-tertiary-fixed px-2 py-0.5 font-label-sm text-label-sm text-on-tertiary-fixed">
                                <MaterialIcon name="ac_unit" className="text-[13px]" />
                                <span>{schedule.coldBadge}</span>
                            </div>
                        </div>
                        <div className="flex items-center gap-space-md">
                            <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-container text-secondary">
                                <img
                                    src="/images/product-placeholder.svg"
                                    alt={schedule.productName}
                                    className="h-full w-full object-cover"
                                />
                            </div>
                            <div className="flex min-w-0 flex-1 flex-col">
                                <span className="font-label-sm text-label-sm text-tertiary">
                                    {schedule.productCategory}
                                </span>
                                <h2 className="truncate font-headline-sm text-headline-sm text-on-surface">
                                    {schedule.productName}
                                </h2>
                                <span className="font-body-sm text-body-sm text-secondary">{schedule.productMeta}</span>
                            </div>
                        </div>
                        <div className="flex items-start gap-2 rounded bg-surface-container-low p-2.5">
                            <MaterialIcon name="inventory_2" className="mt-0.5 shrink-0 text-[16px] text-tertiary" />
                            <p className="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                                {schedule.productNote}
                            </p>
                        </div>
                    </section>

                    <section className="flex flex-col gap-space-sm rounded-lg bg-surface-container-lowest p-space-md">
                        <div className="flex items-center justify-between">
                            <span className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                                Jadwal Pengantaran Terpilih
                            </span>
                            <span className="font-label-sm font-semibold text-label-sm text-primary">
                                {schedule.priority}
                            </span>
                        </div>
                        <div className="flex flex-col gap-space-xs rounded bg-surface-container p-space-md">
                            <div className="flex items-center gap-space-xs text-on-surface">
                                <MaterialIcon name="schedule" className="text-[20px] text-primary" />
                                <span className="font-bold font-headline-md text-headline-md text-on-surface">
                                    {schedule.window}
                                </span>
                            </div>
                            <span className="ml-7 font-body-md text-body-md text-secondary">{schedule.windowDate}</span>
                        </div>
                        <div className="flex items-center justify-between pt-space-xs">
                            <div className="flex items-center gap-space-sm">
                                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-secondary-container text-on-secondary-container">
                                    <MaterialIcon name="two_wheeler" className="text-[20px]" />
                                </div>
                                <div className="flex min-w-0 flex-col">
                                    <span className="font-label-md text-label-md text-on-surface">
                                        {schedule.courier}
                                    </span>
                                    <span className="font-body-sm text-body-sm text-secondary">
                                        {schedule.courierRole}
                                    </span>
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
                            <span className="font-label-md text-label-md text-on-surface">{schedule.noticeTitle}</span>
                            <p className="font-body-sm text-body-sm text-secondary leading-relaxed">
                                {schedule.notice}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="fixed inset-x-0 bottom-16 z-40 bg-surface-container-lowest px-margin pt-space-md pb-safe">
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
                    <a
                        href={whatsappLink(`Saya ingin meminta jadwal pengantaran lain untuk resi ${order.resi}.`)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex h-12 w-full items-center justify-center rounded bg-surface-container-lowest font-label-lg text-label-lg text-on-surface transition-colors hover:bg-surface-container"
                    >
                        Minta Jadwal Lain
                    </a>
                </div>
            </div>
        </ClientLayout>
    );
}
