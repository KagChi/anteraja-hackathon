import { type FormEvent, useEffect, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ActionError } from "../../components/ActionError";
import { SelectField, TextAreaField, TextField } from "../../components/Field";
import { clientApi, firstError } from "../../lib/api";
import { useRouter } from "../../lib/router";
import { isSignedIn, nextPath, setToken } from "../../lib/token";

const ADDRESS_TYPES = [
    { value: "RESIDENTIAL", label: "Rumah / Residensial" },
    { value: "COMMERCIAL", label: "Kantor / Komersial" }
];

/**
 * Self sign-up.
 *
 * The customer shops before signing in, so registration is the moment the order
 * can finally be placed: the account is created, the token stored, and the
 * visitor continues straight to checkout.
 */
export default function Register({ query }: { query: URLSearchParams }) {
    const { navigate } = useRouter();
    const target = nextPath(query, "client", "/client/checkout");
    const [name, setName] = useState("");
    const [phone, setPhone] = useState("");
    const [password, setPassword] = useState("");
    const [passwordConfirmation, setPasswordConfirmation] = useState("");
    const [address, setAddress] = useState("");
    const [area, setArea] = useState("");
    const [addressType, setAddressType] = useState("RESIDENTIAL");
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (isSignedIn("client")) {
            navigate("/client/checkout", { replace: true });
        }
    }, [navigate]);

    async function submit(event: FormEvent) {
        event.preventDefault();

        if (busy) {
            return;
        }

        setError(null);

        if (name.trim() === "" || phone.trim() === "" || address.trim() === "") {
            setError("Nama, nomor HP, dan alamat wajib diisi.");
            return;
        }

        if (password.length < 8) {
            setError("Password minimal 8 karakter.");
            return;
        }

        if (password !== passwordConfirmation) {
            setError("Konfirmasi password tidak sama.");
            return;
        }

        setBusy(true);

        try {
            const response = await clientApi.auth.register({
                name: name.trim(),
                phone: phone.trim(),
                password,
                password_confirmation: passwordConfirmation,
                address: address.trim(),
                area: area.trim() === "" ? null : area.trim(),
                address_type: addressType
            });

            const token = response.data.token;

            if (token === "") {
                setError("Pendaftaran gagal: token tidak diterima.");
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
        <form onSubmit={submit} className="flex w-full flex-col gap-space-lg p-margin pb-space-xl">
            <div className="flex w-full flex-col items-center gap-space-sm py-space-lg">
                <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary">
                    <MaterialIcon name="person_add" className="text-[26px] text-on-primary" />
                </span>
                <span className="font-headline-lg text-headline-lg text-on-surface">Daftar Anteraja Frozen</span>
                <span className="text-center font-body-md text-body-md text-secondary">
                    Akun dipakai untuk memesan, mengonfirmasi jadwal, dan melacak kiriman beku Anda.
                </span>
            </div>

            <section className="flex w-full flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-lg">
                <TextField
                    label="Nama lengkap"
                    value={name}
                    onChange={setName}
                    placeholder="Nama penerima"
                    autoComplete="name"
                />
                <TextField
                    label="Nomor HP"
                    value={phone}
                    onChange={setPhone}
                    placeholder="0812xxxxxxx"
                    type="tel"
                    inputMode="tel"
                    autoComplete="tel"
                    supporting="Nomor ini menjadi ID akun Anda"
                />
                <TextField
                    label="Password"
                    value={password}
                    onChange={setPassword}
                    placeholder="Minimal 8 karakter"
                    type="password"
                    autoComplete="new-password"
                />
                <TextField
                    label="Ulangi password"
                    value={passwordConfirmation}
                    onChange={setPasswordConfirmation}
                    placeholder="Ketik ulang password"
                    type="password"
                    autoComplete="new-password"
                />
            </section>

            <section className="flex w-full flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Alamat pengantaran</span>
                <TextAreaField
                    label="Alamat lengkap"
                    value={address}
                    onChange={setAddress}
                    placeholder="Jalan, nomor rumah, kelurahan"
                />
                <TextField
                    label="Area / kota"
                    value={area}
                    onChange={setArea}
                    placeholder="Contoh: Tebet, Jakarta Selatan"
                />
                <SelectField
                    label="Jenis alamat"
                    value={addressType}
                    onChange={setAddressType}
                    options={ADDRESS_TYPES}
                />
            </section>

            <ActionError message={error} />

            <button
                type="submit"
                disabled={busy}
                className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-60"
            >
                <MaterialIcon name="check" className="text-[20px]" />
                {busy ? "Mendaftarkan…" : "Daftar dan Lanjut Checkout"}
            </button>

            <div className="flex w-full flex-col items-center gap-space-sm">
                <span className="font-body-sm text-body-sm text-secondary">Sudah punya akun?</span>
                <button
                    type="button"
                    onClick={() => navigate("/client/login")}
                    className="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
                >
                    <MaterialIcon name="login" className="text-[18px]" />
                    Masuk
                </button>
            </div>
        </form>
    );
}
