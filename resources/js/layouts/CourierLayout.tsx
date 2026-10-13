import { Head, Link } from "@inertiajs/react";
import type { ReactNode } from "react";
import { AnterajaLogo } from "@/components/AnterajaLogo";
import { BottomNav } from "@/components/BottomNav";
import { MaterialIcon } from "@/components/MaterialIcon";
import { MobileShell } from "@/components/MobileShell";
import { COURIER } from "@/data/courier";
import type { NavItem } from "@/lib/nav";

interface CourierLayoutProps {
    children: ReactNode;
    /** Screen heading shown beside the brand lockup. */
    title?: string;
    /** When set, render a back affordance that links to this route. */
    backTo?: string;
    /** Show the signed-in courier identity instead of a screen heading. */
    showCourier?: boolean;
}

const NAV_ITEMS: NavItem[] = [
    { label: "Tugas", icon: "local_shipping", href: "/courier/tasks" },
    { label: "Riwayat", icon: "receipt_long", href: "/courier/history" },
    { label: "Profil", icon: "badge", href: "/courier/profile" }
];

/** Field-app shell for the Anteraja Kurir screens. */
export function CourierLayout({ children, title, backTo, showCourier = false }: CourierLayoutProps) {
    return (
        <MobileShell
            header={
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
                            {showCourier ? (
                                <>
                                    <AnterajaLogo className="h-6" />
                                    <div className="ml-1 flex flex-col leading-tight">
                                        <span className="font-label-md text-label-md text-on-surface">
                                            {COURIER.name}
                                        </span>
                                        <span className="flex items-center gap-1 font-label-sm text-label-sm text-tertiary">
                                            <span className="inline-block h-1.5 w-1.5 rounded-full bg-tertiary" />
                                            {COURIER.status}
                                        </span>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <AnterajaLogo className="h-5" />
                                    <h1 className="ml-1 min-w-0 truncate font-headline-sm text-headline-sm text-on-surface">
                                        {title}
                                    </h1>
                                </>
                            )}
                        </div>
                        <Link
                            href="/courier/profile"
                            aria-label="Profil kurir"
                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary"
                        >
                            <MaterialIcon name="person" className="text-[18px] text-on-primary" />
                        </Link>
                    </div>
                </header>
            }
            nav={
                <BottomNav
                    items={NAV_ITEMS}
                    ariaLabel="Navigasi kurir"
                    className="sticky bottom-0 z-50 mt-auto bg-surface/95 pb-safe shadow-[0_-1px_8px_rgba(0,0,0,0.04)] backdrop-blur-xl"
                    containerClassName="h-16 px-space-xs"
                />
            }
            bodyClassName="px-margin pb-24 pt-space-md"
        >
            <Head title={title ?? "Kurir"} />
            {children}
        </MobileShell>
    );
}
