import { MaterialIcon } from "@/components/MaterialIcon";
import { StateCard } from "../../components/StateCard";
import { StatusPill } from "../../components/StatusPill";
import { clientApi } from "../../lib/api";
import { Link } from "../../lib/router";
import { useLoad } from "../../lib/useLoad";

/** Everything that already arrived. */
export default function History() {
    const { data, error, reload } = useLoad(() => clientApi.orders.list("completed"), "client-orders-completed");
    const orders = data?.data ?? [];
    const onTime = data?.meta.sla_rate ?? "96%";

    if (error !== null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <StateCard message={error} onRetry={reload} />
            </div>
        );
    }

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin">
            <div className="flex w-full items-center justify-between gap-2 rounded-xl bg-surface-container-low p-space-lg">
                <span className="flex flex-col gap-1">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Selesai</span>
                    <span className="font-headline-md text-headline-md text-on-surface">
                        {orders.length} pengiriman diterima
                    </span>
                </span>
                <span className="rounded-full bg-tertiary-fixed px-space-md py-1 font-label-sm text-label-sm text-tertiary">
                    {onTime} Tepat Waktu
                </span>
            </div>

            {orders.map((order) => (
                <Link
                    key={order.resi}
                    href={`/client/tracking/${order.resi}`}
                    className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg"
                >
                    <div className="flex w-full items-center justify-between gap-2">
                        <span className="font-body-sm text-body-sm text-secondary select-all">{order.resi}</span>
                        <StatusPill code={order.status.code} label={order.status.label} className="gap-1" />
                    </div>

                    <span className="font-headline-md text-headline-md text-on-surface">{order.product}</span>

                    <div className="flex w-full items-center justify-between pt-2">
                        <span className="font-body-sm text-body-sm text-secondary">
                            {order.eta_date}
                            {order.eta_time === "" ? "" : ` • ${order.eta_time}`}
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="font-headline-sm text-headline-sm text-primary">Lihat Detail</span>
                            <MaterialIcon name="arrow_forward" className="text-[16px] text-primary" />
                        </span>
                    </div>
                </Link>
            ))}
        </div>
    );
}
