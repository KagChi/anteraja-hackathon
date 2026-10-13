import type { CSSProperties } from "react";
import { cx } from "@/lib/cx";

interface MaterialIconProps {
    name: string;
    className?: string;
    /** Render the filled variant of the glyph (used for active/selected states). */
    filled?: boolean;
}

export function MaterialIcon({ name, className, filled = false }: MaterialIconProps) {
    const style: CSSProperties | undefined = filled ? { fontVariationSettings: "'FILL' 1" } : undefined;

    return (
        <span aria-hidden="true" className={cx("material-symbols-outlined", className)} style={style}>
            {name}
        </span>
    );
}
