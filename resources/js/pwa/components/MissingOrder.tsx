import { MaterialIcon } from "@/components/MaterialIcon";
import { Link } from "../lib/router";

/**
 * Shown inside a pushed screen when the resi does not belong to the signed-in
 * customer, so an unknown link cannot render an empty screen.
 */
export function MissingOrder({ resi }: { resi: string }) {
    return (
        <section className="flex w-full flex-col items-center gap-space-md rounded-xl bg-surface-container-lowest p-space-xl">
            <span className="flex h-12 w-12 items-center justify-center rounded-full bg-surface-container">
                <MaterialIcon name="search_off" className="text-[24px] text-secondary" />
            </span>
            <span className="font-headline-md text-headline-md text-on-surface">Pesanan tidak ditemukan</span>
            <p className="text-center font-body-sm text-body-sm leading-relaxed text-secondary">
                Resi {resi !== "" ? resi : "-"} tidak terdaftar di akun ini. Pilih pesanan lain dari daftar.
            </p>
            <Link
                href="/client/orders"
                className="inline-flex h-11 items-center justify-center gap-1.5 rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary"
            >
                <MaterialIcon name="receipt_long" className="text-[18px]" />
                Lihat Pesanan
            </Link>
        </section>
    );
}
