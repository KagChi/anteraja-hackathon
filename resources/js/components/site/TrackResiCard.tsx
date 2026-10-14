import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { cx } from "@/lib/cx";

interface TrackResiCardProps {
    className?: string;
}

const RESI_PATTERN = /^[A-Za-z]{2}-?[A-Za-z]{0,3}-?\d{6,12}$|^\d{10,14}$/;

/**
 * The landing page's "Lacak Pesananmu" panel.
 *
 * Live tracking needs a signed-in customer (the API scopes a parcel to its
 * buyer), so this form validates the resi and then hands the visitor to the
 * app or the customer portal instead of pretending to query the fleet.
 */
export function TrackResiCard({ className }: TrackResiCardProps) {
    const [resi, setResi] = useState("");
    const [error, setError] = useState<string | null>(null);
    const [submitted, setSubmitted] = useState<string | null>(null);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const value = resi.trim().toUpperCase();

        if (value.length < 8) {
            setError("Nomor resi minimal 8 karakter, contoh AJ-FRZ-0012345.");
            setSubmitted(null);
            return;
        }

        if (!RESI_PATTERN.test(value)) {
            setError("Format nomor resi belum sesuai. Periksa kembali nomor di bukti pengiriman.");
            setSubmitted(null);
            return;
        }

        setError(null);
        setSubmitted(value);
    }

    return (
        <form
            onSubmit={submit}
            className={cx(
                "flex flex-col gap-3 rounded-2xl border border-black/5 bg-surface-container-lowest p-5 shadow-xl sm:p-6",
                className
            )}
        >
            <div className="flex items-center gap-2">
                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <MaterialIcon name="pin_drop" className="text-[20px]" />
                </span>
                <div className="flex flex-col">
                    <span className="font-headline-sm text-headline-sm text-on-surface">Lacak Pesananmu</span>
                    <span className="font-body-sm text-body-sm text-secondary">Masukkan nomor resi pengiriman</span>
                </div>
            </div>

            <label className="flex flex-col gap-1.5">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Nomor resi</span>
                <div className="flex gap-2">
                    <input
                        value={resi}
                        onChange={(event) => setResi(event.target.value)}
                        inputMode="text"
                        autoComplete="off"
                        placeholder="AJ-FRZ-0012345"
                        aria-invalid={error !== null}
                        className="h-11 w-full rounded-xl border border-outline-variant bg-surface px-3 font-data-mono text-data-mono text-on-surface outline-none transition-colors placeholder:text-secondary/60 focus:border-primary"
                    />
                    <button
                        type="submit"
                        className="h-11 shrink-0 rounded-xl bg-primary px-5 font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        Lacak
                    </button>
                </div>
            </label>

            {error !== null ? (
                <p className="flex items-start gap-2 rounded-lg bg-error-container px-3 py-2 font-body-sm text-body-sm text-on-error-container">
                    <MaterialIcon name="error_outline" className="text-[16px]" />
                    {error}
                </p>
            ) : null}

            {submitted !== null ? (
                <div className="flex flex-col gap-3 rounded-xl bg-surface-container-low p-4">
                    <p className="flex items-start gap-2 font-body-sm text-body-sm text-on-surface">
                        <MaterialIcon name="verified" className="text-[16px] text-tertiary" />
                        <span>
                            Resi <span className="font-bold">{submitted}</span> siap dilacak. Status pengiriman dan
                            jadwal antar tampil lengkap di aplikasi pelanggan.
                        </span>
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <a
                            href="#unduh"
                            className="inline-flex items-center gap-1.5 rounded-full bg-primary px-3.5 py-2 font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                        >
                            <MaterialIcon name="android" className="text-[16px]" />
                            Unduh aplikasi
                        </a>
                        <a
                            href="/client/orders"
                            className="inline-flex items-center gap-1.5 rounded-full border border-outline-variant px-3.5 py-2 font-label-md text-label-md text-on-surface transition-colors hover:border-primary hover:text-primary"
                        >
                            <MaterialIcon name="open_in_new" className="text-[16px]" />
                            Buka portal pelanggan
                        </a>
                    </div>
                </div>
            ) : null}
        </form>
    );
}
