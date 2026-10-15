import { MaterialIcon } from "@/components/MaterialIcon";
import type { ScreenProps } from "../../App";
import { MissingOrder } from "../../components/MissingOrder";
import { StateCard } from "../../components/StateCard";
import { StatusPill } from "../../components/StatusPill";
import { ApiError, clientApi } from "../../lib/api";
import { splitScheduleLabel } from "../../lib/format";
import { Link, useRouter } from "../../lib/router";
import { useLoad } from "../../lib/useLoad";

/** Where one parcel is, what it went through, and who has it. */
export default function Tracking({ params }: ScreenProps) {
    const resi = params.resi ?? "";
    const { navigate } = useRouter();
    const { data, error, reload } = useLoad(
        () =>
            clientApi.orders.tracking(resi).catch((failure: unknown) => {
                // A resi that is not on this account is an empty screen, not a
                // retry card.
                if (failure instanceof ApiError && failure.status === 404) {
                    return null;
                }

                throw failure;
            }),
        `client-tracking-${resi}`
    );

    if (error !== null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <StateCard message={error} onRetry={reload} />
            </div>
        );
    }

    const view = data?.data ?? null;

    if (view === null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <MissingOrder resi={resi} />
            </div>
        );
    }

    const [etaDate, etaTime] = splitScheduleLabel(view.estimated_delivery);
    const courierPhone = (view.courier_phone ?? "").replace(/[^0-9+]/g, "");
    const coldChain = view.cold_chain;

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin pb-space-lg">
            <section className="flex w-full flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-xl">
                <div className="flex w-full items-center justify-between gap-2">
                    <span className="flex flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Nomor Resi</span>
                        <span className="font-headline-md text-headline-md text-on-surface select-all">
                            {view.tracking_number}
                        </span>
                    </span>
                    <StatusPill code={view.status} label={view.status_label} />
                </div>

                <div className="flex w-full flex-col gap-0.5">
                    <span className="font-body-sm text-body-sm text-secondary">Produk</span>
                    <span className="font-body-md text-body-md text-on-surface">
                        {view.product_name ?? "Paket Frozen"}
                    </span>
                    <span className="font-body-sm text-body-sm text-secondary">{view.product_meta ?? ""}</span>
                </div>

                <div className="flex w-full gap-space-lg">
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Estimasi Tiba</span>
                        <span className="font-body-md text-body-md text-on-surface">{etaDate}</span>
                        <span className="font-body-sm text-body-sm text-secondary">{etaTime}</span>
                    </span>
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Penerima</span>
                        <span className="font-body-md text-body-md text-on-surface">{view.recipient_name}</span>
                        <span className="font-body-sm text-body-sm text-secondary">{view.recipient_area}</span>
                    </span>
                </div>

                <div className="flex w-full items-center justify-between rounded-lg bg-surface-container-low p-space-md">
                    <span className="flex items-center gap-space-sm">
                        <MaterialIcon name="two_wheeler" className="text-[20px] text-secondary" />
                        <span className="flex flex-col gap-0.5">
                            <span className="font-body-sm text-body-sm text-secondary">Kurir Pengantar</span>
                            <span className="font-body-md text-body-md text-on-surface">
                                {view.courier_name ?? "-"}
                            </span>
                        </span>
                    </span>
                    <span className="rounded bg-surface-container-lowest px-2 py-0.5 font-body-sm text-body-sm text-secondary">
                        {view.cooler_box_code ?? "-"}
                    </span>
                </div>
            </section>

            <section className="flex w-full items-center justify-between rounded-xl bg-surface-container-lowest p-space-xl">
                <span className="flex items-center gap-space-md">
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-container">
                        <MaterialIcon name="ac_unit" className="text-[20px] text-tertiary" />
                    </span>
                    <span className="flex flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Suhu Cooler Box</span>
                        <span className="flex items-center gap-1">
                            <span className="font-headline-md text-headline-md text-on-surface">
                                {coldChain?.temperature_label ?? "-"}
                            </span>
                            <span className="font-label-md text-label-md text-tertiary">
                                ({coldChain?.temperature_state ?? "-"})
                            </span>
                        </span>
                    </span>
                </span>
                <span className="flex flex-col items-end gap-0.5">
                    <span className="font-body-sm text-body-sm text-secondary">Rantai Dingin</span>
                    <span className="font-body-sm text-body-sm text-tertiary">{coldChain?.chain_state ?? "-"}</span>
                </span>
            </section>

            <section className="flex w-full flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-xl">
                <div className="flex w-full items-center justify-between">
                    <span className="font-headline-md text-headline-md text-on-surface">Riwayat Perjalanan</span>
                    <span className="font-body-sm text-body-sm text-secondary">WIB</span>
                </div>

                {view.timeline.map((node) => (
                    <div key={`${node.title}-${node.time}`} className="flex w-full items-start gap-space-md">
                        <span className="w-4 pt-1">
                            <MaterialIcon
                                name="circle"
                                filled
                                className={`${node.is_accent ? "text-[12px] text-primary" : "text-[8px] text-secondary"}`}
                            />
                        </span>
                        <span className="flex flex-1 flex-col gap-0.5">
                            <span className="flex w-full items-center justify-between gap-2">
                                <span
                                    className={
                                        node.is_accent
                                            ? "font-headline-sm text-headline-sm text-primary"
                                            : "font-body-md text-body-md text-on-surface"
                                    }
                                >
                                    {node.title}
                                </span>
                                <span className="font-body-sm text-body-sm text-secondary">{node.time}</span>
                            </span>
                            <span
                                className={
                                    node.is_accent
                                        ? "font-body-sm text-body-sm leading-relaxed text-on-surface"
                                        : "font-body-sm text-body-sm leading-relaxed text-secondary"
                                }
                            >
                                {node.description}
                            </span>
                        </span>
                    </div>
                ))}
            </section>

            <div className="flex w-full flex-col gap-space-md pt-1">
                {view.status === "AWAITING_CONFIRMATION" ? (
                    <Link
                        href={`/client/schedule/${view.tracking_number}`}
                        className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                    >
                        <MaterialIcon name="event_available" className="text-[20px]" />
                        Konfirmasi Jadwal
                    </Link>
                ) : null}

                {view.status === "ISSUE_REPORTED" || view.status === "RETURNED" ? (
                    <button
                        type="button"
                        onClick={() => navigate(`/client/issue/${view.tracking_number}`)}
                        className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-error-container px-space-lg font-label-md text-label-md text-on-error-container"
                    >
                        <MaterialIcon name="report_problem" className="text-[20px]" />
                        Lihat Detail Kendala
                    </button>
                ) : null}

                {courierPhone === "" ? null : (
                    <a
                        href={`tel:${courierPhone}`}
                        className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
                    >
                        <MaterialIcon name="call" className="text-[20px]" />
                        Hubungi Kurir
                    </a>
                )}
            </div>
        </div>
    );
}
