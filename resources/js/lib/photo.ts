import { useCallback, useRef, useState } from "react";

export const PHOTO_PLACEHOLDER = "/images/proof-placeholder.svg";

export interface PickedPhoto {
    url: string;
    name: string;
    meta: string;
    /** True once the user replaced the placeholder with a real file. */
    picked: boolean;
}

export function formatFileSize(bytes: number): string {
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * Pairs a hidden file input with a photo preview. The retake button opens the
 * device camera or file picker and the chosen file replaces the placeholder.
 * Pass null to start with no photo attached (the screenshot-issue flow).
 */
export function usePhoto(initial: { name: string; meta: string } | null) {
    const initialRef = useRef(initial);
    const [photo, setPhoto] = useState<PickedPhoto | null>(() =>
        initial ? { url: PHOTO_PLACEHOLDER, name: initial.name, meta: initial.meta, picked: false } : null
    );
    const inputRef = useRef<HTMLInputElement>(null);

    const open = useCallback(() => inputRef.current?.click(), []);

    const onFile = useCallback((file?: File) => {
        if (!file) return;
        setPhoto((prev) => {
            if (prev?.picked) URL.revokeObjectURL(prev.url);
            return {
                url: URL.createObjectURL(file),
                name: file.name,
                meta: `${formatFileSize(file.size)} • Baru dipilih`,
                picked: true
            };
        });
    }, []);

    const reset = useCallback(() => {
        setPhoto((prev) => {
            if (prev?.picked) URL.revokeObjectURL(prev.url);
            const start = initialRef.current;
            return start ? { url: PHOTO_PLACEHOLDER, name: start.name, meta: start.meta, picked: false } : null;
        });
    }, []);

    return { photo, open, onFile, reset, inputRef };
}
