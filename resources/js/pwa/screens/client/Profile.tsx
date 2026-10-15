import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { InstallCard } from "../../components/InstallCard";
import { StateCard } from "../../components/StateCard";
import { clientApi } from "../../lib/api";
import { useRouter } from "../../lib/router";
import { HELP_MESSAGE, whatsappLink } from "../../lib/support";
import { clearToken, isSignedIn } from "../../lib/token";
import { useLoad } from "../../lib/useLoad";

const SUPPORT_LABEL = "Layanan Pelanggan Anteraja Frozen";

/** The sender's identity, their numbers, and the one channel out. */
export default function Profile() {
    const { navigate } = useRouter();
    const [busy, setBusy] = useState(false);
    const { data, error, reload } = useLoad(() => clientApi.me(), "client-me");
    const profile = data?.data ?? null;

    async function signOut() {
        setBusy(true);

        try {
            await clientApi.auth.logout();
        } catch {
            // The token is unusable either way; still leave the app.
        }

        clearToken("client");
        navigate("/client/login", { replace: true });
    }

    if (!isSignedIn("client")) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-xl">
                    <MaterialIcon name="person" className="text-[26px] text-secondary" />
                    <span className="font-headline-md text-headline-md text-on-surface">Anda belum masuk</span>
                    <span className="font-body-md text-body-md text-secondary">
                        Masuk untuk melihat profil dan riwayat pengiriman Anda.
                    </span>
                    <button
                        type="button"
                        onClick={() => navigate("/client/login")}
                        className="mt-space-sm inline-flex h-11 items-center justify-center gap-1.5 rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                    >
                        <MaterialIcon name="login" className="text-[18px]" />
                        Masuk
                    </button>
                </section>
            </div>
        );
    }

    if (error !== null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <StateCard message={error} onRetry={reload} />
            </div>
        );
    }

    if (profile === null) {
        return <div className="p-margin font-body-md text-body-md text-secondary">Memuat profil…</div>;
    }

    const supportUrl = profile.support.whatsapp_url === "" ? whatsappLink(HELP_MESSAGE) : profile.support.whatsapp_url;

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin">
            <section className="flex w-full flex-col items-center gap-space-md rounded-xl bg-surface-container-lowest p-space-xl">
                <span className="flex h-16 w-16 items-center justify-center rounded-full bg-primary">
                    <MaterialIcon name="person" className="text-[30px] text-on-primary" />
                </span>
                <span className="flex flex-col items-center gap-0.5">
                    <span className="font-headline-md text-headline-md text-on-surface">{profile.name}</span>
                    <span className="font-body-sm text-body-sm text-secondary">{profile.phone}</span>
                </span>
            </section>

            <div className="flex w-full gap-space-sm">
                <Stat value={String(profile.stats.active_count)} label="Pesanan aktif" />
                <Stat value={String(profile.stats.completed_count)} label="Selesai" />
                <Stat value={profile.stats.on_time_rate} label="Tepat waktu" />
            </div>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-start gap-space-md">
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-surface-container">
                        <MaterialIcon name="location_on" className="text-[18px] text-secondary" />
                    </span>
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Alamat Utama</span>
                        <span className="font-body-md text-body-md text-on-surface select-all">{profile.address}</span>
                    </span>
                </div>
            </section>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Kontak</span>

                <a
                    href={supportUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex w-full items-center gap-space-md"
                >
                    <MaterialIcon name="support_agent" className="text-[18px] text-primary" />
                    <span className="flex-1 font-body-md text-body-md text-on-surface">{SUPPORT_LABEL}</span>
                    <MaterialIcon name="arrow_forward" className="text-[16px] text-primary" />
                </a>

                <a href={`mailto:${profile.support.email}`} className="flex w-full items-center gap-space-md">
                    <MaterialIcon name="mail" className="text-[18px] text-secondary" />
                    <span className="flex-1 font-body-md text-body-md text-on-surface select-all">
                        {profile.support.email}
                    </span>
                </a>
            </section>

            <button
                type="button"
                disabled={busy}
                onClick={() => void signOut()}
                className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface disabled:opacity-60"
            >
                <MaterialIcon name="logout" className="text-[20px]" />
                {busy ? "Keluar…" : "Keluar"}
            </button>

            <InstallCard realm="client" />
        </div>
    );
}

function Stat({ value, label }: { value: string; label: string }) {
    return (
        <div className="flex flex-1 flex-col items-center gap-1 rounded-xl bg-surface-container-lowest p-2">
            <span className="font-headline-md text-headline-md text-on-surface">{value}</span>
            <span className="text-center font-body-sm text-body-sm text-secondary">{label}</span>
        </div>
    );
}
