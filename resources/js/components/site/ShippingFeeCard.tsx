import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { FEE_SERVICES, FEE_ZONES } from "@/data/landing";
import { cx } from "@/lib/cx";

interface ShippingFeeCardProps {
    className?: string;
}

interface Estimate {
    zoneLabel: string;
    serviceLabel: string;
    weight: number;
    total: number;
    days: string;
}

function rupiah(value: number): string {
    return "Rp " + value.toLocaleString("id-ID");
}

/**
 * The landing page's "Cek Ongkir" panel.
 *
 * The estimate is a published rate card, not a quote: the real price depends on
 * the basket and the destination the customer picks in the app, so the panel
 * says so and hands over.
 */
export function ShippingFeeCard({ className }: ShippingFeeCardProps) {
    const [zone, setZone] = useState(FEE_ZONES[0].value);
    const [service, setService] = useState(FEE_SERVICES[0].value);
    const [weight, setWeight] = useState("1");
    const [error, setError] = useState<string | null>(null);
    const [estimate, setEstimate] = useState<Estimate | null>(null);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const parsed = Number.parseFloat(weight.replace(",", "."));

        if (!Number.isFinite(parsed) || parsed <= 0 || parsed > 50) {
            setError("Berat harus antara 0,1 kg sampai 50 kg.");
            setEstimate(null);
            return;
        }

        const selectedZone = FEE_ZONES.find((item) => item.value === zone) ?? FEE_ZONES[0];
        const selectedService = FEE_SERVICES.find((item) => item.value === service) ?? FEE_SERVICES[0];
        const billable = Math.max(1, Math.ceil(parsed));
        const total = Math.round(billable * selectedZone.perKg * selectedService.multiplier);

        setError(null);
        setEstimate({
            zoneLabel: selectedZone.label,
            serviceLabel: selectedService.label,
            weight: parsed,
            total,
            days:
                selectedZone.minDays === selectedZone.maxDays
                    ? selectedZone.minDays + " hari"
                    : selectedZone.minDays + " - " + selectedZone.maxDays + " hari"
        });
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
                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary-container text-on-secondary-container">
                    <MaterialIcon name="local_shipping" className="text-[20px]" />
                </span>
                <div className="flex flex-col">
                    <span className="font-headline-sm text-headline-sm text-on-surface">Cek Ongkir</span>
                    <span className="font-body-sm text-body-sm text-secondary">Estimasi tarif kiriman beku</span>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <label className="flex flex-col gap-1.5">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Kota tujuan</span>
                    <select
                        value={zone}
                        onChange={(event) => setZone(event.target.value)}
                        className="h-11 rounded-xl border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface outline-none focus:border-primary"
                    >
                        {FEE_ZONES.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="flex flex-col gap-1.5">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Layanan</span>
                    <select
                        value={service}
                        onChange={(event) => setService(event.target.value)}
                        className="h-11 rounded-xl border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface outline-none focus:border-primary"
                    >
                        {FEE_SERVICES.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            <label className="flex flex-col gap-1.5">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Berat (kg)</span>
                <div className="flex gap-2">
                    <input
                        value={weight}
                        onChange={(event) => setWeight(event.target.value)}
                        inputMode="decimal"
                        placeholder="1"
                        className="h-11 w-full rounded-xl border border-outline-variant bg-surface px-3 font-data-mono text-data-mono text-on-surface outline-none transition-colors focus:border-primary"
                    />
                    <button
                        type="submit"
                        className="h-11 shrink-0 rounded-xl bg-on-surface px-5 font-label-md text-label-md text-white transition-opacity hover:opacity-90"
                    >
                        Hitung
                    </button>
                </div>
            </label>

            {error !== null ? (
                <p className="flex items-start gap-2 rounded-lg bg-error-container px-3 py-2 font-body-sm text-body-sm text-on-error-container">
                    <MaterialIcon name="error_outline" className="text-[16px]" />
                    {error}
                </p>
            ) : null}

            {estimate !== null ? (
                <div className="flex flex-col gap-2 rounded-xl bg-surface-container-low p-4">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">
                        Estimasi {estimate.serviceLabel} • {estimate.zoneLabel}
                    </span>
                    <span className="font-headline-lg text-headline-lg text-on-surface">{rupiah(estimate.total)}</span>
                    <span className="font-body-sm text-body-sm text-secondary">
                        Berat {estimate.weight} kg (dibulatkan per kilogram) • estimasi tiba {estimate.days}
                    </span>
                    <span className="font-body-sm text-body-sm text-secondary">
                        Ini tarif acuan. Total akhir dihitung dari isi keranjang dan alamat Anda di aplikasi.
                    </span>
                    <a
                        href="#unduh"
                        className="mt-1 inline-flex w-fit items-center gap-1.5 rounded-full bg-primary px-3.5 py-2 font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        <MaterialIcon name="shopping_bag" className="text-[16px]" />
                        Pesan lewat aplikasi
                    </a>
                </div>
            ) : null}
        </form>
    );
}
