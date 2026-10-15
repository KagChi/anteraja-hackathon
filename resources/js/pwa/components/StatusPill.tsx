import { cx } from "@/lib/cx";
import { statusKey, statusPillClasses } from "../lib/status";

interface StatusPillProps {
    /** The API status code; the tone comes from the shared mapping. */
    code: string;
    label: string;
    className?: string;
}

export function StatusPill({ code, label, className }: StatusPillProps) {
    return (
        <span
            className={cx(
                "inline-flex items-center rounded-full px-2 py-0.5 font-label-sm text-label-sm",
                statusPillClasses(statusKey(code)),
                className
            )}
        >
            {label}
        </span>
    );
}
