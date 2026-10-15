import { useEffect, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import type { ScreenProps } from "../../App";
import { ActionError } from "../../components/ActionError";
import { SelectField, TextAreaField, TextField } from "../../components/Field";
import { PhotoField } from "../../components/PhotoField";
import { StateCard } from "../../components/StateCard";
import { ApiError, courierApi, firstError } from "../../lib/api";
import { type PickedPhoto, releasePhoto } from "../../lib/photo";
import { useRouter } from "../../lib/router";
import { useLoad } from "../../lib/useLoad";

const NOTE_LIMIT = 200;

/** Reporting trouble with a parcel from the field. */
export default function Issue({ params, query }: ScreenProps) {
    const resi = params.resi ?? "";
    const reason = query.get("reason") ?? "";
    const { navigate } = useRouter();
    const { data, error, reload } = useLoad(
        () =>
            courierApi.deliveries.issueForm(resi).catch((failure: unknown) => {
                if (failure instanceof ApiError) {
                    return null;
                }

                throw failure;
            }),
        `courier-issue-${resi}`
    );
    const form = data?.issueForm ?? null;
    const [category, setCategory] = useState("");
    const [note, setNote] = useState("");
    const [temperature, setTemperature] = useState("");
    const [photo, setPhoto] = useState<PickedPhoto | null>(null);
    const [state, setState] = useState<"idle" | "done">("idle");
    const [actionError, setActionError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (form === null || category !== "") {
            return;
        }

        const preset = form.options.find((option) => option.label === reason);
        setCategory(preset?.value ?? form.options[0]?.value ?? "");
    }, [category, form, reason]);

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
                        Kendala tidak dapat dilaporkan
                    </span>
                    <span className="text-center font-body-sm text-body-sm text-secondary">
                        Paket ini tidak berada pada status yang dapat dilaporkan kendalanya.
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

    const needsTemperature = category === "suhu_naik";

    async function submit() {
        if (state === "done" || busy) {
            return;
        }

        if (note.trim().length < 10) {
            setActionError("Catatan kendala minimal 10 karakter.");
            return;
        }

        if (photo === null) {
            setActionError("Foto bukti fisik wajib diambil.");
            return;
        }

        if (needsTemperature && temperature.trim() === "") {
            setActionError("Suhu paket wajib diisi untuk kendala suhu naik.");
            return;
        }

        setBusy(true);
        setActionError(null);

        try {
            const body = new FormData();
            body.append("issue_category", category);
            body.append("description", note.trim());

            if (needsTemperature) {
                body.append("temperature_c", temperature.trim());
            }

            body.append("photo", photo.file, photo.name);
            await courierApi.deliveries.reportIssue(resi, body);
            setState("done");
        } catch (failure) {
            setActionError(firstError(failure));
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="flex w-full flex-col gap-space-xl p-margin pb-space-xl">
            <section className="flex w-full flex-col gap-2 rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Nomor Resi</span>
                    <span className="rounded-full bg-error-container px-space-md py-0.5 font-label-sm text-label-sm text-on-error-container">
                        Kendala Lapangan
                    </span>
                </div>
                <span className="font-headline-md text-headline-md tracking-tight text-on-surface select-all">
                    {form.tracking}
                </span>
                <div className="flex w-full gap-space-lg pt-1">
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Penerima</span>
                        <span className="font-headline-sm text-headline-sm text-on-surface">{form.recipient}</span>
                    </span>
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Kurir Pelapor</span>
                        <span className="font-body-md text-body-md text-on-surface">{form.reporter}</span>
                    </span>
                </div>
            </section>

            <section className="flex w-full flex-col gap-space-xl">
                <SelectField label="Jenis Kendala" value={category} onChange={setCategory} options={form.options} />

                {needsTemperature ? (
                    <TextField
                        label="Suhu Paket (°C)"
                        value={temperature}
                        onChange={setTemperature}
                        placeholder="Contoh: -12"
                        type="number"
                        inputMode="decimal"
                        min={-60}
                        max={40}
                        supporting="Wajib untuk kendala suhu naik"
                    />
                ) : null}

                <div className="flex w-full flex-col gap-1">
                    <TextAreaField
                        label="Catatan Kendala"
                        value={note}
                        onChange={setNote}
                        rows={4}
                        supporting="Berikan rincian kondisi paket secara jelas"
                    />
                    <span className="font-body-sm text-body-sm text-secondary">
                        {note.length}/{NOTE_LIMIT}
                    </span>
                </div>

                <PhotoField
                    label="Bukti Foto Fisik"
                    photo={photo}
                    onPick={setPhoto}
                    actionLabel="Ambil Foto"
                    supporting="Foto kemasan paket apa adanya, sebelum disimpan kembali."
                />

                <div className="flex w-full items-start gap-space-sm rounded-xl bg-surface-container-low p-space-lg">
                    <MaterialIcon name="info" className="text-[20px] text-primary" />
                    <span className="flex-1 font-body-sm text-body-sm leading-snug text-secondary">
                        Laporan ini otomatis memberitahukan tim operasional Hub dan customer service. Paket harap
                        disimpan kembali di kantong tahan suhu.
                    </span>
                </div>
            </section>

            <ActionError message={actionError} />

            {state === "done" ? (
                <button
                    type="button"
                    onClick={() => navigate("/courier/tasks", { replace: true })}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-tertiary-fixed px-space-lg font-label-md text-label-md text-tertiary"
                >
                    <MaterialIcon name="check_circle" className="text-[20px]" />
                    Laporan Terkirim
                </button>
            ) : (
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => void submit()}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary disabled:opacity-60"
                >
                    <MaterialIcon name="send" className="text-[20px]" />
                    {busy ? "Mengirim laporan…" : "Kirim Laporan"}
                </button>
            )}
        </div>
    );
}
