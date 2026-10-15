import { type FormEvent, useEffect, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import type { ScreenProps } from "../../App";
import { ActionError } from "../../components/ActionError";
import { TextField } from "../../components/Field";
import { clientApi, firstError } from "../../lib/api";
import { useRouter } from "../../lib/router";
import { isSignedIn, nextPath, setToken, signedInHome } from "../../lib/token";

/** Customer sign-in: phone number and password, straight to the Pesanan tab. */
export default function Login({ query }: ScreenProps) {
    const { navigate } = useRouter();
    const target = nextPath(query, "client", signedInHome("client"));
    const [phone, setPhone] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (isSignedIn("client")) {
            navigate(target, { replace: true });
        }
    }, [navigate, target]);

    async function submit(event: FormEvent) {
        event.preventDefault();

        if (busy) {
            return;
        }

        setError(null);

        if (phone.trim() === "" || password === "") {
            setError("Nomor HP dan password wajib diisi.");
            return;
        }

        setBusy(true);

        try {
            const response = await clientApi.auth.login(phone.trim(), password);
            const token = response.data.token;

            if (token === "") {
                setError("Login gagal: token tidak diterima.");
                return;
            }

            setToken("client", token);
            navigate(target, { replace: true });
        } catch (failure) {
            setError(firstError(failure));
        } finally {
            setBusy(false);
        }
    }

    return (
        <form
            onSubmit={submit}
            className="flex h-full w-full flex-col items-start justify-center gap-space-xl p-margin"
        >
            <div className="flex w-full flex-col items-center gap-space-sm">
                <span className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary">
                    <MaterialIcon name="ac_unit" className="text-[30px] text-on-primary" />
                </span>
                <span className="font-headline-lg text-headline-lg text-on-surface">Anteraja Frozen</span>
                <span className="font-body-md text-body-md text-secondary">Masuk untuk melacak paket beku Anda.</span>
            </div>

            <div className="flex w-full flex-col gap-space-lg">
                <TextField
                    label="Nomor HP"
                    value={phone}
                    onChange={setPhone}
                    placeholder="0812xxxxxxx"
                    type="tel"
                    inputMode="tel"
                    autoComplete="tel"
                />

                <TextField
                    label="Password"
                    value={password}
                    onChange={setPassword}
                    placeholder="Password akun"
                    type="password"
                    autoComplete="current-password"
                />

                <ActionError message={error} />
            </div>

            <div className="flex w-full flex-col gap-space-sm">
                <button
                    type="submit"
                    disabled={busy}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-60"
                >
                    <MaterialIcon name="login" className="text-[20px]" />
                    {busy ? "Memproses…" : "Masuk"}
                </button>

                <button
                    type="button"
                    onClick={() => navigate("/client/register")}
                    className="font-body-sm text-body-sm text-secondary underline"
                >
                    Belum punya akun? Daftar
                </button>
            </div>
        </form>
    );
}
