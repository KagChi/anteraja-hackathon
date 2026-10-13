import { Link } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { CLIENT_ACTIVE_ORDERS, CLIENT_STATUS_TONE } from "@/data/client";
import { ClientLayout } from "@/layouts";
import { cx } from "@/lib/cx";

export default function Orders() {
    return (
        <ClientLayout title="Pesanan">
            <div className="flex w-full flex-col gap-space-md pb-space-lg">
                <div className="flex items-center justify-between rounded-xl bg-surface-container-low p-space-md">
                    <div className="flex flex-col">
                        <span className="font-label-sm text-label-sm uppercase text-secondary">Pesanan Aktif</span>
                        <span className="font-headline-sm text-headline-sm text-on-surface">
                            {CLIENT_ACTIVE_ORDERS.length} pengiriman berjalan
                        </span>
                    </div>
                    <span className="flex items-center gap-1 rounded-full bg-tertiary-fixed px-2 py-0.5 font-label-sm text-label-sm font-semibold text-tertiary">
                        <MaterialIcon name="ac_unit" className="text-[14px]" />
                        Beku -18°C
                    </span>
                </div>

                {CLIENT_ACTIVE_ORDERS.map((order) => (
                    <article
                        key={order.resi}
                        className="flex flex-col gap-space-sm rounded-xl border border-black/5 bg-surface-container-lowest p-space-md"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-data-mono text-label-sm text-secondary">{order.resi}</span>
                            <span
                                className={cx(
                                    "rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold",
                                    CLIENT_STATUS_TONE[order.statusKey]
                                )}
                            >
                                {order.status}
                            </span>
                        </div>
                        <div className="flex flex-col">
                            <span className="font-headline-sm text-headline-sm text-on-surface">{order.product}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{order.productMeta}</span>
                        </div>
                        <div className="flex flex-col gap-0.5">
                            <span className="font-label-sm text-label-sm text-secondary">Penerima</span>
                            <span className="font-label-md text-label-md text-on-surface">
                                {order.recipient} • {order.recipientArea}
                            </span>
                        </div>
                        <div className="flex items-center justify-between border-t border-black/5 pt-space-sm">
                            <span className="font-label-sm text-label-sm text-secondary">
                                {order.etaDate} • {order.etaTime}
                            </span>
                            <Link
                                href={`/client/tracking/${order.resi}`}
                                className="flex items-center gap-1 font-label-md text-label-md font-semibold text-primary transition-opacity active:opacity-70"
                            >
                                <span>Lacak</span>
                                <MaterialIcon name="arrow_forward" className="text-[16px]" />
                            </Link>
                        </div>
                    </article>
                ))}

                <p className="px-1 font-body-sm text-body-sm text-secondary">
                    Pilih pesanan untuk melihat posisi kurir, suhu cooler box, dan riwayat perjalanan paket.
                </p>
            </div>
        </ClientLayout>
    );
}
