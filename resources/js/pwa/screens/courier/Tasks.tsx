import { useEffect, useMemo, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ActionError } from "../../components/ActionError";
import { StateCard } from "../../components/StateCard";
import type { CourierTask } from "../../lib/api";
import { courierApi, firstError } from "../../lib/api";
import { currentPosition } from "../../lib/geo";
import { Link } from "../../lib/router";
import { useLoad } from "../../lib/useLoad";

/**
 * The task board: the first unaccepted job is on the board with everything the
 * courier needs to decide, and every other job is one tap away.
 */
export default function Tasks() {
    const { data, error, reload } = useLoad(() => courierApi.tasks.board(), "courier-tasks");
    const [decision, setDecision] = useState<"accepted" | "rejected" | null>(null);
    const [toastDismissed, setToastDismissed] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const [distance, setDistance] = useState<{ label: string; eta: string } | null>(null);
    const [busy, setBusy] = useState(false);

    const board = useMemo(() => {
        const incoming = data?.newTasks[0];

        if (incoming !== undefined) {
            return { task: incoming, isNew: true };
        }

        const active = data?.activeTasks[0];

        return active === undefined ? null : { task: active, isNew: false };
    }, [data]);

    const others: CourierTask[] = useMemo(() => {
        if (data === null) {
            return [];
        }

        const rest = [...data.newTasks, ...data.activeTasks];

        return board === null ? rest : rest.filter((task) => task.tracking !== board.task.tracking);
    }, [board, data]);

    const state = decision ?? (board === null ? null : board.isNew ? "pending" : "active");
    const tracking = board?.task.tracking ?? "";

    // The distance row: the server measures, the device only offers the fix.
    useEffect(() => {
        if (tracking === "") {
            return;
        }

        let cancelled = false;

        void currentPosition().then((point) => {
            if (point === null || cancelled) {
                return;
            }

            void courierApi.tasks
                .distance(tracking, point.latitude, point.longitude)
                .then((response) => {
                    if (cancelled || response === "") {
                        return;
                    }

                    setDistance({
                        label: `${parseFloat(response.distance_km.toFixed(1))} km dari lokasi Anda`,
                        eta: `~${Math.round(response.eta_minutes)} Menit`
                    });
                })
                .catch(() => undefined);
        });

        return () => {
            cancelled = true;
        };
    }, [tracking]);

    async function decide(accept: boolean) {
        if (tracking === "" || busy) {
            return;
        }

        setBusy(true);
        setActionError(null);

        try {
            if (accept) {
                await courierApi.tasks.accept(tracking);
                setDecision("accepted");
            } else {
                await courierApi.tasks.reject(tracking, "Ditolak dari aplikasi kurir");
                setDecision("rejected");
            }

            setToastDismissed(false);
        } catch (failure) {
            setActionError(firstError(failure));
        } finally {
            setBusy(false);
        }
    }

    if (error !== null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <StateCard message={error} onRetry={reload} />
            </div>
        );
    }

    if (board === null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <section className="flex w-full flex-col items-center gap-space-sm rounded-xl bg-surface-container-lowest p-space-xl">
                    <span className="font-headline-md text-headline-md text-on-surface">Tidak ada tugas</span>
                    <span className="text-center font-body-sm text-body-sm text-secondary">
                        Belum ada paket yang ditugaskan kepada Anda saat ini.
                    </span>
                </section>
            </div>
        );
    }

    const task = board.task;
    const toast =
        toastDismissed || decision === null
            ? null
            : decision === "accepted"
              ? "Tugas berhasil diterima! Navigasi segera dibuka."
              : `Tugas ${tracking} telah ditolak.`;

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin">
            <div className="flex w-full items-center justify-between rounded-xl bg-surface-container-high p-space-lg">
                <span className="flex items-center gap-space-sm">
                    <span className="inline-flex items-center rounded-full bg-primary px-2 py-0.5 font-label-sm text-label-sm text-on-primary uppercase">
                        Tugas Baru
                    </span>
                    <span className="font-body-sm text-body-sm text-secondary">{task.age}</span>
                </span>
                <span className="font-label-sm text-label-sm text-on-surface-variant">ID: {task.id}</span>
            </div>

            <section className="flex w-full flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-xl">
                <div className="flex w-full items-center justify-between gap-2 pb-1">
                    <span className="flex flex-col gap-1">
                        <span className="font-label-sm text-label-sm text-secondary uppercase">Nomor Resi / AWB</span>
                        <span className="font-headline-md text-headline-md tracking-tight text-on-surface select-all">
                            {task.tracking}
                        </span>
                    </span>
                    <span className="flex items-center gap-1 rounded-full bg-surface-container-high px-space-md py-1">
                        <MaterialIcon name="ac_unit" className="text-[16px] text-primary" />
                        <span className="font-body-sm text-body-sm text-on-surface">{task.service}</span>
                    </span>
                </div>

                <div className="flex w-full items-center justify-between rounded-lg bg-surface-container p-space-md">
                    <span className="flex items-center gap-space-sm">
                        <MaterialIcon name="schedule" className="text-[20px] text-primary" />
                        <span className="flex flex-col gap-0.5">
                            <span className="font-label-sm text-label-sm text-secondary">Jendela Pengantaran</span>
                            <span className="font-body-md text-body-md text-on-surface">{task.window}</span>
                        </span>
                    </span>
                    <span className="rounded-full bg-tertiary-fixed px-2 py-0.5 font-label-sm text-label-sm text-tertiary">
                        {task.windowStatus}
                    </span>
                </div>

                <div className="flex w-full flex-col gap-2 pt-1">
                    <div className="flex w-full items-center justify-between">
                        <span className="font-body-sm text-body-sm text-secondary">Penerima Paket</span>
                        <span className="font-body-sm text-body-sm text-secondary">{task.placeType}</span>
                    </div>
                    <span className="font-headline-md text-headline-md text-on-surface">{task.recipient}</span>
                    <span className="font-body-md text-body-md leading-relaxed text-secondary">{task.address}</span>
                </div>

                <div className="flex w-full flex-col gap-1 rounded-lg bg-surface-container-low p-space-lg">
                    <span className="flex items-center gap-1.5">
                        <MaterialIcon name="warning" className="text-[18px] text-primary" />
                        <span className="font-label-sm text-label-sm text-primary uppercase">Instruksi Khusus</span>
                    </span>
                    <span className="font-body-md text-body-md leading-snug text-on-surface">
                        {task.instruction ?? "Tidak ada instruksi khusus."}
                    </span>
                </div>

                <div className="flex w-full gap-space-sm">
                    {task.metrics.map((metric) => (
                        <span
                            key={metric.label}
                            className="flex flex-1 flex-col items-center gap-1 rounded-lg bg-surface-container p-2"
                        >
                            <span className="font-label-sm text-label-sm text-secondary">{metric.label}</span>
                            <span className="font-label-md text-label-md text-on-surface">{metric.value}</span>
                        </span>
                    ))}
                </div>
            </section>

            <div className="flex w-full items-center justify-between rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="flex items-center gap-space-sm">
                    <MaterialIcon name="near_me" className="text-[20px] text-on-surface" />
                    <span className="flex flex-col gap-0.5">
                        <span className="font-body-md text-body-md text-on-surface">Jarak Pengantaran</span>
                        <span className="font-body-sm text-body-sm text-secondary">
                            {distance?.label ?? "Mengukur jarak…"}
                        </span>
                    </span>
                </span>
                <span className="font-label-md text-label-md text-on-surface">{distance?.eta ?? ""}</span>
            </div>

            <ActionError message={actionError} />

            {state === "accepted" ? (
                <Link
                    href={`/courier/delivery/${task.tracking}`}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                >
                    <MaterialIcon name="local_shipping" className="text-[20px]" />
                    Buka Detail Pengantaran
                </Link>
            ) : null}

            {state === "active" ? (
                <Link
                    href={`/courier/delivery/${task.tracking}`}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                >
                    <MaterialIcon name="local_shipping" className="text-[20px]" />
                    Lanjutkan Pengantaran
                </Link>
            ) : null}

            {state === "accepted" || state === "active" ? null : (
                <div className="flex w-full items-center gap-space-lg pt-1">
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => void decide(false)}
                        className="inline-flex h-12 flex-1 items-center justify-center rounded-xl bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface disabled:opacity-60"
                    >
                        Tolak
                    </button>
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => void decide(true)}
                        className="inline-flex h-12 flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary disabled:opacity-60"
                    >
                        <MaterialIcon name="check_circle" className="text-[20px]" />
                        Terima Tugas
                    </button>
                </div>
            )}

            {toast === null ? null : (
                <div className="flex w-full items-center justify-between gap-2 rounded-xl bg-inverse-surface p-space-lg">
                    <span className="flex-1 font-body-md text-body-md text-inverse-on-surface">{toast}</span>
                    <button
                        type="button"
                        onClick={() => setToastDismissed(true)}
                        className="rounded-md px-2 py-1 font-body-sm text-body-sm text-inverse-primary"
                    >
                        Tutup
                    </button>
                </div>
            )}

            {others.length === 0 ? null : (
                <section className="flex w-full flex-col gap-space-sm">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">
                        Tugas Lain ({others.length})
                    </span>

                    {others.map((other) => (
                        <Link
                            key={other.tracking}
                            href={`/courier/delivery/${other.tracking}`}
                            className="flex w-full items-center justify-between gap-space-md rounded-xl bg-surface-container-lowest p-space-lg"
                        >
                            <span className="flex flex-col gap-0.5">
                                <span className="font-headline-sm text-headline-sm text-on-surface">
                                    {other.tracking}
                                </span>
                                <span className="font-body-sm text-body-sm text-secondary">
                                    {other.recipient} • {other.statusLabel}
                                </span>
                            </span>
                            <MaterialIcon name="arrow_forward" className="text-[18px] text-primary" />
                        </Link>
                    ))}
                </section>
            )}
        </div>
    );
}
