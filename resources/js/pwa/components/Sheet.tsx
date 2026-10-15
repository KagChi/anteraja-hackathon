import { type ReactNode, useEffect } from "react";

interface SheetProps {
    onClose: () => void;
    label: string;
    children: ReactNode;
}

/** The bottom sheet the courier's issue picker slides up in. */
export function Sheet({ onClose, label, children }: SheetProps) {
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") {
                onClose();
            }
        };

        window.addEventListener("keydown", onKeyDown);

        return () => window.removeEventListener("keydown", onKeyDown);
    }, [onClose]);

    return (
        <div
            className="fixed inset-0 z-50 flex flex-col justify-end"
            role="dialog"
            aria-modal="true"
            aria-label={label}
        >
            <button type="button" aria-label="Tutup" onClick={onClose} className="absolute inset-0 bg-on-surface/40" />
            <div className="relative w-full rounded-t-2xl bg-surface-container-lowest pb-safe shadow-[0_-8px_30px_rgba(0,0,0,0.12)]">
                {children}
            </div>
        </div>
    );
}
