import { Link } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { CLIENT_PAST_ORDERS, CLIENT_STATUS_TONE } from "@/data/client";
import { ClientLayout } from "@/layouts";
import { cx } from "@/lib/cx";

export default function History() {
    return (
        <ClientLayout title="Riwayat">
            <div className="flex w-full flex-col gap-space-md pb-space-lg">
                <div className="flex items-center justify-between rounded-xl bg-surface-container-low p-space-md">
                    <div className="flex flex-col">
                        <span className="font-label-sm text-label-sm text-secondary uppercase">Selesai</span>
                        <span className="font-headline-sm text-headline-sm text-on-surface">
                            {CLIENT_PAST_ORDERS.length} pengiriman diterima
                        </span>
                    </div>
                    <span className="rounded-full bg-tertiary-fixed px-2 py-0.5 font-label-sm font-semibold text-label-sm text-tertiary">
                        96% Tepat Waktu
                    </span>
                </div>

                {CLIENT_PAST_ORDERS.map((order) => (
                    <article
                        key={order.resi}
                        className="flex flex-col gap-space-sm rounded-xl border border-black/5 bg-surface-container-lowest p-space-md"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-data-mono text-label-sm text-secondary">{order.resi}</span>
                            <span
                                className={cx(
                                    "flex items-center gap-1 rounded-full px-2 py-0.5 font-label-sm font-semibold text-label-sm",
                                    CLIENT_STATUS_TONE[order.statusKey]
                                )}
                            >
                                <MaterialIcon name="check_circle" className="text-[13px]" />
                                {order.status}
                            </span>
                        </div>
                        <span className="font-headline-sm text-headline-sm text-on-surface">{order.product}</span>
                        <div className="flex items-center justify-between border-black/5 border-t pt-space-sm">
                            <span className="font-label-sm text-label-sm text-secondary">
                                {order.etaDate} • {order.etaTime}
                            </span>
                            <Link
                                href={`/client/tracking/${order.resi}`}
                                className="flex items-center gap-1 font-label-md font-semibold text-label-md text-primary transition-opacity active:opacity-70"
                            >
                                <span>Lihat Detail</span>
                                <MaterialIcon name="arrow_forward" className="text-[16px]" />
                            </Link>
                        </div>
                    </article>
                ))}
            </div>
        </ClientLayout>
    );
}
