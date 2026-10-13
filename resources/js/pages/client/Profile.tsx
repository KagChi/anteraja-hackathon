import { MaterialIcon } from "@/components/MaterialIcon";
import { CLIENT_PROFILE, SUPPORT, whatsappLink } from "@/data/client";
import { ClientLayout } from "@/layouts";

export default function Profile() {
    return (
        <ClientLayout title="Profil">
            <div className="flex w-full flex-col gap-space-md pb-space-lg">
                <section className="flex flex-col items-center gap-space-sm rounded-xl border border-black/5 bg-surface-container-lowest p-space-lg">
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary">
                        <MaterialIcon name="person" className="text-[28px] text-on-primary" />
                    </div>
                    <div className="flex flex-col items-center">
                        <span className="font-headline-sm text-headline-sm text-on-surface">{CLIENT_PROFILE.name}</span>
                        <span className="font-body-sm text-body-sm text-secondary">{CLIENT_PROFILE.phone}</span>
                    </div>
                </section>

                <section className="grid grid-cols-3 gap-space-sm">
                    {CLIENT_PROFILE.stats.map((stat) => (
                        <div
                            key={stat.label}
                            className="flex flex-col items-center rounded-xl border border-black/5 bg-surface-container-lowest p-space-sm"
                        >
                            <span className="font-headline-sm text-headline-sm font-semibold text-on-surface">
                                {stat.value}
                            </span>
                            <span className="text-center font-label-sm text-label-sm text-secondary">{stat.label}</span>
                        </div>
                    ))}
                </section>

                <section className="flex flex-col gap-space-sm rounded-xl border border-black/5 bg-surface-container-lowest p-space-md">
                    <div className="flex items-start gap-space-sm">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-surface-container text-secondary">
                            <MaterialIcon name="location_on" className="text-[18px]" />
                        </div>
                        <div className="flex flex-col">
                            <span className="font-label-sm text-label-sm text-secondary">Alamat Utama</span>
                            <span className="font-label-md text-label-md text-on-surface">
                                {CLIENT_PROFILE.address}
                            </span>
                        </div>
                    </div>
                    <p className="rounded-lg bg-surface-container-low p-space-sm font-body-sm text-body-sm text-secondary">
                        {CLIENT_PROFILE.note}
                    </p>
                </section>

                <section className="flex flex-col gap-space-sm rounded-xl border border-black/5 bg-surface-container-lowest p-space-md">
                    <span className="font-label-sm text-label-sm uppercase text-secondary">Kontak</span>
                    <a
                        href={whatsappLink("Halo, saya butuh bantuan dengan pengiriman Anteraja Frozen.")}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-space-sm font-label-md text-label-md text-on-surface transition-colors hover:text-primary"
                    >
                        <MaterialIcon name="support_agent" className="text-[18px] text-primary" />
                        <span>{SUPPORT.label}</span>
                    </a>
                    <span className="flex items-center gap-space-sm font-label-md text-label-md text-on-surface">
                        <MaterialIcon name="mail" className="text-[18px] text-secondary" />
                        <span>{CLIENT_PROFILE.email}</span>
                    </span>
                </section>
            </div>
        </ClientLayout>
    );
}
