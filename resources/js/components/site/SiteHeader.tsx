import { Link } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { AnterajaLogo } from "@/components/AnterajaLogo";
import { MaterialIcon } from "@/components/MaterialIcon";
import { NAV_LINKS } from "@/data/landing";
import { cx } from "@/lib/cx";

/**
 * The landing page's sticky header.
 *
 * Over the dark hero it is transparent; once the page scrolls it turns into a
 * frosted white bar so the links stay readable over the light sections.
 */
export function SiteHeader() {
    const [scrolled, setScrolled] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 24);
        onScroll();
        window.addEventListener("scroll", onScroll, { passive: true });

        return () => window.removeEventListener("scroll", onScroll);
    }, []);

    return (
        <header
            className={cx(
                "fixed inset-x-0 top-0 z-50 transition-colors duration-300",
                scrolled ? "border-black/5 border-b bg-surface/90 backdrop-blur-xl" : "bg-transparent"
            )}
        >
            <div className="mx-auto flex h-16 w-full max-w-6xl items-center gap-space-sm px-4 sm:px-6">
                <Link href="/" className="flex items-center gap-2" aria-label="Anteraja Frozen">
                    <AnterajaLogo className="h-7" />
                    <span
                        className={cx(
                            "hidden font-headline-sm text-headline-sm sm:inline",
                            scrolled ? "text-on-surface" : "text-white"
                        )}
                    >
                        Anteraja Frozen
                    </span>
                </Link>

                <nav className="ml-auto hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
                    {NAV_LINKS.map((link) => (
                        <a
                            key={link.href}
                            href={link.href}
                            className={cx(
                                "rounded-full px-3 py-2 font-label-md text-label-md transition-colors",
                                scrolled
                                    ? "text-secondary hover:bg-surface-container hover:text-on-surface"
                                    : "text-white/80 hover:bg-white/10 hover:text-white"
                            )}
                        >
                            {link.label}
                        </a>
                    ))}
                </nav>

                <div className="ml-auto flex items-center gap-2 lg:ml-0">
                    <Link
                        href="/login"
                        className={cx(
                            "hidden items-center gap-1.5 rounded-full border px-3 py-2 font-label-md text-label-md transition-colors sm:inline-flex",
                            scrolled
                                ? "border-outline-variant text-on-surface hover:border-primary hover:text-primary"
                                : "border-white/30 text-white hover:border-white hover:bg-white/10"
                        )}
                    >
                        <MaterialIcon name="admin_panel_settings" className="text-[16px]" />
                        Masuk Admin
                    </Link>
                    <a
                        href="#unduh"
                        className="inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-2 font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        <MaterialIcon name="android" className="text-[16px]" />
                        Unduh
                    </a>
                    <button
                        type="button"
                        onClick={() => setMenuOpen((open) => !open)}
                        aria-label="Buka menu"
                        aria-expanded={menuOpen}
                        className={cx(
                            "flex h-9 w-9 items-center justify-center rounded-full lg:hidden",
                            scrolled ? "text-on-surface hover:bg-surface-container" : "text-white hover:bg-white/10"
                        )}
                    >
                        <MaterialIcon name={menuOpen ? "close" : "menu"} className="text-[22px]" />
                    </button>
                </div>
            </div>

            {menuOpen ? (
                <div className="border-black/5 border-t bg-surface/95 px-4 py-3 backdrop-blur-xl lg:hidden">
                    <div className="mx-auto flex w-full max-w-6xl flex-col">
                        {NAV_LINKS.map((link) => (
                            <a
                                key={link.href}
                                href={link.href}
                                onClick={() => setMenuOpen(false)}
                                className="rounded-lg px-3 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container"
                            >
                                {link.label}
                            </a>
                        ))}
                        <Link
                            href="/login"
                            className="mt-1 flex items-center gap-2 rounded-lg px-3 py-2.5 font-label-md text-label-md text-primary hover:bg-surface-container"
                        >
                            <MaterialIcon name="admin_panel_settings" className="text-[16px]" />
                            Masuk Admin
                        </Link>
                    </div>
                </div>
            ) : null}
        </header>
    );
}
