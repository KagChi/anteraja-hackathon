import { Link, usePage } from "@inertiajs/react";
import { type ReactNode, useEffect, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { cx } from "@/lib/cx";

interface AdminLayoutProps {
    children: ReactNode;
}

interface NavItem {
    label: string;
    icon: string;
    href: string;
}

const NAV_ITEMS: NavItem[] = [
    { label: "Shipment", icon: "local_shipping", href: "/admin/shipment" },
    { label: "Kendala", icon: "warning", href: "/admin/exception" },
    { label: "Laporan", icon: "bar_chart", href: "/admin/report" },
    { label: "Riwayat", icon: "history", href: "/admin/history" }
];

export function AdminLayout({ children }: AdminLayoutProps) {
    const { url } = usePage();
    const pathname = url.split("?")[0];
    const [open, setOpen] = useState(false);

    // biome-ignore lint/correctness/useExhaustiveDependencies: close the drawer whenever the route changes
    useEffect(() => {
        setOpen(false);
    }, [pathname]);

    return (
        <div className="min-h-screen bg-surface font-sans text-on-surface antialiased">
            {open ? (
                <button
                    type="button"
                    aria-label="Tutup navigasi"
                    className="fixed inset-0 z-40 bg-inverse-surface/20 lg:hidden"
                    onClick={() => setOpen(false)}
                />
            ) : null}

            <aside
                aria-label="Navigasi utama"
                className={cx(
                    "fixed inset-y-0 left-0 z-50 flex w-[240px] flex-col justify-between border-r border-outline-variant bg-surface-container-lowest transition-transform duration-200 lg:translate-x-0",
                    open ? "translate-x-0" : "-translate-x-full"
                )}
            >
                <div className="flex flex-col">
                    <div className="flex h-14 items-center justify-between border-b border-outline-variant px-margin">
                        <div className="flex items-center gap-space-xs">
                            <span className="font-headline-sm text-headline-sm uppercase tracking-tight text-primary">
                                Anteraja
                            </span>
                            <span className="rounded-sm bg-surface-container-high px-space-xs py-0.5 font-label-sm text-label-sm uppercase tracking-wide text-on-surface-variant">
                                Frozen
                            </span>
                        </div>
                        <MaterialIcon name="ac_unit" className="text-[18px] text-secondary" />
                    </div>

                    <div className="px-margin py-space-sm">
                        <span className="font-label-sm text-label-sm uppercase text-secondary">Navigasi Utama</span>
                    </div>

                    <nav className="flex flex-col gap-space-xs px-space-xs">
                        {NAV_ITEMS.map((item) => {
                            const active = pathname === item.href || pathname.startsWith(item.href + "/");
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    aria-current={active ? "page" : undefined}
                                    className={cx(
                                        "flex items-center gap-space-md px-space-md py-space-sm font-label-md text-label-md transition-colors",
                                        active
                                            ? "border-l-2 border-primary bg-surface-container-low text-primary"
                                            : "text-secondary hover:bg-surface-container hover:text-on-surface"
                                    )}
                                >
                                    <MaterialIcon name={item.icon} className="text-[20px]" />
                                    <span>{item.label}</span>
                                </Link>
                            );
                        })}
                    </nav>
                </div>

                <div className="border-t border-outline-variant bg-surface-container-lowest p-margin">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-space-xs">
                            <span className="h-2 w-2 rounded-full bg-tertiary-container" />
                            <span className="font-label-sm text-label-sm text-secondary">Hub Cold-Chain Aktif</span>
                        </div>
                        <span className="font-data-mono text-data-mono text-secondary">-20°C</span>
                    </div>
                </div>
            </aside>

            <div className="lg:pl-[240px]">
                <header className="fixed left-0 right-0 top-0 z-40 flex h-14 items-center justify-between border-b border-outline-variant bg-surface-container-lowest px-margin lg:left-[240px]">
                    <div className="flex items-center gap-space-sm">
                        <button
                            type="button"
                            aria-label="Buka navigasi"
                            className="flex h-8 w-8 items-center justify-center rounded-md border border-outline-variant text-secondary transition-colors hover:bg-surface-container hover:text-on-surface lg:hidden"
                            onClick={() => setOpen(true)}
                        >
                            <MaterialIcon name="menu" className="text-[18px]" />
                        </button>
                        <div className="relative flex w-56 items-center md:w-80">
                            <MaterialIcon name="search" className="absolute left-space-sm text-[18px] text-secondary" />
                            <input
                                type="text"
                                placeholder="Cari nomor resi / AWB / driver..."
                                className="h-9 w-full rounded-md border border-outline-variant bg-surface-container-lowest pl-8 pr-space-md font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:border-primary focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-margin">
                        <div className="hidden items-center gap-space-sm sm:flex">
                            <button
                                type="button"
                                aria-label="Notifikasi"
                                className="flex h-8 w-8 items-center justify-center rounded-md border border-outline-variant text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                            >
                                <MaterialIcon name="notifications" className="text-[18px]" />
                            </button>
                            <button
                                type="button"
                                aria-label="Pemantauan suhu"
                                className="flex h-8 w-8 items-center justify-center rounded-md border border-outline-variant text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                            >
                                <MaterialIcon name="thermostat" className="text-[18px]" />
                            </button>
                        </div>
                        <div className="hidden h-6 w-px bg-outline-variant sm:block" />
                        <div className="flex items-center gap-space-sm">
                            <div className="hidden text-right sm:block">
                                <div className="font-label-md text-label-md leading-none text-on-surface">
                                    Dimas Pratama
                                </div>
                                <div className="mt-0.5 font-label-sm text-label-sm text-secondary">Admin Logistics</div>
                            </div>
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary">
                                <MaterialIcon name="person" className="text-[18px] text-on-primary" />
                            </div>
                        </div>
                    </div>
                </header>

                <main className="min-h-screen bg-surface pt-14">
                    <div className="flex w-full flex-col px-margin py-margin">{children}</div>
                </main>
            </div>
        </div>
    );
}
