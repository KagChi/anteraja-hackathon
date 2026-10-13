import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { PhotoInput } from "@/components/PhotoInput";
import { PROOF_FORM } from "@/data/courier";
import { CourierLayout } from "@/layouts";
import { cx } from "@/lib/cx";
import { usePhoto } from "@/lib/photo";

type SubmitState = "idle" | "saving" | "done";

export default function Proof() {
    const form = PROOF_FORM;
    const [recipientName, setRecipientName] = useState(form.recipient);
    const [relation, setRelation] = useState(form.relations[0]);
    const {
        photo,
        open: openCamera,
        onFile,
        inputRef
    } = usePhoto({
        name: form.photoName,
        meta: form.photoMeta
    });
    const [notes, setNotes] = useState("");
    const [submitState, setSubmitState] = useState<SubmitState>("idle");

    function handleSubmit() {
        if (!recipientName.trim() || submitState !== "idle") return;
        setSubmitState("saving");
        window.setTimeout(() => setSubmitState("done"), 700);
    }

    return (
        <CourierLayout title="Konfirmasi Pengiriman" backTo="/courier/delivery">
            <div className="flex w-full flex-col gap-space-lg pb-6">
                <section className="flex flex-col gap-space-xs rounded-xl bg-surface-container-lowest p-margin">
                    <div className="flex items-center justify-between">
                        <span className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                            Nomor Resi
                        </span>
                        <span className="rounded-full bg-secondary-container px-2 py-0.5 font-label-sm text-label-sm text-on-secondary-fixed">
                            {form.service}
                        </span>
                    </div>
                    <div className="select-all font-data-mono font-semibold text-headline-sm text-on-surface">
                        {form.tracking}
                    </div>
                    <div className="flex items-center gap-1.5 pt-1 font-body-md text-body-md text-on-surface-variant">
                        <MaterialIcon name="person" className="text-[18px]" />
                        <span>
                            Tujuan Penerima: <strong className="font-label-lg text-on-surface">{form.recipient}</strong>
                        </span>
                    </div>
                </section>

                <section className="flex flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-margin">
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="recipient-name"
                            className="flex items-center justify-between font-label-md text-label-md text-on-surface"
                        >
                            <span>Nama Penerima Fisik</span>
                            <span className="font-body-sm text-body-sm text-primary">*Wajib</span>
                        </label>
                        <input
                            id="recipient-name"
                            type="text"
                            value={recipientName}
                            onChange={(event) => setRecipientName(event.target.value)}
                            placeholder="Masukkan nama orang yang menerima paket"
                            className="h-12 w-full rounded-xl bg-surface-container-low px-3 font-body-lg text-body-lg text-on-surface placeholder:text-secondary focus:bg-surface-container focus:outline-none"
                        />
                        <p className="font-body-sm text-body-sm text-secondary">
                            Pastikan nama sesuai dengan identitas penerima paket di lokasi.
                        </p>
                    </div>

                    <div className="flex flex-col gap-2">
                        <span className="font-label-md text-label-md text-on-surface">Hubungan dengan Penerima</span>
                        <div className="grid grid-cols-2 gap-2">
                            {form.relations.map((option) => {
                                const active = relation === option;
                                return (
                                    <button
                                        key={option}
                                        type="button"
                                        onClick={() => setRelation(option)}
                                        className={cx(
                                            "flex h-12 items-center justify-center rounded-xl px-3 font-label-md text-label-md transition-colors",
                                            active
                                                ? "bg-primary text-on-primary"
                                                : "bg-surface-container-low text-on-surface"
                                        )}
                                    >
                                        {option}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="flex flex-col gap-2">
                        <div className="flex items-center justify-between">
                            <span className="font-label-md text-label-md text-on-surface">Foto Bukti Penyerahan</span>
                            <span className="flex items-center gap-1 font-label-sm font-medium text-label-sm text-tertiary">
                                <MaterialIcon name="check_circle" className="text-[16px]" />
                                Terverifikasi
                            </span>
                        </div>
                        <div className="flex items-center justify-between rounded-xl bg-surface-container-low p-3">
                            <div className="flex min-w-0 items-center gap-3">
                                <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-container-high">
                                    <img
                                        src={photo?.url}
                                        alt="Bukti serah terima paket beku"
                                        className="h-full w-full object-cover"
                                    />
                                </div>
                                <div className="flex min-w-0 flex-col">
                                    <span className="truncate font-label-md text-label-md text-on-surface">
                                        {photo?.name}
                                    </span>
                                    <span className="font-body-sm text-body-sm text-secondary">{photo?.meta}</span>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={openCamera}
                                className="flex h-10 shrink-0 items-center gap-1 rounded-lg bg-surface-container-highest px-3 font-label-md text-label-md text-on-surface transition-colors hover:bg-secondary-container"
                            >
                                <MaterialIcon name="photo_camera" className="text-[18px]" />
                                Ambil Ulang
                            </button>
                        </div>
                        <PhotoInput inputRef={inputRef} onFile={onFile} />
                        <p className="font-body-sm text-body-sm text-secondary">
                            Ambil foto paket bersama penerima atau di depan pintu rumah/lokasi penyerahan.
                        </p>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="delivery-notes"
                            className="flex items-center justify-between font-label-md text-label-md text-on-surface"
                        >
                            <span>Catatan Tambahan</span>
                            <span className="font-body-sm text-body-sm text-secondary">Opsional</span>
                        </label>
                        <input
                            id="delivery-notes"
                            type="text"
                            value={notes}
                            onChange={(event) => setNotes(event.target.value)}
                            placeholder={form.notesPlaceholder}
                            className="h-12 w-full rounded-xl bg-surface-container-low px-3 font-body-lg text-body-lg text-on-surface placeholder:text-secondary focus:bg-surface-container focus:outline-none"
                        />
                    </div>
                </section>

                <div className="flex flex-col gap-2 pt-2">
                    <button
                        type="button"
                        onClick={handleSubmit}
                        disabled={submitState !== "idle"}
                        className={cx(
                            "flex h-[52px] w-full select-none items-center justify-center gap-2 rounded-xl font-label-lg text-label-lg transition-colors",
                            submitState === "done"
                                ? "bg-tertiary text-on-tertiary"
                                : "bg-primary text-on-primary active:bg-primary-container"
                        )}
                    >
                        {submitState === "done" ? (
                            <>
                                <MaterialIcon name="check" className="text-[20px]" />
                                Pengiriman Berhasil Selesai
                            </>
                        ) : submitState === "saving" ? (
                            <>
                                <MaterialIcon name="progress_activity" className="animate-spin text-[20px]" />
                                Menyimpan Bukti...
                            </>
                        ) : (
                            <>
                                <MaterialIcon name="task_alt" className="text-[20px]" />
                                Selesaikan Pengiriman
                            </>
                        )}
                    </button>
                    <div className="text-center font-body-sm text-body-sm text-secondary">
                        Status paket akan langsung diperbarui ke sistem pusat Satria.
                    </div>
                </div>
            </div>
        </CourierLayout>
    );
}
