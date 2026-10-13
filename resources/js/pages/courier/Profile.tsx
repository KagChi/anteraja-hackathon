import { MaterialIcon } from "@/components/MaterialIcon";
import { COURIER_PROFILE } from "@/data/courier";
import { CourierLayout } from "@/layouts";

export default function Profile() {
    return (
        <CourierLayout title="Profil Kurir">
            <div className="flex w-full flex-col gap-space-md">
                <section className="flex flex-col items-center gap-space-sm rounded-xl bg-surface-container-lowest p-space-lg">
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary">
                        <MaterialIcon name="person" className="text-[28px] text-on-primary" />
                    </div>
                    <div className="flex flex-col items-center">
                        <span className="font-headline-sm text-headline-sm text-on-surface">
                            {COURIER_PROFILE.name}
                        </span>
                        <span className="flex items-center gap-1 font-label-sm text-label-sm text-tertiary">
                            <span className="inline-block h-1.5 w-1.5 rounded-full bg-tertiary" />
                            {COURIER_PROFILE.status}
                        </span>
                    </div>
                    <span className="rounded-full bg-surface-container px-2 py-0.5 font-data-mono text-label-sm text-secondary">
                        ID: {COURIER_PROFILE.id}
                    </span>
                </section>

                <section className="grid grid-cols-3 gap-space-sm">
                    {COURIER_PROFILE.stats.map((stat) => (
                        <div key={stat.label} className="rounded-xl bg-surface-container-lowest p-space-sm text-center">
                            <span className="block font-label-sm text-label-sm text-secondary">{stat.label}</span>
                            <span className="font-headline-sm text-headline-sm font-semibold text-on-surface">
                                {stat.value}
                            </span>
                        </div>
                    ))}
                </section>

                <section className="flex flex-col gap-space-sm rounded-xl bg-surface-container-lowest p-space-md">
                    {[
                        { icon: "call", label: "Telepon", value: COURIER_PROFILE.phone },
                        { icon: "warehouse", label: "Hub", value: COURIER_PROFILE.hub },
                        { icon: "two_wheeler", label: "Armada", value: COURIER_PROFILE.vehicle }
                    ].map((row) => (
                        <div key={row.label} className="flex items-center gap-space-sm">
                            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-surface-container text-secondary">
                                <MaterialIcon name={row.icon} className="text-[18px]" />
                            </div>
                            <div className="flex flex-col">
                                <span className="font-label-sm text-label-sm text-secondary">{row.label}</span>
                                <span className="font-label-md text-label-md text-on-surface">{row.value}</span>
                            </div>
                        </div>
                    ))}
                </section>
            </div>
        </CourierLayout>
    );
}
