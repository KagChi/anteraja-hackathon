import { useEffect, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import type { Realm } from "../lib/token";

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: "accepted" | "dismissed" }>;
}

const COPY: Record<Realm, { title: string; body: string }> = {
    client: {
        title: "Pasang Anteraja Frozen",
        body: "Pasang aplikasi ini di layar utama supaya pesanan dan pelacakan terbuka tanpa browser."
    },
    courier: {
        title: "Pasang Anteraja Kurir",
        body: "Pasang aplikasi ini di layar utama supaya daftar tugas terbuka langsung dari ikon."
    }
};

function isStandalone(): boolean {
    return window.matchMedia("(display-mode: standalone)").matches;
}

function isIos(): boolean {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

/**
 * The install affordance.
 *
 * Chromium offers a real prompt; iOS Safari has none, so it gets the share-sheet
 * instructions instead. Both disappear once the app is running installed.
 */
export function InstallCard({ realm }: { realm: Realm }) {
    const [prompt, setPrompt] = useState<BeforeInstallPromptEvent | null>(null);
    const [installed, setInstalled] = useState(isStandalone);

    useEffect(() => {
        const onPrompt = (event: Event) => {
            event.preventDefault();
            setPrompt(event as BeforeInstallPromptEvent);
        };
        const onInstalled = () => setInstalled(true);

        window.addEventListener("beforeinstallprompt", onPrompt);
        window.addEventListener("appinstalled", onInstalled);

        return () => {
            window.removeEventListener("beforeinstallprompt", onPrompt);
            window.removeEventListener("appinstalled", onInstalled);
        };
    }, []);

    if (installed) {
        return null;
    }

    const copy = COPY[realm];

    return (
        <section className="flex w-full flex-col gap-space-sm rounded-xl bg-surface-container-lowest p-space-lg">
            <div className="flex items-center gap-space-sm">
                <span className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <MaterialIcon name="install_mobile" className="text-[22px]" />
                </span>
                <span className="font-headline-sm text-headline-sm text-on-surface">{copy.title}</span>
            </div>
            <p className="font-body-sm text-body-sm leading-relaxed text-secondary">{copy.body}</p>

            {prompt !== null ? (
                <button
                    type="button"
                    onClick={() => void prompt.prompt()}
                    className="inline-flex h-11 items-center justify-center gap-1.5 rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                >
                    <MaterialIcon name="download" className="text-[18px]" />
                    Pasang aplikasi
                </button>
            ) : (
                <p className="rounded-lg bg-surface-container-low p-space-md font-body-sm text-body-sm leading-relaxed text-on-surface">
                    {isIos()
                        ? "Di iPhone: tekan tombol Bagikan, lalu pilih “Tambahkan ke Layar Utama”."
                        : "Buka menu browser, lalu pilih “Pasang aplikasi” atau “Tambahkan ke layar utama”."}
                </p>
            )}
        </section>
    );
}
