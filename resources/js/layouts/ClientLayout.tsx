import { Head, Link, usePage } from "@inertiajs/react";
import type { ReactNode } from "react";
import { AnterajaLogo } from "@/components/AnterajaLogo";
import { BottomNav } from "@/components/BottomNav";
import { MaterialIcon } from "@/components/MaterialIcon";
import { MobileShell } from "@/components/MobileShell";
import { whatsappLink } from "@/data/client";
import type { NavItem } from "@/lib/nav";

interface ClientLayoutProps {
    children: ReactNode;
    title: string;
    /**
     * Destination for the header back affordance. Tab roots omit it, so they
     * show no back arrow.
     */
    backTo?: string;
}

const NAV_ITEMS: NavItem[] = [
    { label: "Pesanan", icon: "receipt_long", href: "/client/orders" },
    { label: "Riwayat", icon: "history", href: "/client/history" },
    { label: "Profil", icon: "account_circle", href: "/client/profile" }
];

/** Screens reached from an order keep the Pesanan tab lit. */
const ORDER_SCOPED = ["/client/orders", "/client/tracking", "/client/schedule", "/client/issue"];

/** Customer-facing shell for the Anteraja Frozen delivery screens. */
export function ClientLayout({ children, title, backTo }: ClientLayoutProps) {
    const { url } = usePage();
    const pathname = url.split("?")[0];
    const activeHref = ORDER_SCOPED.some((path) => pathname === path || pathname.startsWith(`${path}/`))
        ? "/client/orders"
        : undefined;

    return (
        <MobileShell
            className="client-app"
            header={
                <header className="sticky top-0 z-50 bg-surface/90 pt-safe backdrop-blur-xl">
                    <div className="flex h-14 items-center justify-between px-margin">
                        <div className="flex min-w-0 items-center gap-space-sm">
                            {backTo ? (
                                <Link
                                    href={backTo}
                                    aria-label="Kembali"
                                    className="flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center text-on-surface transition-colors hover:text-primary"
                                >
                                    <MaterialIcon name="arrow_back_ios_new" className="text-[20px]" />
                                </Link>
                            ) : null}
                            <AnterajaLogo className="h-6" />
                            <h1 className="min-w-0 truncate font-headline-sm text-headline-sm text-on-surface">
                                {title}
                            </h1>
                        </div>
                        <div className="flex shrink-0 items-center gap-space-sm">
                            <a
                                href={whatsappLink("Halo, saya butuh bantuan dengan pengiriman Anteraja Frozen.")}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Bantuan via WhatsApp"
                                className="flex min-h-[44px] min-w-[44px] items-center justify-center text-secondary transition-colors hover:text-on-surface"
                            >
                                <MaterialIcon name="help_outline" className="text-[22px]" />
                            </a>
                            <Link
                                href="/client/profile"
                                aria-label="Profil saya"
                                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary"
                            >
                                <MaterialIcon name="person" className="text-[18px] text-on-primary" />
                            </Link>
                        </div>
                    </div>
                </header>
            }
            nav={
                <BottomNav
                    items={NAV_ITEMS}
                    ariaLabel="Navigasi pelanggan"
                    activeHref={activeHref}
                    className="sticky bottom-0 z-50 mt-auto border-t border-black/5 bg-surface/95 pb-safe backdrop-blur-xl"
                    containerClassName="h-16 px-2"
                    labelClassName="text-[10px] font-medium"
                />
            }
            bodyClassName="px-margin pt-space-md pb-24"
        >
            <Head title={title} />
            {children}
        </MobileShell>
    );
}
