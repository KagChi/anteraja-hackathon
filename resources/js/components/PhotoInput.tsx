import type { RefObject } from "react";

interface PhotoInputProps {
    inputRef: RefObject<HTMLInputElement | null>;
    onFile: (file?: File) => void;
}

/** Hidden camera/gallery input driven by a page-level retake button. */
export function PhotoInput({ inputRef, onFile }: PhotoInputProps) {
    return (
        <input
            ref={inputRef}
            type="file"
            accept="image/*"
            capture="environment"
            className="hidden"
            onChange={(event) => {
                onFile(event.target.files?.[0]);
                event.target.value = "";
            }}
        />
    );
}
