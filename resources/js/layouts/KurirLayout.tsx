import { Link, usePage } from "@inertiajs/react";
import type { ReactNode } from "react";
import { AnterajaMark } from "@/components/AnterajaMark";
import { MaterialIcon } from "@/components/MaterialIcon";
import { COURIER } from "@/data/kurir";
import { cx } from "@/lib/cx";

interface KurirLayoutProps {
    children: ReactNode;
    /** Screen title shown alongside the brand lockup in the header. */
    title?: string;
    /** When provided, render a back affordance that links to this route. */
    backTo?: string;
    /** Show the signed-in courier identity instead of a screen title. */
    showCourier?: boolean;
}

const NAV_ITEMS = [
    { label: "Tugas", icon: "local_shipping", href: "/kurir/tasks", available: true },
    { label: "Riwayat", icon: "receipt_long", href: "/kurir/tasks", available: false },
    { label: "Profil", icon: "badge", href: "/kurir/tasks", available: false }
];

/**
 * Field-app shell for the Anteraja Kurir mobile slice: a sticky brand header
 * over a centred 390px column, with the fixed bottom tab bar from the source
 * design. The column keeps the phone proportions legible on desktop previews.
 */
export function KurirLayout({ children, title, backTo, showCourier = false }: KurirLayoutProps) {
    const { url } = usePage();
    const pathname = url.split("?")[0];

    return (
        <div className="flex min-h-screen w-full justify-center bg-surface-dim font-sans text-on-surface antialiased">
            <div className="relative flex min-h-screen w-full max-w-[430px] flex-col bg-surface">
                <header className="sticky top-0 z-50 bg-surface/90 pt-safe shadow-[0_1px_8px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                    <div className="flex h-16 items-center justify-between px-margin">
                        <div className="flex min-w-0 items-center gap-space-sm">
                            {backTo ? (
                                <Link
                                    href={backTo}
                                    aria-label="Kembali"
                                    className="flex h-11 w-11 shrink-0 items-center justify-center text-on-surface transition-colors hover:text-primary"
                                >
                                    <MaterialIcon name="arrow_back" />
                                </Link>
                            ) : null}
                            <AnterajaMark className="h-8 w-8" />
                            <span className="flex shrink-0 items-baseline gap-1">
                                <span className="font-headline-sm text-headline-sm font-bold leading-none tracking-tight text-primary">
                                    anteraja
                                </span>
                                <span className="font-label-sm text-label-sm uppercase text-secondary">Kurir</span>
                            </span>
                            {title ? (
                                <h1 className="ml-1 truncate font-headline-sm text-headline-sm text-on-surface">
                                    {title}
                                </h1>
                            ) : null}
                            {showCourier ? (
                                <div className="ml-1 flex flex-col leading-tight">
                                    <span className="font-label-md text-label-md text-on-surface">{COURIER.name}</span>
                                    <span className="flex items-center gap-1 font-label-sm text-label-sm text-tertiary">
                                        <span className="inline-block h-1.5 w-1.5 rounded-full bg-tertiary" />
                                        {COURIER.status}
                                    </span>
                                </div>
                            ) : null}
                        </div>
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary">
                            <MaterialIcon name="person" className="text-[18px] text-on-primary" />
                        </div>
                    </div>
                </header>

                <main className="flex w-full flex-1 flex-col px-margin pb-24 pt-space-md">{children}</main>

                <nav
                    aria-label="Navigasi kurir"
                    className="sticky bottom-0 z-50 mt-auto bg-surface/95 pb-safe shadow-[0_-1px_8px_rgba(0,0,0,0.04)] backdrop-blur-xl"
                >
                    <div className="flex h-16 items-center justify-around px-space-xs">
                        {NAV_ITEMS.map((item) => {
                            const active =
                                item.available && (pathname === item.href || pathname.startsWith(`${item.href}/`));
                            const className = cx(
                                "flex min-h-[48px] min-w-[64px] flex-col items-center justify-center",
                                active
                                    ? "font-bold text-primary"
                                    : item.available
                                      ? "text-secondary transition-colors hover:text-on-surface"
                                      : "text-secondary/50"
                            );
                            const content = (
                                <>
                                    <MaterialIcon name={item.icon} />
                                    <span className="mt-0.5 font-label-sm text-label-sm">{item.label}</span>
                                </>
                            );

                            return item.available ? (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    aria-current={active ? "page" : undefined}
                                    className={className}
                                >
                                    {content}
                                </Link>
                            ) : (
                                <button key={item.label} type="button" aria-disabled="true" className={className}>
                                    {content}
                                </button>
                            );
                        })}
                    </div>
                </nav>
            </div>
        </div>
    );
}
