import { Head, router } from "@inertiajs/react";
import { type FormEvent, useState } from "react";
import { AnterajaLogo } from "@/components/AnterajaLogo";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ApiError, api } from "@/lib/api";

/**
 * Admin sign-in. The credentials go to the JSON API (which only admits active
 * admins) and the session cookie it returns is what the console then uses.
 */
export default function Login() {
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (submitting) return;

        setSubmitting(true);
        setError(null);

        try {
            await api.auth.login(email, password);
            router.visit("/admin/shipment");
        } catch (caught) {
            const message =
                caught instanceof ApiError
                    ? (caught.errors.email?.[0] ?? caught.message)
                    : "Tidak dapat masuk. Coba lagi.";
            setError(message);
            setSubmitting(false);
        }
    }

    return (
        <div className="flex min-h-screen w-full items-center justify-center bg-surface px-margin py-margin font-sans text-on-surface antialiased">
            <Head title="Masuk" />

            <div className="w-full max-w-sm">
                <AnterajaLogo className="h-9" />
                <h1 className="mt-space-sm font-headline-lg text-headline-lg text-on-surface">Masuk Admin</h1>
                <p className="mt-space-xs font-body-md text-body-md text-secondary">
                    Konsol operasional Anteraja Frozen. Hanya admin aktif yang dapat masuk.
                </p>

                <form
                    onSubmit={handleSubmit}
                    className="mt-space-lg flex flex-col gap-space-md rounded-xl border border-outline-variant bg-surface-container-lowest p-space-lg"
                >
                    <label className="flex flex-col gap-1" htmlFor="email">
                        <span className="font-label-sm text-label-sm text-secondary uppercase">Email</span>
                        <input
                            id="email"
                            type="email"
                            value={email}
                            onChange={(event) => setEmail(event.target.value)}
                            required
                            autoComplete="username"
                            placeholder="nama@anteraja.id"
                            className="h-10 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                        />
                    </label>

                    <label className="flex flex-col gap-1" htmlFor="password">
                        <span className="font-label-sm text-label-sm text-secondary uppercase">Kata Sandi</span>
                        <input
                            id="password"
                            type="password"
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            required
                            autoComplete="current-password"
                            placeholder="••••••••"
                            className="h-10 rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                        />
                    </label>

                    {error ? (
                        <div
                            role="alert"
                            className="flex items-start gap-space-xs rounded-lg bg-error-container p-space-sm font-body-sm text-body-sm text-on-error-container"
                        >
                            <MaterialIcon name="error" className="text-[18px]" />
                            <span>{error}</span>
                        </div>
                    ) : null}

                    <button
                        type="submit"
                        disabled={submitting}
                        className="flex h-10 items-center justify-center gap-space-xs rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-50"
                    >
                        <MaterialIcon name={submitting ? "refresh" : "login"} className="text-[18px]" />
                        <span>{submitting ? "Memproses…" : "Masuk"}</span>
                    </button>
                </form>
            </div>
        </div>
    );
}
