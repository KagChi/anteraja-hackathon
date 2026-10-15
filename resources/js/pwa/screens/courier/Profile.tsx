import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { InstallCard } from "../../components/InstallCard";
import { StateCard } from "../../components/StateCard";
import { courierApi } from "../../lib/api";
import { useRouter } from "../../lib/router";
import { clearToken, isSignedIn } from "../../lib/token";
import { useLoad } from "../../lib/useLoad";

/** The courier's identity, their route, and the way out. */
export default function Profile() {
    const { navigate } = useRouter();
    const [busy, setBusy] = useState(false);
    const { data, error, reload } = useLoad(() => courierApi.profile(), "courier-profile");
    const profile = data?.profile ?? null;

    async function signOut() {
        setBusy(true);

        try {
            await courierApi.auth.logout();
        } catch {
            // The token is unusable either way; still leave the app.
        }

        clearToken("courier");
        navigate("/courier/login", { replace: true });
    }

    if (!isSignedIn("courier")) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-xl">
                    <MaterialIcon name="badge" className="text-[26px] text-secondary" />
                    <span className="font-headline-md text-headline-md text-on-surface">Anda belum masuk</span>
                    <button
                        type="button"
                        onClick={() => navigate("/courier/login")}
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

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin">
            <section className="flex w-full flex-col items-center gap-space-md rounded-xl bg-surface-container-lowest p-space-xl">
                <span className="flex h-16 w-16 items-center justify-center rounded-full bg-primary">
                    <MaterialIcon name="person" className="text-[30px] text-on-primary" />
                </span>
                <span className="flex flex-col items-center gap-0.5">
                    <span className="font-headline-md text-headline-md text-on-surface">{profile.name}</span>
                    <span className="flex items-center gap-1 font-body-sm text-body-sm text-tertiary">
                        <span className="inline-block h-1.5 w-1.5 rounded-full bg-tertiary" />
                        {profile.status}
                    </span>
                </span>
            </section>

            <div className="flex w-full gap-space-sm">
                {profile.stats.map((stat) => (
                    <span
                        key={stat.label}
                        className="flex flex-1 flex-col items-center gap-1 rounded-xl bg-surface-container-lowest p-2"
                    >
                        <span className="font-headline-md text-headline-md text-on-surface">{stat.value}</span>
                        <span className="text-center font-body-sm text-body-sm text-secondary">{stat.label}</span>
                    </span>
                ))}
            </div>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Identitas Kurir</span>

                <Row icon="badge" label="ID Kurir" value={profile.id} />
                <Row icon="call" label="Nomor HP" value={profile.phone} />
                <Row icon="warehouse" label="Hub" value={profile.hub} />
                <Row icon="two_wheeler" label="Kendaraan" value={profile.vehicle} />
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

            <InstallCard realm="courier" />
        </div>
    );
}

function Row({ icon, label, value }: { icon: string; label: string; value: string }) {
    return (
        <div className="flex w-full items-center gap-space-md">
            <MaterialIcon name={icon} className="text-[18px] text-secondary" />
            <span className="flex flex-1 flex-col gap-0.5">
                <span className="font-body-sm text-body-sm text-secondary">{label}</span>
                <span className="font-body-md text-body-md text-on-surface select-all">{value}</span>
            </span>
        </div>
    );
}
