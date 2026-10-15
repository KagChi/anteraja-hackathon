import { StateCard } from "../../components/StateCard";
import { courierApi } from "../../lib/api";
import { useLoad } from "../../lib/useLoad";

/** Every parcel this courier has already handed over. */
export default function History() {
    const { data, error, reload } = useLoad(() => courierApi.history(), "courier-history");
    const rows = data?.history ?? [];
    const summary = data?.summary ?? null;
    const onTime = (summary?.onTimeRate ?? "97% Tepat Waktu").replace("Tepat Waktu", "").trim();

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
                        {summary?.completedToday ?? "0 Pengiriman"}
                    </span>
                </span>
                <span className="rounded-full bg-tertiary-fixed px-space-md py-1 font-label-sm text-label-sm text-tertiary">
                    {onTime} Tepat Waktu
                </span>
            </div>

            {rows.length === 0 ? (
                <span className="font-body-md text-body-md text-secondary">Belum ada riwayat pengantaran.</span>
            ) : null}

            {rows.map((row) => (
                <section
                    key={row.resi}
                    className="flex w-full flex-col gap-space-sm rounded-xl bg-surface-container-lowest p-space-lg"
                >
                    <div className="flex w-full items-center justify-between gap-2">
                        <span className="font-body-sm text-body-sm text-secondary select-all">{row.resi}</span>
                        <span className="rounded-full bg-tertiary-fixed px-space-md py-0.5 font-label-sm text-label-sm text-tertiary">
                            {row.status}
                        </span>
                    </div>
                    <span className="font-headline-sm text-headline-sm text-on-surface">{row.recipient}</span>
                    <span className="font-body-sm text-body-sm text-secondary">{row.completedAt}</span>
                </section>
            ))}
        </div>
    );
}
