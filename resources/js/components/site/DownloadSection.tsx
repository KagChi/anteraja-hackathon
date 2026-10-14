import { MaterialIcon } from "@/components/MaterialIcon";
import { Reveal } from "@/components/site/Reveal";
import { cx } from "@/lib/cx";

export interface DownloadApp {
    key: string;
    name: string;
    app_id: string;
    apk_url: string;
    version: string;
}

interface DownloadSectionProps {
    apps: DownloadApp[];
}

/** What each app is for, keyed by the registry key in mobile/apps.php. */
const APP_COPY: Record<string, { audience: string; description: string; icon: string; points: string[] }> = {
    client: {
        audience: "Untuk pelanggan",
        description: "Belanja produk beku, pilih jadwal antar, dan pantau status kiriman Anda.",
        icon: "ac_unit",
        points: ["Katalog produk frozen", "Checkout dan jadwal mandiri", "Lacak resi dan konfirmasi"]
    },
    kurir: {
        audience: "Untuk kurir",
        description: "Terima tugas, jalankan pemeriksaan pra-antar, dan unggah bukti penyerahan.",
        icon: "local_shipping",
        points: ["Daftar tugas harian", "Validasi lokasi serah terima", "Riwayat pengantaran"]
    }
};

/**
 * The Android download cards.
 *
 * The URLs come from config/mobile.php (overridable per environment) rather
 * than being hard-coded here, so hosting the APKs elsewhere is a deployment
 * change and never a code change.
 */
export function DownloadSection({ apps }: DownloadSectionProps) {
    return (
        <section id="unduh" className="border-black/5 border-t bg-surface-container-low py-16 sm:py-20">
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 sm:px-6">
                <Reveal className="flex flex-col items-center gap-3 text-center">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm text-primary uppercase">
                        <MaterialIcon name="android" className="text-[14px]" />
                        Aplikasi Android
                    </span>
                    <h2 className="font-headline-lg text-headline-lg text-on-surface sm:text-[30px]">
                        Unduh dan mulai kirim hari ini
                    </h2>
                    <p className="max-w-2xl font-body-md text-body-md text-secondary">
                        Dua aplikasi, satu backend. Pasang berkas APK di bawah ini pada perangkat Android Anda;
                        pembaruan berikutnya datang dari berkas yang sama.
                    </p>
                </Reveal>

                <div className="grid gap-5 md:grid-cols-2">
                    {apps.map((app, index) => {
                        const copy = APP_COPY[app.key] ?? {
                            audience: "Aplikasi",
                            description: "Aplikasi resmi Anteraja Frozen.",
                            icon: "apps",
                            points: []
                        };

                        return (
                            <Reveal key={app.key} delay={index * 90}>
                                <article
                                    className={cx(
                                        "flex h-full flex-col gap-4 rounded-2xl border bg-surface-container-lowest p-6 transition-colors",
                                        app.key === "client" ? "border-primary/30" : "border-black/5"
                                    )}
                                >
                                    <div className="flex items-start gap-3">
                                        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                            <MaterialIcon name={copy.icon} className="text-[24px]" />
                                        </span>
                                        <div className="flex flex-col">
                                            <span className="font-label-sm text-label-sm text-primary uppercase">
                                                {copy.audience}
                                            </span>
                                            <span className="font-headline-md text-headline-md text-on-surface">
                                                {app.name}
                                            </span>
                                        </div>
                                    </div>

                                    <p className="font-body-sm text-body-sm leading-relaxed text-secondary">
                                        {copy.description}
                                    </p>

                                    <ul className="flex list-none flex-col gap-1.5 p-0">
                                        {copy.points.map((point) => (
                                            <li
                                                key={point}
                                                className="flex items-center gap-2 font-body-sm text-body-sm text-on-surface"
                                            >
                                                <MaterialIcon
                                                    name="check_circle"
                                                    className="text-[15px] text-tertiary"
                                                />
                                                {point}
                                            </li>
                                        ))}
                                    </ul>

                                    <div className="mt-auto flex flex-col gap-2 pt-2">
                                        <a
                                            href={app.apk_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                                        >
                                            <MaterialIcon name="download" className="text-[18px]" />
                                            Unduh APK
                                        </a>
                                        <span className="font-label-sm text-label-sm text-secondary">
                                            {app.version} • {app.app_id}
                                        </span>
                                    </div>
                                </article>
                            </Reveal>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
