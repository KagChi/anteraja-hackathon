import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import type { ScreenProps } from "../../App";
import { ActionError } from "../../components/ActionError";
import { MissingOrder } from "../../components/MissingOrder";
import { Sheet } from "../../components/Sheet";
import { StateCard } from "../../components/StateCard";
import { ApiError, clientApi, firstError } from "../../lib/api";
import { splitScheduleLabel } from "../../lib/format";
import { useLoad } from "../../lib/useLoad";

const JAKARTA = "Asia/Jakarta";

function jakartaDate(offsetDays: number): string {
    const today = new Date(new Intl.DateTimeFormat("en-CA", { timeZone: JAKARTA }).format(new Date()));
    today.setDate(today.getDate() + offsetDays);

    return new Intl.DateTimeFormat("en-CA", { timeZone: JAKARTA }).format(today);
}

/** Accept the delivery window, or ask the hub for another one. */
export default function Schedule({ params }: ScreenProps) {
    const resi = params.resi ?? "";
    const { data, error, reload } = useLoad(
        () =>
            clientApi.orders.schedule(resi).catch((failure: unknown) => {
                if (failure instanceof ApiError && failure.status === 404) {
                    return null;
                }

                throw failure;
            }),
        `client-schedule-${resi}`
    );
    const [state, setState] = useState<"idle" | "confirmed" | "rescheduled">("idle");
    const [sheetOpen, setSheetOpen] = useState(false);
    const [rescheduleDate, setRescheduleDate] = useState(() => jakartaDate(0));
    const [rescheduleSlot, setRescheduleSlot] = useState("");
    const [actionError, setActionError] = useState<string | null>(null);

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

    const [windowDate, window] = splitScheduleLabel(view.scheduled_delivery);
    const cargo = view.cargo_items[0] ?? null;
    const dateOptions = [
        { value: jakartaDate(0), label: "Hari Ini" },
        { value: jakartaDate(1), label: "Besok" }
    ];

    async function confirm() {
        setActionError(null);

        try {
            await clientApi.orders.confirmSchedule(resi);
            setState("confirmed");
        } catch (failure) {
            setActionError(firstError(failure));
        }
    }

    async function submitReschedule() {
        if (rescheduleSlot === "") {
            setActionError("Pilih jadwal baru dulu.");
            return;
        }

        setActionError(null);

        try {
            await clientApi.orders.reschedule(resi, rescheduleDate, rescheduleSlot);
            setSheetOpen(false);
            setState("rescheduled");
        } catch (failure) {
            setActionError(firstError(failure));
        }
    }

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin pb-space-xl">
            <div className="flex w-full items-center justify-between gap-2">
                <span className="flex items-center gap-space-sm rounded bg-secondary-container px-space-md py-1">
                    <MaterialIcon name="circle" filled className="text-[8px] text-primary" />
                    <span className="font-body-sm text-body-sm text-on-secondary-container uppercase">
                        {view.status_label}
                    </span>
                </span>
                <span className="font-body-sm text-body-sm text-secondary select-all">ID: {view.tracking_number}</span>
            </div>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Tujuan Pengiriman</span>
                <div className="flex w-full items-start gap-space-md">
                    <span className="flex h-8 w-8 items-center justify-center rounded bg-surface-container">
                        <MaterialIcon name="location_on" className="text-[18px] text-secondary" />
                    </span>
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-headline-md text-headline-md text-on-surface">{view.recipient_name}</span>
                        <span className="font-body-sm text-body-sm leading-snug text-secondary select-all">
                            {view.recipient_address}
                        </span>
                    </span>
                </div>
            </section>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between gap-2">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Detail Muatan Beku</span>
                    <span className="rounded-full bg-tertiary-fixed px-space-md py-1 font-label-sm text-label-sm text-tertiary">
                        {view.cold_guaranteed_badge}
                    </span>
                </div>

                <div className="flex w-full items-center gap-space-md">
                    <span className="flex h-16 w-16 items-center justify-center rounded-lg bg-surface-container">
                        <MaterialIcon name="ac_unit" className="text-[28px] text-tertiary" />
                    </span>
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-tertiary">
                            {cargo?.category ?? "Makanan Beku (Frozen Food)"}
                        </span>
                        <span className="font-headline-md text-headline-md text-on-surface">
                            {cargo?.name ?? "Paket Frozen"}
                        </span>
                        <span className="font-body-sm text-body-sm text-secondary">{cargo?.quantity_label ?? ""}</span>
                    </span>
                </div>

                <div className="flex w-full items-start gap-space-sm rounded bg-surface-container-low p-space-md">
                    <MaterialIcon name="inventory_2" className="text-[16px] text-tertiary" />
                    <span className="flex-1 font-body-sm text-body-sm leading-relaxed text-on-surface-variant">
                        {view.handling_note}
                    </span>
                </div>
            </section>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between gap-2">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">
                        Jadwal Pengantaran Terpilih
                    </span>
                    <span className="font-label-md text-label-md text-primary">
                        {view.has_cold_priority ? "Prioritas Dingin" : "Reguler"}
                    </span>
                </div>

                <div className="flex w-full flex-col gap-1 rounded bg-surface-container p-space-lg">
                    <span className="flex items-center gap-space-sm">
                        <MaterialIcon name="schedule" className="text-[20px] text-primary" />
                        <span className="font-headline-md text-headline-md text-on-surface">{window}</span>
                    </span>
                    <span className="font-body-sm text-body-sm text-secondary">{windowDate}</span>
                </div>

                <div className="flex w-full items-center justify-between gap-2">
                    <span className="flex items-center gap-space-md">
                        <span className="flex h-9 w-9 items-center justify-center rounded-full bg-secondary-container">
                            <MaterialIcon name="two_wheeler" className="text-[20px] text-on-secondary-container" />
                        </span>
                        <span className="flex flex-col gap-0.5">
                            <span className="font-body-md text-body-md text-on-surface">
                                {view.courier_name ?? "-"}
                            </span>
                            <span className="font-body-sm text-body-sm text-secondary">
                                {view.courier_badge ?? "Kurir Khusus Frozen Anteraja"}
                            </span>
                        </span>
                    </span>
                    <span className="flex h-6 w-6 items-center justify-center rounded-full bg-tertiary-fixed">
                        <MaterialIcon name="verified" className="text-[14px] text-tertiary" />
                    </span>
                </div>
            </section>

            <div className="flex w-full items-start gap-space-md rounded-xl bg-surface-container-low p-space-lg">
                <MaterialIcon name="info" className="text-[20px] text-primary" />
                <span className="flex flex-1 flex-col gap-0.5">
                    <span className="font-headline-sm text-headline-sm text-on-surface">
                        Penting Sebelum Konfirmasi
                    </span>
                    <span className="font-body-sm text-body-sm leading-relaxed text-secondary">
                        {view.handling_note}
                    </span>
                </span>
            </div>

            <ActionError message={actionError} />

            <div className="flex w-full flex-col gap-space-md pt-1">
                {state === "confirmed" ? (
                    <button
                        type="button"
                        disabled
                        className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-tertiary-fixed px-space-lg font-label-md text-label-md text-tertiary"
                    >
                        <MaterialIcon name="check_circle" className="text-[20px]" />
                        Jadwal Terkonfirmasi
                    </button>
                ) : state === "rescheduled" ? (
                    <button
                        type="button"
                        disabled
                        className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-tertiary-fixed px-space-lg font-label-md text-label-md text-tertiary"
                    >
                        <MaterialIcon name="event_available" className="text-[20px]" />
                        Permintaan Jadwal Terkirim
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={() => void confirm()}
                        className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                    >
                        <MaterialIcon name="check_circle" className="text-[20px]" />
                        Siap Menerima
                    </button>
                )}

                <button
                    type="button"
                    onClick={() => setSheetOpen(true)}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
                >
                    <MaterialIcon name="event_available" className="text-[20px]" />
                    Minta Jadwal Lain
                </button>
            </div>

            {sheetOpen ? (
                <Sheet onClose={() => setSheetOpen(false)} label="Pilih Jadwal Baru">
                    <div className="flex w-full flex-col gap-space-lg p-space-lg">
                        <span className="font-headline-md text-headline-md text-on-surface">Pilih Jadwal Baru</span>

                        <div className="flex w-full flex-col gap-2">
                            <span className="font-label-sm text-label-sm text-secondary uppercase">Tanggal</span>
                            <div className="flex w-full gap-space-sm">
                                {dateOptions.map((option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => setRescheduleDate(option.value)}
                                        className={`flex flex-1 items-center justify-center rounded-xl p-space-md font-body-md text-body-md ${
                                            rescheduleDate === option.value
                                                ? "bg-primary text-on-primary"
                                                : "bg-surface-container-low text-on-surface"
                                        }`}
                                    >
                                        {option.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="flex w-full flex-col gap-2">
                            <span className="font-label-sm text-label-sm text-secondary uppercase">Waktu</span>
                            {view.available_slots.map((slot) => (
                                <button
                                    key={slot}
                                    type="button"
                                    onClick={() => setRescheduleSlot(slot)}
                                    className={`w-full rounded-lg p-space-md text-left font-body-md text-body-md ${
                                        rescheduleSlot === slot
                                            ? "bg-primary text-on-primary"
                                            : "bg-surface-container-low text-on-surface"
                                    }`}
                                >
                                    {slot} WIB
                                </button>
                            ))}
                        </div>

                        <button
                            type="button"
                            onClick={() => void submitReschedule()}
                            className="inline-flex h-12 w-full items-center justify-center rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                        >
                            Kirim Permintaan
                        </button>
                        <button
                            type="button"
                            onClick={() => setSheetOpen(false)}
                            className="inline-flex h-12 w-full items-center justify-center rounded-xl bg-surface-container-low px-space-lg font-label-md text-label-md text-on-surface"
                        >
                            Batal
                        </button>
                    </div>
                </Sheet>
            ) : null}
        </div>
    );
}
