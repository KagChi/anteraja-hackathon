import { type ReactNode, useEffect, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { cx } from "@/lib/cx";
import { Link, useRouter } from "../lib/router";
import { HELP_MESSAGE, whatsappLink } from "../lib/support";
import type { Realm } from "../lib/token";
import { useCart } from "../lib/useCart";
import { TabBar } from "./TabBar";

export type Chrome = "tabs" | "back" | "title" | "plain";

interface ShellProps {
    realm: Realm;
    chrome: Chrome;
    title: string;
    /** Where the back affordance goes; the pushed screens always name one. */
    back?: string;
    /** Optional top-bar action: the customer's basket or the support channel. */
    action?: "cart" | "help";
    children: ReactNode;
}

/** A quiet strip above the content while the device has no connection. */
function OfflineBanner() {
    const [online, setOnline] = useState(() => window.navigator.onLine);

    useEffect(() => {
        const onOnline = () => setOnline(true);
        const onOffline = () => setOnline(false);

        window.addEventListener("online", onOnline);
        window.addEventListener("offline", onOffline);

        return () => {
            window.removeEventListener("online", onOnline);
            window.removeEventListener("offline", onOffline);
        };
    }, []);

    if (online) {
        return null;
    }

    return (
        <div className="flex w-full items-center gap-space-sm bg-error-container px-margin py-space-sm">
            <MaterialIcon name="wifi_off" className="text-[16px] text-on-error-container" />
            <span className="font-label-sm text-label-sm text-on-error-container">
                Tidak ada koneksi. Data mungkin belum yang terbaru.
            </span>
        </div>
    );
}

function TopBarAction({ action }: { action: "cart" | "help" }) {
    const { count } = useCart();

    if (action === "cart") {
        return (
            <Link
                href="/client/checkout"
                aria-label="Keranjang"
                className="relative flex h-11 w-11 items-center justify-center text-on-surface"
            >
                <MaterialIcon name="shopping_cart" className="text-[22px]" />
                {count > 0 ? (
                    <span className="absolute top-1 right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 font-label-sm text-label-sm text-on-primary">
                        {count}
                    </span>
                ) : null}
            </Link>
        );
    }

    return (
        <a
            href={whatsappLink(HELP_MESSAGE)}
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Bantuan"
            className="flex h-11 w-11 items-center justify-center text-on-surface"
        >
            <MaterialIcon name="help" className="text-[22px]" />
        </a>
    );
}

/**
 * The chrome every screen sits in: an optional top bar (a large title, or a back
 * affordance and the screen's name), the scroll body, and the tab bar for the
 * destinations that belong to one.
 */
export function Shell({ realm, chrome, title, back, action, children }: ShellProps) {
    const { navigate } = useRouter();
    const compact = chrome === "back";

    return (
        <div className="flex min-h-dvh w-full flex-col bg-surface font-sans text-on-surface antialiased">
            {chrome === "plain" ? <OfflineBanner /> : null}

            {chrome === "plain" ? null : (
                <header className="sticky top-0 z-40 w-full bg-surface/90 pt-safe shadow-[0_1px_8px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                    <OfflineBanner />
                    <div
                        className={cx(
                            "flex w-full items-center gap-space-sm px-margin",
                            compact ? "h-14" : "h-auto min-h-16 py-space-sm"
                        )}
                    >
                        {compact ? (
                            <button
                                type="button"
                                aria-label="Kembali"
                                onClick={() => (back === undefined ? window.history.back() : navigate(back))}
                                className="flex h-11 w-11 shrink-0 items-center justify-center text-on-surface transition-colors hover:text-primary"
                            >
                                <MaterialIcon name="arrow_back" />
                            </button>
                        ) : null}

                        <h1
                            className={cx(
                                "min-w-0 flex-1 truncate",
                                compact
                                    ? "font-headline-sm text-headline-sm text-on-surface"
                                    : "font-headline-lg text-headline-lg text-on-surface"
                            )}
                        >
                            {title}
                        </h1>

                        {action === undefined ? null : <TopBarAction action={action} />}
                    </div>
                </header>
            )}

            <main className="flex w-full flex-1 flex-col">{children}</main>

            {chrome === "tabs" ? <TabBar realm={realm} /> : null}
        </div>
    );
}
