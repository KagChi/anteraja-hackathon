import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import type { ScreenProps } from "../../App";
import { ActionError } from "../../components/ActionError";
import { MissingOrder } from "../../components/MissingOrder";
import { StateCard } from "../../components/StateCard";
import { ApiError, clientApi, firstError } from "../../lib/api";
import { useRouter } from "../../lib/router";
import { whatsappLink, whatsappMessageFor } from "../../lib/support";
import { useLoad } from "../../lib/useLoad";

/**
 * A parcel that ran into trouble: what happened, what operations decided, and
 * the way to a replacement.
 */
export default function Issue({ params }: ScreenProps) {
    const resi = params.resi ?? "";
    const { navigate } = useRouter();
    const { data, error, reload } = useLoad(
        () =>
            clientApi.orders.issue(resi).catch((failure: unknown) => {
                if (failure instanceof ApiError && failure.status === 404) {
                    return null;
                }

                throw failure;
            }),
        `client-issue-${resi}`
    );
    const [actionError, setActionError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

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

    async function requestResend() {
        if (busy) {
            return;
        }

        setBusy(true);
        setActionError(null);

        try {
            const response = await clientApi.orders.requestResend(resi);
            const replacement = response.replacement_tracking_number ?? response.data.replacement_tracking_number;
            const target = typeof replacement === "string" && replacement !== "" ? replacement : resi;

            navigate(`/client/schedule/${target}`, { replace: true });
        } catch (failure) {
            setActionError(firstError(failure));
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin pb-space-xl">
            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between gap-2">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">No. Resi Pengiriman</span>
                    <span className="font-headline-sm text-headline-sm text-on-surface select-all">
                        {view.tracking_number}
                    </span>
                </div>

                <hr className="border-outline-variant" />

                <div className="flex w-full gap-space-lg">
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Penerima</span>
                        <span className="font-body-md text-body-md text-on-surface">{view.recipient_name}</span>
                    </span>
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Kurir Bertugas</span>
                        <span className="font-body-md text-body-md text-on-surface">{view.courier_name ?? "-"}</span>
                    </span>
                </div>

                <span className="flex w-full flex-col gap-0.5">
                    <span className="font-body-sm text-body-sm text-secondary">Produk Beku</span>
                    <span className="font-headline-sm text-headline-sm text-on-surface">
                        {view.product_names[0] ?? "Paket Frozen"}
                    </span>
                </span>
            </section>

            <section className="flex w-full flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between gap-2">
                    <span className="rounded bg-error-container px-space-md py-0.5 font-body-md text-body-md text-on-error-container">
                        {view.status_label}
                    </span>
                    <span className="rounded bg-surface-container px-space-md py-0.5 font-body-sm text-body-sm text-secondary">
                        {view.cold_chain_chip ?? "Suhu belum tercatat"}
                    </span>
                </div>

                <span className="flex w-full flex-col gap-1">
                    <span className="font-body-sm text-body-sm text-secondary">
                        Laporan Masalah Fisik ({view.issue_category_label})
                    </span>
                    <span className="font-headline-md text-headline-md text-on-surface">{view.issue_summary}</span>
                </span>

                <div className="flex w-full flex-col gap-1 rounded-lg bg-surface-container-low p-space-lg">
                    <span className="font-label-md text-label-md text-primary">Keputusan Admin Operasional</span>
                    <span className="font-body-md text-body-md leading-relaxed text-on-surface">
                        {view.decision_message}
                    </span>
                </div>
            </section>

            <section className="flex w-full flex-col gap-1 rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-body-md text-body-md text-tertiary">Pemeriksaan Kualitas Cold Chain</span>
                <span className="font-body-sm text-body-sm leading-relaxed text-secondary">
                    {view.quality_inspection_note ?? "Tim kontrol kualitas belum mencatat temuan tambahan."}
                </span>
            </section>

            <section className="flex w-full flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-headline-md text-headline-md text-on-surface">Riwayat Penanganan Hub</span>

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

            <ActionError message={actionError} />

            <div className="flex w-full flex-col items-center gap-space-md pt-1">
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => void requestResend()}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary disabled:opacity-60"
                >
                    <MaterialIcon name="refresh" className="text-[20px]" />
                    {busy ? "Mengajukan…" : "Ajukan Pengiriman Ulang"}
                </button>

                <a
                    href={whatsappLink(whatsappMessageFor(resi))}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-center font-body-sm text-body-sm leading-relaxed text-secondary underline"
                >
                    Butuh bantuan langsung? Hubungi Layanan Pelanggan Anteraja Frozen.
                </a>
            </div>
        </div>
    );
}
