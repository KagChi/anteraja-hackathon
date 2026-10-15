import { MaterialIcon } from "@/components/MaterialIcon";
import { cx } from "@/lib/cx";
import { Link, useRouter } from "../lib/router";
import type { Realm } from "../lib/token";

interface TabItem {
    label: string;
    icon: string;
    href: string;
}

const TABS: Record<Realm, TabItem[]> = {
    client: [
        { label: "Beranda", icon: "home", href: "/client/landing" },
        { label: "Pesanan", icon: "receipt_long", href: "/client/orders" },
        { label: "Riwayat", icon: "history", href: "/client/history" },
        { label: "Profil", icon: "account_circle", href: "/client/profile" }
    ],
    courier: [
        { label: "Tugas", icon: "local_shipping", href: "/courier/tasks" },
        { label: "Riwayat", icon: "receipt_long", href: "/courier/history" },
        { label: "Profil", icon: "badge", href: "/courier/profile" }
    ]
};

/** The bottom tab bar the tab screens share. */
export function TabBar({ realm }: { realm: Realm }) {
    const { path } = useRouter();

    return (
        <nav
            aria-label={realm === "courier" ? "Navigasi kurir" : "Navigasi pelanggan"}
            className="sticky bottom-0 z-40 mt-auto w-full bg-surface/95 pb-safe shadow-[0_-1px_8px_rgba(0,0,0,0.04)] backdrop-blur-xl"
        >
            <div className="flex h-16 w-full items-stretch">
                {TABS[realm].map((tab) => {
                    const active = path === tab.href || path.startsWith(`${tab.href}/`);

                    return (
                        <Link
                            key={tab.href}
                            href={tab.href}
                            className={cx(
                                "flex flex-1 flex-col items-center justify-center gap-0.5",
                                active ? "font-bold text-primary" : "text-secondary"
                            )}
                        >
                            <MaterialIcon name={tab.icon} filled={active} className="text-[22px]" />
                            <span className="font-label-sm text-label-sm">{tab.label}</span>
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
