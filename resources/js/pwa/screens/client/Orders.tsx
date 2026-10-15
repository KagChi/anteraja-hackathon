import { MaterialIcon } from "@/components/MaterialIcon";
import { StateCard } from "../../components/StateCard";
import { StatusPill } from "../../components/StatusPill";
import { clientApi } from "../../lib/api";
import { Link } from "../../lib/router";
import { useLoad } from "../../lib/useLoad";

/** Every parcel still on its way to a recipient. */
export default function Orders() {
    const { data, error, reload } = useLoad(() => clientApi.orders.list("active"), "client-orders-active");
    const orders = data?.data ?? [];

    if (error !== null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <StateCard message={error} onRetry={reload} />
            </div>
        );
    }

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin">
            <section className="flex w-full flex-col gap-1 rounded-xl bg-surface-container-low p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Pesanan Aktif</span>
                <span className="font-headline-md text-headline-md text-on-surface">
                    {orders.length} pengiriman berjalan
                </span>
            </section>

            {orders.length === 0 ? (
                <span className="font-body-md text-body-md text-secondary">Belum ada pengiriman berjalan.</span>
            ) : null}

            {orders.map((order) => (
                <Link
                    key={order.resi}
                    href={`/client/tracking/${order.resi}`}
                    className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg"
                >
                    <div className="flex w-full items-center justify-between gap-2">
                        <span className="font-body-sm text-body-sm text-secondary select-all">{order.resi}</span>
                        <StatusPill code={order.status.code} label={order.status.label} />
                    </div>

                    <div className="flex w-full flex-col gap-0.5">
                        <span className="font-headline-md text-headline-md text-on-surface">{order.product}</span>
                        <span className="font-body-sm text-body-sm text-secondary">{order.product_meta}</span>
                    </div>

                    <div className="flex w-full flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Penerima</span>
                        <span className="font-body-md text-body-md text-on-surface">
                            {order.recipient}
                            {order.recipient_area === null ? "" : ` • ${order.recipient_area}`}
                        </span>
                    </div>

                    <div className="flex w-full items-center justify-between pt-2">
                        <span className="font-body-sm text-body-sm text-secondary">
                            {order.eta_date}
                            {order.eta_time === "" ? "" : ` • ${order.eta_time}`}
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="font-headline-sm text-headline-sm text-primary">Lacak</span>
                            <MaterialIcon name="arrow_forward" className="text-[16px] text-primary" />
                        </span>
                    </div>
                </Link>
            ))}
        </div>
    );
}
