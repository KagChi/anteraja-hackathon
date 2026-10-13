import { type FormEvent, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { PhotoInput } from "@/components/PhotoInput";
import { ISSUE_FORM } from "@/data/courier";
import { CourierLayout } from "@/layouts";
import { cx } from "@/lib/cx";
import { usePhoto } from "@/lib/photo";

type SubmitState = "idle" | "sending" | "done";

export default function Issue() {
    const form = ISSUE_FORM;
    const [issueType, setIssueType] = useState(form.options[0].value);
    const [note, setNote] = useState(form.note);
    const {
        photo,
        open: openCamera,
        onFile,
        inputRef,
        reset: clearPhoto
    } = usePhoto({
        name: form.photoName,
        meta: form.photoMeta
    });
    const [submitState, setSubmitState] = useState<SubmitState>("idle");

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (submitState !== "idle") return;
        setSubmitState("sending");
        window.setTimeout(() => setSubmitState("done"), 800);
    }

    return (
        <CourierLayout title="Lapor Masalah" backTo="/courier/delivery">
            <div className="flex w-full flex-col gap-space-lg pb-8">
                <section
                    aria-label="Informasi Paket Terkait"
                    className="flex flex-col gap-space-sm rounded-xl bg-surface-container-lowest p-margin shadow-sm"
                >
                    <div className="flex items-center justify-between">
                        <span className="font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                            Nomor Resi
                        </span>
                        <span className="rounded-full bg-error-container px-2 py-0.5 font-label-sm text-label-sm text-on-error-container">
                            Kendala Lapangan
                        </span>
                    </div>
                    <div className="font-data-mono text-headline-sm font-semibold tracking-tight text-on-surface">
                        {form.tracking}
                    </div>
                    <div className="mt-space-xs grid grid-cols-2 gap-space-md border-t border-surface-container pt-space-xs">
                        <div className="flex min-w-0 flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Penerima</span>
                            <span className="truncate font-body-md text-body-md font-semibold text-on-surface">
                                {form.recipient}
                            </span>
                        </div>
                        <div className="flex min-w-0 flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Kurir Pelapor</span>
                            <span className="truncate font-body-md text-body-md text-on-surface">{form.reporter}</span>
                        </div>
                    </div>
                </section>

                <form className="flex flex-col gap-space-lg" onSubmit={handleSubmit}>
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="jenis-kendala"
                            className="font-label-md text-label-md font-semibold text-on-surface"
                        >
                            Jenis Kendala <span className="text-error">*</span>
                        </label>
                        <div className="relative w-full">
                            <select
                                id="jenis-kendala"
                                name="jenis_kendala"
                                value={issueType}
                                onChange={(event) => setIssueType(event.target.value)}
                                className="h-12 w-full cursor-pointer appearance-none rounded-xl bg-surface-container-lowest px-space-md pr-10 font-body-md text-body-md text-on-surface shadow-sm focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                {form.options.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-secondary">
                                <MaterialIcon name="expand_more" className="text-[20px]" />
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="catatan-kendala"
                            className="font-label-md text-label-md font-semibold text-on-surface"
                        >
                            Catatan Kendala <span className="text-error">*</span>
                        </label>
                        <textarea
                            id="catatan-kendala"
                            name="catatan_kendala"
                            rows={3}
                            maxLength={form.noteLimit}
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            className="w-full resize-none rounded-xl bg-surface-container-lowest p-space-md font-body-md text-body-md text-on-surface shadow-sm placeholder:text-secondary focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                        <div className="flex items-center justify-between px-1">
                            <span className="font-body-sm text-body-sm text-secondary">
                                Berikan rincian kondisi paket secara jelas
                            </span>
                            <span className="font-body-sm text-body-sm text-secondary">
                                {note.length}/{form.noteLimit}
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between">
                            <span className="font-label-md text-label-md font-semibold text-on-surface">
                                Bukti Foto Fisik <span className="text-error">*</span>
                            </span>
                            {photo ? (
                                <span className="flex items-center gap-1 font-body-sm text-body-sm font-semibold text-tertiary">
                                    <MaterialIcon name="check_circle" className="text-[16px]" />1 Terlampir
                                </span>
                            ) : null}
                        </div>
                        {photo ? (
                            <div className="flex items-center justify-between gap-space-md rounded-xl bg-surface-container-lowest p-space-md shadow-sm">
                                <div className="flex min-w-0 items-center gap-space-md">
                                    <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-container-high">
                                        <img
                                            src={photo.url}
                                            alt="Bukti foto kemasan paket beku"
                                            className="h-full w-full object-cover"
                                        />
                                    </div>
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate font-label-md text-label-md font-semibold text-on-surface">
                                            {photo.name}
                                        </span>
                                        <span className="font-body-sm text-body-sm text-secondary">{photo.meta}</span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    aria-label="Hapus foto"
                                    onClick={clearPhoto}
                                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-secondary transition-colors hover:bg-error-container hover:text-error"
                                >
                                    <MaterialIcon name="delete" className="text-[20px]" />
                                </button>
                            </div>
                        ) : null}
                        <button
                            type="button"
                            onClick={openCamera}
                            className="mt-1 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-surface-container-low font-label-md text-label-md text-on-surface transition-all hover:bg-surface-container active:scale-[0.99]"
                        >
                            <MaterialIcon name="photo_camera" className="text-[20px]" />
                            Ambil Foto Ulang
                        </button>
                        <PhotoInput inputRef={inputRef} onFile={onFile} />
                    </div>

                    <div className="flex items-start gap-space-sm rounded-xl bg-surface-container-low p-space-md">
                        <MaterialIcon name="info" className="mt-0.5 shrink-0 text-[20px] text-primary" />
                        <p className="font-body-sm text-body-sm text-secondary">
                            Laporan ini otomatis memberitahukan tim operasional Hub dan customer service. Paket harap
                            disimpan kembali di kantong tahan suhu.
                        </p>
                    </div>

                    <div className="pt-space-xs">
                        <button
                            type="submit"
                            disabled={submitState !== "idle"}
                            className={cx(
                                "flex h-[52px] w-full items-center justify-center gap-2 rounded-xl font-label-lg text-label-lg tracking-wide shadow-md transition-all active:scale-[0.99]",
                                submitState === "done"
                                    ? "bg-tertiary text-on-tertiary"
                                    : "bg-primary text-on-primary active:bg-primary-container"
                            )}
                        >
                            {submitState === "done" ? (
                                <>
                                    <MaterialIcon name="check_circle" className="text-[20px]" />
                                    Laporan Terkirim
                                </>
                            ) : submitState === "sending" ? (
                                <>
                                    <MaterialIcon name="progress_activity" className="animate-spin text-[20px]" />
                                    Mengirim Laporan...
                                </>
                            ) : (
                                <>
                                    <MaterialIcon name="send" className="text-[22px]" />
                                    Kirim Laporan
                                </>
                            )}
                        </button>
                    </div>
                </form>
            </div>
        </CourierLayout>
    );
}
