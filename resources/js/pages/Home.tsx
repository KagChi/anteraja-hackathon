import { Head, Link } from "@inertiajs/react";
import { AnterajaLogo } from "@/components/AnterajaLogo";
import { MaterialIcon } from "@/components/MaterialIcon";

interface AppLink {
    title: string;
    description: string;
    icon: string;
    href: string;
}

const APPS: AppLink[] = [
    {
        title: "Admin Console",
        description: "Pantau pengiriman, tangani kendala, dan tinjau laporan harian.",
        icon: "monitoring",
        href: "/admin/shipment"
    },
    {
        title: "Aplikasi Kurir",
        description: "Terima tugas, antar paket beku, dan unggah bukti penyerahan.",
        icon: "local_shipping",
        href: "/courier/tasks"
    },
    {
        title: "Aplikasi Pelanggan",
        description: "Lacak kurir beku, konfirmasi jadwal, dan lihat kendala pengiriman.",
        icon: "ac_unit",
        href: "/client/tracking"
    }
];

export default function Home() {
    return (
        <div className="flex min-h-screen w-full flex-col items-center justify-center bg-surface px-margin py-margin font-sans text-on-surface antialiased">
            <Head title="Pilih Aplikasi" />
            <div className="w-full max-w-5xl">
                <AnterajaLogo className="h-9" />
                <h1 className="mt-space-sm font-headline-lg text-headline-lg text-on-surface">Pilih Aplikasi</h1>
                <p className="mt-space-xs font-body-md text-body-md text-secondary">
                    Tiga antarmuka Anteraja Frozen: dashboard admin, aplikasi kurir, dan aplikasi pelanggan.
                </p>

                <div className="mt-space-lg grid grid-cols-1 gap-space-md sm:grid-cols-2 lg:grid-cols-3">
                    {APPS.map((app) => (
                        <Link
                            key={app.href}
                            href={app.href}
                            className="flex flex-col gap-space-sm rounded-xl border border-outline-variant bg-surface-container-lowest p-space-lg transition-colors hover:border-primary"
                        >
                            <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-container text-primary">
                                <MaterialIcon name={app.icon} className="text-[20px]" />
                            </span>
                            <span className="font-headline-sm text-headline-sm text-on-surface">{app.title}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{app.description}</span>
                            <span className="mt-space-xs flex items-center gap-1 font-label-md text-label-md text-primary">
                                Buka
                                <MaterialIcon name="arrow_forward" className="text-[16px]" />
                            </span>
                        </Link>
                    ))}
                </div>
            </div>
        </div>
    );
}
