import { useRef } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { type PickedPhoto, preparePhoto } from "../lib/photo";

interface PhotoFieldProps {
    label: string;
    photo: PickedPhoto | null;
    onPick: (photo: PickedPhoto | null) => void;
    /** Label for the button that opens the camera. */
    actionLabel: string;
    supporting?: string;
}

/**
 * Camera capture on the web.
 *
 * `capture="environment"` opens the rear camera on a phone and the file picker
 * everywhere else, which is what makes the proof photo work in an installed app
 * and in a desktop browser alike.
 */
export function PhotoField({ label, photo, onPick, actionLabel, supporting }: PhotoFieldProps) {
    const inputRef = useRef<HTMLInputElement>(null);

    return (
        <div className="flex w-full flex-col gap-2">
            <span className="flex w-full items-center justify-between">
                <span className="font-body-md text-body-md text-on-surface">{label}</span>
                {photo === null ? null : <span className="font-body-sm text-body-sm text-tertiary">1 Terlampir</span>}
            </span>

            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = "";

                    if (file === undefined) {
                        return;
                    }

                    void preparePhoto(file).then((prepared) => onPick(prepared));
                }}
            />

            {photo === null ? (
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
                >
                    <MaterialIcon name="photo_camera" className="text-[20px]" />
                    {actionLabel}
                </button>
            ) : (
                <div className="flex w-full items-center justify-between gap-space-md rounded-xl bg-surface-container-low p-space-md">
                    <span className="flex flex-1 items-center gap-space-md">
                        <img src={photo.previewUrl} alt="" className="h-14 w-14 rounded-lg object-cover" />
                        <span className="flex flex-1 flex-col gap-0.5">
                            <span className="font-body-md text-body-md text-on-surface">{photo.name}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{photo.sizeLabel}</span>
                        </span>
                    </span>
                    <button
                        type="button"
                        onClick={() => {
                            onPick(null);
                            inputRef.current?.click();
                        }}
                        className="rounded-lg bg-surface-container px-space-md py-2 font-label-sm text-label-sm text-on-surface"
                    >
                        Ambil Ulang
                    </button>
                </div>
            )}

            {supporting === undefined ? null : (
                <span className="font-body-sm text-body-sm text-secondary">{supporting}</span>
            )}
        </div>
    );
}
