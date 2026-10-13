import { AnterajaMark } from "@/components/AnterajaMark";
import { cx } from "@/lib/cx";

interface BrandLockupProps {
    /** Small uppercase qualifier beside the wordmark, e.g. "Kurir". */
    tag?: string;
    className?: string;
}

/** The Anteraja mark plus wordmark used across the courier-facing headers. */
export function BrandLockup({ tag, className }: BrandLockupProps) {
    return (
        <span className={cx("flex min-w-0 items-center gap-2", className)}>
            <AnterajaMark className="h-8 w-8" />
            <span className="flex shrink-0 items-baseline gap-1">
                <span className="font-headline-sm text-headline-sm font-bold leading-none tracking-tight text-primary">
                    anteraja
                </span>
                {tag ? <span className="font-label-sm text-label-sm uppercase text-secondary">{tag}</span> : null}
            </span>
        </span>
    );
}
