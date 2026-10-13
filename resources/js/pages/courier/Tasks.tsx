import { Head } from "@inertiajs/react";
import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { TASK_DETAIL } from "@/data/courier";
import { CourierLayout } from "@/layouts";
import { cx } from "@/lib/cx";

type TaskState = "pending" | "processing" | "accepted" | "rejected";

export default function Tasks() {
    const [state, setState] = useState<TaskState>("pending");
    const [toastDismissed, setToastDismissed] = useState(false);
    const task = TASK_DETAIL;

    function accept() {
        if (state !== "pending") return;
        setToastDismissed(false);
        setState("processing");
        window.setTimeout(() => setState("accepted"), 600);
    }

    function reject() {
        if (state !== "pending") return;
        setToastDismissed(false);
        setState("rejected");
    }

    const toast =
        state === "accepted"
            ? "Tugas berhasil diterima! Navigasi segera dibuka."
            : state === "rejected"
              ? `Tugas ${task.tracking} telah ditolak.`
              : null;

    return (
        <CourierLayout showCourier>
            <Head title="Tugas Pengiriman" />

            <div className="flex w-full flex-col gap-space-md">
                <div className="flex items-center justify-between rounded-xl bg-surface-container-high p-space-md">
                    <div className="flex items-center gap-space-sm">
                        <span className="inline-flex items-center rounded-full bg-primary px-2 py-0.5 font-label-sm text-label-sm uppercase tracking-wide text-on-primary">
                            Tugas Baru
                        </span>
                        <span className="font-body-sm text-body-sm text-secondary">{task.age}</span>
                    </div>
                    <span className="font-label-sm text-label-sm text-on-surface-variant">ID: {task.id}</span>
                </div>

                <section className="flex flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                    <div className="flex items-start justify-between gap-space-sm pb-space-xs">
                        <div>
                            <span className="block font-label-sm text-label-sm uppercase text-secondary">
                                Nomor Resi / AWB
                            </span>
                            <span className="select-all font-data-mono text-headline-sm font-semibold tracking-tight text-on-surface">
                                {task.tracking}
                            </span>
                        </div>
                        <span className="inline-flex items-center gap-1 rounded-full bg-surface-container-high px-2.5 py-1 font-label-sm text-label-sm text-on-surface">
                            <MaterialIcon name="ac_unit" className="text-[16px] text-primary" />
                            {task.service}
                        </span>
                    </div>

                    <div className="flex items-center justify-between rounded-lg bg-surface-container p-space-sm">
                        <div className="flex items-center gap-space-sm">
                            <MaterialIcon name="schedule" className="text-[20px] text-primary" />
                            <div className="flex flex-col">
                                <span className="font-label-sm text-label-sm text-secondary">Jendela Pengantaran</span>
                                <span className="font-label-md text-label-md text-on-surface">{task.window}</span>
                            </div>
                        </div>
                        <span className="rounded-full bg-tertiary-fixed px-2 py-0.5 font-label-sm text-label-sm text-tertiary">
                            {task.windowStatus}
                        </span>
                    </div>

                    <div className="flex flex-col gap-space-xs pt-space-xs">
                        <div className="flex items-center justify-between">
                            <span className="font-label-sm text-label-sm text-secondary">Penerima Paket</span>
                            <span className="font-label-sm text-label-sm text-secondary">{task.placeType}</span>
                        </div>
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">{task.recipient}</h2>
                        <p className="font-body-md text-body-md leading-relaxed text-secondary">{task.address}</p>
                    </div>

                    <div className="flex flex-col gap-1 rounded-lg bg-surface-container-low p-space-md">
                        <div className="flex items-center gap-1.5 text-primary">
                            <MaterialIcon name="warning" className="text-[18px]" />
                            <span className="font-label-sm text-label-sm font-bold uppercase tracking-wider">
                                Instruksi Khusus
                            </span>
                        </div>
                        <p className="font-body-md text-body-md font-medium leading-snug text-on-surface">
                            {task.instruction}
                        </p>
                    </div>

                    <div className="grid grid-cols-3 gap-space-sm pt-space-xs">
                        {task.metrics.map((metric) => (
                            <div key={metric.label} className="rounded-lg bg-surface-container p-space-sm text-center">
                                <span className="block font-label-sm text-label-sm text-secondary">{metric.label}</span>
                                <span className="font-label-md text-label-md font-semibold text-on-surface">
                                    {metric.value}
                                </span>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="flex items-center justify-between rounded-xl bg-surface-container-lowest p-space-md">
                    <div className="flex items-center gap-space-sm">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-surface-container">
                            <MaterialIcon name="near_me" className="text-[20px] text-on-surface" />
                        </div>
                        <div className="flex flex-col">
                            <span className="font-label-md text-label-md text-on-surface">Jarak Pengantaran</span>
                            <span className="font-body-sm text-body-sm text-secondary">{task.distance}</span>
                        </div>
                    </div>
                    <span className="font-label-md text-label-md font-semibold text-on-surface">{task.eta}</span>
                </div>

                {state === "accepted" ? (
                    <button
                        type="button"
                        disabled
                        className="flex h-[52px] w-full items-center justify-center gap-2 rounded-xl bg-primary font-label-lg text-label-lg text-on-primary"
                    >
                        <MaterialIcon name="done_all" className="text-[22px]" />
                        Tugas Diterima
                    </button>
                ) : (
                    <div className="flex w-full items-center gap-space-md pt-space-xs">
                        <button
                            type="button"
                            disabled={state !== "pending"}
                            onClick={reject}
                            className={cx(
                                "flex h-[52px] flex-1 items-center justify-center gap-1.5 rounded-xl bg-surface-container-high font-label-lg text-label-lg text-on-surface transition-colors active:bg-surface-dim",
                                state !== "pending" && "opacity-50"
                            )}
                        >
                            <MaterialIcon name="close" className="text-[20px]" />
                            Tolak
                        </button>
                        <button
                            type="button"
                            disabled={state !== "pending"}
                            onClick={accept}
                            className={cx(
                                "flex h-[52px] flex-[2] items-center justify-center gap-2 rounded-xl bg-primary font-label-lg text-label-lg text-on-primary shadow-sm transition-colors active:bg-primary-container",
                                state !== "pending" && "opacity-50"
                            )}
                        >
                            <MaterialIcon
                                name={state === "processing" ? "refresh" : "check_circle"}
                                className={cx("text-[22px]", state === "processing" && "animate-spin")}
                            />
                            {state === "processing" ? "Memproses..." : "Terima Tugas"}
                        </button>
                    </div>
                )}

                {toast && !toastDismissed ? (
                    <div className="flex items-center justify-between gap-space-sm rounded-xl bg-inverse-surface p-space-md text-inverse-on-surface">
                        <span className="font-body-md text-body-md">{toast}</span>
                        <button
                            type="button"
                            onClick={() => setToastDismissed(true)}
                            className="shrink-0 rounded-md px-2 py-1 font-label-sm text-label-sm text-inverse-primary"
                        >
                            Tutup
                        </button>
                    </div>
                ) : null}
            </div>
        </CourierLayout>
    );
}
