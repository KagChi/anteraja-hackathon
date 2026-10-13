import { COURIER_HISTORY } from "@/data/courier";
import { CourierLayout } from "@/layouts";
import { cx } from "@/lib/cx";

export default function History() {
    return (
        <CourierLayout title="Riwayat Pengiriman">
            <div className="flex w-full flex-col gap-space-md">
                <div className="flex items-center justify-between rounded-xl bg-surface-container-lowest p-space-md">
                    <div className="flex flex-col">
                        <span className="font-label-sm text-label-sm text-secondary uppercase">Selesai Hari Ini</span>
                        <span className="font-headline-md text-headline-md text-on-surface">8 Pengiriman</span>
                    </div>
                    <span className="rounded-full bg-tertiary-fixed px-2 py-0.5 font-label-sm font-semibold text-label-sm text-tertiary">
                        97% Tepat Waktu
                    </span>
                </div>

                {COURIER_HISTORY.map((row) => (
                    <article
                        key={row.resi}
                        className="flex flex-col gap-space-xs rounded-xl bg-surface-container-lowest p-space-md"
                    >
                        <div className="flex items-center justify-between gap-space-sm">
                            <span className="font-data-mono font-semibold text-body-sm text-on-surface">
                                {row.resi}
                            </span>
                            <span
                                className={cx(
                                    "rounded-full px-2 py-0.5 font-label-sm font-semibold text-label-sm",
                                    row.status === "Terkirim"
                                        ? "bg-tertiary-fixed text-tertiary"
                                        : "bg-error-container text-on-error-container"
                                )}
                            >
                                {row.status}
                            </span>
                        </div>
                        <span className="font-label-md text-label-md text-on-surface">{row.recipient}</span>
                        <span className="font-body-sm text-body-sm text-secondary">{row.completedAt}</span>
                    </article>
                ))}
            </div>
        </CourierLayout>
    );
}
