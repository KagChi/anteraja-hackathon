import { MaterialIcon } from "@/components/MaterialIcon";
import { Reveal } from "@/components/site/Reveal";
import { cx } from "@/lib/cx";

/** One app card, built from the registry in config/pwa.php. */
export interface AppCard {
    key: string;
    name: string;
    url: string;
    audience: string;
    description: string;
    icon: string;
    points: string[];
}

interface AppSectionProps {
    apps: AppCard[];
}

/**
 * The way into the two apps.
 *
 * Both are installable web apps, so there is nothing to download: the card
 * opens the app itself, and the note underneath explains how to put it on the
 * home screen.
 */
export function AppSection({ apps }: AppSectionProps) {
    return (
        <section id="unduh" className="border-black/5 border-t bg-surface-container-low py-16 sm:py-20">
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 sm:px-6">
                <Reveal className="flex flex-col items-center gap-3 text-center">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm text-primary uppercase">
                        <MaterialIcon name="install_mobile" className="text-[14px]" />
                        Aplikasi web
                    </span>
                    <h2 className="font-headline-lg text-headline-lg text-on-surface sm:text-[30px]">
                        Buka atau pasang aplikasinya
                    </h2>
                    <p className="max-w-2xl font-body-md text-body-md text-secondary">
                        Dua aplikasi, satu backend. Buka langsung di browser, atau pasang ke layar utama supaya terbuka
                        seperti aplikasi biasa — tanpa unduhan berkas.
                    </p>
                </Reveal>

                <div className="grid gap-5 md:grid-cols-2">
                    {apps.map((app, index) => (
                        <Reveal key={app.key} delay={index * 90}>
                            <article
                                className={cx(
                                    "flex h-full flex-col gap-4 rounded-2xl border bg-surface-container-lowest p-6 transition-colors",
                                    app.key === "client" ? "border-primary/30" : "border-black/5"
                                )}
                            >
                                <div className="flex items-start gap-3">
                                    <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <MaterialIcon name={app.icon} className="text-[24px]" />
                                    </span>
                                    <div className="flex flex-col">
                                        <span className="font-label-sm text-label-sm text-primary uppercase">
                                            {app.audience}
                                        </span>
                                        <span className="font-headline-md text-headline-md text-on-surface">
                                            {app.name}
                                        </span>
                                    </div>
                                </div>

                                <p className="font-body-sm text-body-sm leading-relaxed text-secondary">
                                    {app.description}
                                </p>

                                <ul className="flex list-none flex-col gap-1.5 p-0">
                                    {app.points.map((point) => (
                                        <li
                                            key={point}
                                            className="flex items-center gap-2 font-body-sm text-body-sm text-on-surface"
                                        >
                                            <MaterialIcon name="check_circle" className="text-[15px] text-tertiary" />
                                            {point}
                                        </li>
                                    ))}
                                </ul>

                                <div className="mt-auto flex flex-col gap-2 pt-2">
                                    {/* A real page load on purpose: the app is its
                                        own shell, not an Inertia page. */}
                                    <a
                                        href={app.url}
                                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                                    >
                                        <MaterialIcon name="open_in_new" className="text-[18px]" />
                                        Buka {app.name}
                                    </a>
                                    <span className="font-label-sm text-label-sm text-secondary">
                                        Pasang: buka aplikasinya, lalu pilih “Pasang aplikasi” di menu browser. Di
                                        iPhone: tombol Bagikan → “Tambahkan ke Layar Utama”.
                                    </span>
                                </div>
                            </article>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}
