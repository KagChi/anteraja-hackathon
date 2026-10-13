import { type ReactNode, useEffect, useId, useRef, useState } from "react";
import { cx } from "@/lib/cx";

interface DialogLayer {
    id: string;
    close: () => void;
}

/**
 * Open dialogs in mount order. Escape closes only the last entry, so a dialog
 * stacked on another (a courier picker over a detail modal, say) never takes the
 * one underneath down with it.
 */
const LAYER_STACK: DialogLayer[] = [];

/** z-index rungs per nesting level; Tailwind only sees classes written in full. */
const LAYER_Z = ["z-50", "z-[60]", "z-[70]", "z-[80]"];

/**
 * Panel widths. Each size emits exactly one `max-w-*` utility: two of them on one
 * element would be decided by stylesheet order rather than by the class list.
 */
const WIDTHS = {
    md: "max-w-md",
    lg: "max-w-lg",
    xl: "max-w-xl",
    "2xl": "max-w-2xl",
    "3xl": "max-w-3xl",
    "4xl": "max-w-4xl"
} as const;

interface DialogProps {
    /** Called for a backdrop click, the close affordance or Escape. */
    onClose: () => void;
    /** Accessible name for the panel. */
    label: string;
    children: ReactNode;
    /** Panel width; defaults to the medium form dialog. */
    width?: keyof typeof WIDTHS;
    /** Panel classes for anything else, such as a max height. */
    className?: string;
}

/**
 * Centered dialog shell: dimmed backdrop, dismissal on backdrop click or Escape,
 * and focus moved into the panel so keyboard users land inside the dialog.
 */
export function Dialog({ onClose, label, children, width = "md", className }: DialogProps) {
    const id = useId();
    const panelRef = useRef<HTMLDivElement>(null);
    const closeRef = useRef(onClose);
    const [depth, setDepth] = useState(0);

    useEffect(() => {
        closeRef.current = onClose;
    });

    useEffect(() => {
        const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const layer: DialogLayer = { id, close: () => closeRef.current() };

        LAYER_STACK.push(layer);
        setDepth(LAYER_STACK.length - 1);
        panelRef.current?.focus();

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key !== "Escape") return;
            if (LAYER_STACK[LAYER_STACK.length - 1]?.id !== id) return;
            event.stopPropagation();
            closeRef.current();
        }

        window.addEventListener("keydown", handleKeyDown);
        return () => {
            window.removeEventListener("keydown", handleKeyDown);
            const index = LAYER_STACK.findIndex((entry) => entry.id === id);
            if (index !== -1) LAYER_STACK.splice(index, 1);
            if (previouslyFocused?.isConnected) previouslyFocused.focus();
        };
    }, [id]);

    return (
        <div
            className={cx(
                "fixed inset-0 flex items-center justify-center p-margin",
                LAYER_Z[Math.min(depth, LAYER_Z.length - 1)]
            )}
        >
            <button
                type="button"
                aria-label="Tutup dialog"
                tabIndex={-1}
                onClick={() => closeRef.current()}
                className="absolute inset-0 cursor-default bg-inverse-surface/40"
            />
            <div
                ref={panelRef}
                role="dialog"
                aria-modal="true"
                aria-label={label}
                tabIndex={-1}
                className={cx(
                    "relative flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg shadow-xl focus:outline-none",
                    WIDTHS[width],
                    className
                )}
            >
                {children}
            </div>
        </div>
    );
}
