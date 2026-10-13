import { cx } from "@/lib/cx";

interface AnterajaMarkProps {
    className?: string;
}

/**
 * The Anteraja courier brand mark: a magenta rounded tile holding the white
 * "A" glyph, matching the logo lockup used across the field app screens.
 */
export function AnterajaMark({ className }: AnterajaMarkProps) {
    return (
        <svg viewBox="0 0 32 32" role="img" aria-label="Anteraja Kurir" className={cx("shrink-0", className)}>
            <rect width="32" height="32" rx="9" fill="#c0005f" />
            <path
                d="M16.05 7.5 24 25h-4.3l-1.45-3.3h-4.6L12.2 25H8l8.05-17.5Zm-.02 5.9L14.5 17.2h3.05l-1.52-3.8Z"
                fill="#ffffff"
            />
        </svg>
    );
}
