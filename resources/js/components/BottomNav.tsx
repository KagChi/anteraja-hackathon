import { Link, usePage } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { cx } from "@/lib/cx";
import { isActive, type NavItem } from "@/lib/nav";

interface BottomNavProps {
    items: NavItem[];
    ariaLabel: string;
    /** Classes for the nav element itself (background, border, safe padding). */
    className?: string;
    /** Classes for the inner row that lays the tabs out. */
    containerClassName?: string;
    itemClassName?: string;
    activeClassName?: string;
    inactiveClassName?: string;
    labelClassName?: string;
    /** Force a specific tab active (for flow screens that live under another tab). */
    activeHref?: string;
}

/**
 * Shared bottom tab bar for the mobile apps. The visual chrome is passed in by
 * each layout so every app keeps its own palette while the behaviour (active
 * detection, filled icon, aria state, badge dot) stays identical.
 */
export function BottomNav({
    items,
    ariaLabel,
    className,
    containerClassName,
    itemClassName,
    activeClassName = "font-bold text-primary",
    inactiveClassName = "text-secondary transition-colors hover:text-on-surface",
    labelClassName = "font-label-sm text-label-sm",
    activeHref
}: BottomNavProps) {
    const { url } = usePage();
    const pathname = url.split("?")[0];

    return (
        <nav aria-label={ariaLabel} className={className}>
            <div className={cx("flex items-center justify-around", containerClassName)}>
                {items.map((item) => {
                    const active = activeHref ? activeHref === item.href : isActive(pathname, item.href);

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={active ? "page" : undefined}
                            className={cx(
                                "flex min-h-[48px] min-w-[56px] flex-col items-center justify-center gap-0.5",
                                itemClassName,
                                active ? activeClassName : inactiveClassName
                            )}
                        >
                            <span className="relative flex items-center justify-center">
                                <MaterialIcon name={item.icon} filled={active} />
                                {item.badge ? (
                                    <span className="absolute -right-1 -top-0.5 h-2 w-2 rounded-full bg-primary" />
                                ) : null}
                            </span>
                            <span className={labelClassName}>{item.label}</span>
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
