import type { ReactNode } from "react";
import { cx } from "@/lib/cx";

interface MobileShellProps {
    children: ReactNode;
    /** Sticky header content. */
    header: ReactNode;
    /** Bottom tab bar, when the screen has one. */
    nav?: ReactNode;
    /** Page-level classes; the app palette scope is applied here. */
    className?: string;
    /** Padding/cluster classes for the scroll body. */
    bodyClassName?: string;
    /** Canvas classes; override for dark screens. */
    canvasClassName?: string;
}

/**
 * Structural shell shared by every mobile screen: a full-bleed column with a
 * sticky header, a flexible scroll body and an optional bottom tab bar. All
 * colour and typography come from the palette scope applied via className.
 */
export function MobileShell({ children, header, nav, className, bodyClassName, canvasClassName }: MobileShellProps) {
    return (
        <div
            className={cx(
                "flex min-h-screen w-full flex-col font-sans antialiased",
                canvasClassName ?? "bg-surface text-on-surface",
                className
            )}
        >
            {header}
            <main className={cx("flex w-full flex-1 flex-col", bodyClassName)}>{children}</main>
            {nav}
        </div>
    );
}
