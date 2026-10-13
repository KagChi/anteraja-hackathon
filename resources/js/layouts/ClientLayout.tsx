import { Head, Link } from "@inertiajs/react";
import type { ReactNode } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { MobileShell } from "@/components/MobileShell";

interface ClientLayoutProps {
    children: ReactNode;
    title: string;
    /**
     * Destination for the header back affordance. When omitted the button
     * falls back to browser history, matching the source prototype.
     */
    backTo?: string;
}

/** Customer-facing shell for the Anteraja Frozen delivery screens. */
export function ClientLayout({ children, title, backTo }: ClientLayoutProps) {
    const backClass =
        "flex min-h-[44px] min-w-[44px] items-center justify-center text-on-surface transition-colors hover:text-primary";

    return (
        <MobileShell
            className="client-app"
            header={
                <header className="sticky top-0 z-50 bg-surface/90 pt-safe backdrop-blur-xl">
                    <div className="flex h-14 items-center justify-between px-margin">
                        <div className="flex items-center gap-space-sm">
                            {backTo ? (
                                <Link href={backTo} aria-label="Kembali" className={backClass}>
                                    <MaterialIcon name="arrow_back_ios_new" className="text-[20px]" />
                                </Link>
                            ) : (
                                <button
                                    type="button"
                                    aria-label="Kembali"
                                    onClick={() => window.history.back()}
                                    className={backClass}
                                >
                                    <MaterialIcon name="arrow_back_ios_new" className="text-[20px]" />
                                </button>
                            )}
                            <div className="flex flex-col">
                                <span className="font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                                    Anteraja Frozen
                                </span>
                                <h1 className="font-headline-sm text-headline-sm leading-tight text-on-surface">
                                    {title}
                                </h1>
                            </div>
                        </div>
                        <div className="flex items-center gap-space-sm">
                            <button
                                type="button"
                                aria-label="Bantuan"
                                className="flex min-h-[44px] min-w-[44px] items-center justify-center text-secondary transition-colors hover:text-on-surface"
                            >
                                <MaterialIcon name="help_outline" className="text-[22px]" />
                            </button>
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary">
                                <MaterialIcon name="person" className="text-[18px] text-on-primary" />
                            </div>
                        </div>
                    </div>
                </header>
            }
            bodyClassName="px-margin pt-space-md pb-safe"
        >
            <Head title={title} />
            {children}
        </MobileShell>
    );
}
