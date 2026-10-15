import { useEffect, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import type { ScreenProps } from "../../App";
import { ActionError } from "../../components/ActionError";
import { TextAreaField, TextField } from "../../components/Field";
import { PhotoField } from "../../components/PhotoField";
import { StateCard } from "../../components/StateCard";
import { ApiError, courierApi, firstError } from "../../lib/api";
import { accuracyLabel, gpsFields } from "../../lib/geo";
import { type PickedPhoto, releasePhoto } from "../../lib/photo";
import { useRouter } from "../../lib/router";
import { useFixWindow } from "../../lib/useFixWindow";
import { useLoad } from "../../lib/useLoad";

/**
 * Proof of delivery: who physically took the parcel, the photo that shows it,
 * and the fix window behind the hand-over.
 */
export default function Proof({ params }: ScreenProps) {
    const resi = params.resi ?? "";
    const { navigate } = useRouter();
    const { fixes, latest } = useFixWindow();
    const { data, error, reload } = useLoad(
        () =>
            courierApi.deliveries.proofForm(resi).catch((failure: unknown) => {
                if (failure instanceof ApiError) {
                    return null;
                }

                throw failure;
            }),
        `courier-proof-${resi}`
    );
    const [recipientName, setRecipientName] = useState("");
    const [relation, setRelation] = useState("");
    const [pin, setPin] = useState("");
    const [notes, setNotes] = useState("");
    const [photo, setPhoto] = useState<PickedPhoto | null>(null);
    const [state, setState] = useState<"idle" | "done">("idle");
    const [actionError, setActionError] = useState<string | null>(null);
    const [gpsNotice, setGpsNotice] = useState("");
    const [busy, setBusy] = useState(false);
    const form = data?.proofForm ?? null;

    useEffect(() => {
        if (form !== null && relation === "") {
            setRelation(form.relations[0] ?? "Penerima Sendiri");
        }
    }, [form, relation]);

    useEffect(() => () => releasePhoto(photo), [photo]);

    if (error !== null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <StateCard message={error} onRetry={reload} />
            </div>
        );
    }

    if (form === null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <section className="flex w-full flex-col items-center gap-space-sm rounded-xl bg-surface-container-lowest p-space-xl">
                    <span className="font-headline-md text-headline-md text-on-surface">
                        Paket tidak dapat dikonfirmasi
                    </span>
                    <span className="text-center font-body-sm text-body-sm text-secondary">
                        Paket ini tidak berada pada status pengantaran aktif.
                    </span>
                    <button
                        type="button"
                        onClick={() => navigate("/courier/tasks")}
                        className="mt-space-sm inline-flex h-11 items-center justify-center rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                    >
                        Kembali ke Tugas
                    </button>
                </section>
            </div>
        );
    }

    async function submit() {
        if (recipientName.trim() === "" || state === "done" || busy) {
            return;
        }

        if (pin.trim().length !== 6) {
            setActionError("Masukkan 6 digit PIN yang dikirim ke penerima.");
            return;
        }

        if (photo === null) {
            setActionError("Foto bukti penyerahan wajib diambil.");
            return;
        }

        setBusy(true);
        setActionError(null);

        try {
            const body = new FormData();
            body.append("received_by", recipientName.trim());
            body.append("recipient_relation", relation);
            body.append("pin", pin.trim());

            if (notes.trim() !== "") {
                body.append("receipt_note", notes.trim());
            }

            if (latest !== null) {
                const fields = gpsFields(fixes, latest);
                body.append("latitude", String(fields.latitude));
                body.append("longitude", String(fields.longitude));
                body.append("device_timestamp", String(fields.device_timestamp));

                fixes.forEach((fix, index) => {
                    body.append(`gps_signals[${index}][latitude]`, String(fix.latitude));
                    body.append(`gps_signals[${index}][longitude]`, String(fix.longitude));

                    if (fix.accuracy !== null) {
                        body.append(`gps_signals[${index}][accuracy]`, String(fix.accuracy));
                    }

                    body.append(`gps_signals[${index}][timestamp]`, String(fix.timestamp));
                });
            }

            body.append("photo", photo.file, photo.name);
            await courierApi.deliveries.confirm(resi, body);
            setState("done");
        } catch (failure) {
            setActionError(reasonMessage(failure));
        } finally {
            setBusy(false);
        }
    }

    async function requestReview() {
        setActionError(null);

        try {
            await courierApi.deliveries.requestReview(resi, "POD");
            setGpsNotice("Permintaan tinjauan terkirim ke hub.");
        } catch (failure) {
            setActionError(firstError(failure));
        }
    }

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin pb-space-xl">
            <section className="flex w-full flex-col gap-1 rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Nomor Resi</span>
                    <span className="rounded-full bg-secondary-container px-space-md py-0.5 font-label-sm text-label-sm text-on-secondary-container">
                        {form.service}
                    </span>
                </div>
                <span className="font-headline-md text-headline-md tracking-tight text-on-surface select-all">
                    {form.tracking}
                </span>
                <span className="flex items-center gap-1.5 pt-1">
                    <MaterialIcon name="person" className="text-[18px] text-on-surface-variant" />
                    <span className="font-body-md text-body-md text-on-surface-variant">
                        Tujuan Penerima: {form.recipient}
                    </span>
                </span>
            </section>

            <section className="flex w-full flex-col gap-space-xl rounded-xl bg-surface-container-lowest p-space-lg">
                <TextField
                    label="Nama Penerima Fisik"
                    value={recipientName}
                    onChange={setRecipientName}
                    placeholder="Masukkan nama orang yang menerima paket"
                    supporting="Pastikan nama sesuai dengan identitas penerima paket di lokasi."
                    autoComplete="name"
                />

                <div className="flex w-full flex-col gap-2">
                    <span className="font-body-md text-body-md text-on-surface">Hubungan dengan Penerima</span>
                    <div className="grid grid-cols-2 gap-space-sm">
                        {form.relations.map((option) => (
                            <button
                                key={option}
                                type="button"
                                onClick={() => setRelation(option)}
                                className={`flex w-full items-center justify-center rounded-xl p-space-md font-body-md text-body-md ${
                                    relation === option
                                        ? "bg-primary text-on-primary"
                                        : "bg-surface-container-low text-on-surface"
                                }`}
                            >
                                {option}
                            </button>
                        ))}
                    </div>
                </div>

                <TextField
                    label="PIN Penerimaan (6 digit)"
                    value={pin}
                    onChange={(value) => setPin(value.replace(/\D/g, "").slice(0, 6))}
                    placeholder="Contoh: 123456"
                    inputMode="numeric"
                    supporting="Minta kode 6 digit yang dikirim ke WhatsApp penerima, lalu masukkan di sini."
                />

                <PhotoField
                    label="Foto Bukti Penyerahan"
                    photo={photo}
                    onPick={setPhoto}
                    actionLabel="Ambil Foto Bukti"
                    supporting="Ambil foto paket bersama penerima atau di depan pintu rumah/lokasi penyerahan."
                />

                <TextAreaField
                    label="Catatan Tambahan"
                    value={notes}
                    onChange={setNotes}
                    placeholder={form.notesPlaceholder}
                    rows={2}
                    supporting="Opsional"
                />
            </section>

            <ActionError message={actionError} />

            <section className="flex w-full flex-col gap-2 rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between gap-2">
                    <span className="flex items-center gap-space-sm">
                        <MaterialIcon name="location_on" className="text-[18px] text-secondary" />
                        <span className="font-label-sm text-label-sm text-secondary uppercase">
                            Lokasi Serah Terima
                        </span>
                    </span>
                    <span className="font-body-sm text-body-sm text-secondary">{fixes.length} titik</span>
                </div>

                <span className="font-body-sm text-body-sm text-secondary">
                    {latest === null ? "Menunggu sinyal GPS perangkat." : accuracyLabel(latest.accuracy)}
                </span>
                <span className="font-body-sm text-body-sm leading-snug text-secondary">
                    Koordinat dan riwayat sinyal dikirim bersama bukti foto; hub memeriksanya terhadap radius titik
                    penerima.
                </span>

                {gpsNotice === "" ? null : (
                    <div className="flex w-full items-center gap-space-sm rounded-lg bg-tertiary-fixed p-space-md">
                        <MaterialIcon name="check_circle" className="text-[16px] text-on-tertiary-fixed" />
                        <span className="flex-1 font-body-sm text-body-sm text-on-tertiary-fixed">{gpsNotice}</span>
                    </div>
                )}

                {actionError === null ? null : (
                    <button
                        type="button"
                        onClick={() => void requestReview()}
                        className="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
                    >
                        <MaterialIcon name="help_outline" className="text-[18px]" />
                        Minta Tinjauan Hub
                    </button>
                )}
            </section>

            {state === "done" ? (
                <button
                    type="button"
                    onClick={() => navigate("/courier/tasks", { replace: true })}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-tertiary-fixed px-space-lg font-label-md text-label-md text-tertiary"
                >
                    <MaterialIcon name="check_circle" className="text-[20px]" />
                    Pengiriman Berhasil Selesai
                </button>
            ) : (
                <button
                    type="button"
                    disabled={recipientName.trim() === "" || pin.trim().length !== 6 || busy}
                    onClick={() => void submit()}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary disabled:opacity-60"
                >
                    <MaterialIcon name="task_alt" className="text-[20px]" />
                    {busy ? "Mengirim bukti…" : "Selesaikan Pengiriman"}
                </button>
            )}

            <span className="text-center font-body-sm text-body-sm text-secondary">
                Status paket akan langsung diperbarui ke sistem pusat Satria.
            </span>
        </div>
    );
}

/** A refused hand-over explains itself: the server sends the reasons with it. */
function reasonMessage(failure: unknown): string {
    if (failure instanceof ApiError) {
        const reasons = failure.payload.reasons;

        if (Array.isArray(reasons) && reasons.length > 0) {
            const first = reasons[0] as { label?: unknown };

            if (typeof first.label === "string" && first.label !== "") {
                return `${failure.message} ${first.label}.`;
            }
        }
    }

    return firstError(failure);
}
