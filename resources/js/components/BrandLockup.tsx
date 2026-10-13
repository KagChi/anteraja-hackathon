import { AnterajaMark } from "@/components/AnterajaMark";
import { cx } from "@/lib/cx";

interface BrandLockupProps {
    /** Small uppercase qualifier beside the wordmark, e.g. "Kurir". */
    tag?: string;
    /** Render the "anteraja" wordmark next to the mark. */
    wordmark?: boolean;
    markClassName?: string;
    className?: string;
}

/**
 * The Anteraja mark, optionally with its wordmark. Title screens keep the mark
 * on its own so long screen headings never collide with the brand text.
 */
export function BrandLockup({ tag, wordmark = true, markClassName = "h-8 w-8", className }: BrandLockupProps) {
    return (
        <span className={cx("flex shrink-0 items-center gap-2", className)}>
            <AnterajaMark className={markClassName} />
            {wordmark ? (
                <span className="flex shrink-0 items-baseline gap-1">
                    <span className="font-headline-sm text-headline-sm font-bold leading-none tracking-tight text-primary">
                        anteraja
                    </span>
                    {tag ? <span className="font-label-sm text-label-sm uppercase text-secondary">{tag}</span> : null}
                </span>
            ) : null}
        </span>
    );
}
