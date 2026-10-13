import { Link } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";

interface MissingOrderProps {
    resi?: string;
}

/** Shown when a resi does not belong to the signed-in customer. */
export function MissingOrder({ resi }: MissingOrderProps) {
    return (
        <div className="flex flex-col items-center gap-space-sm rounded-xl border border-black/5 bg-surface-container-lowest p-space-lg text-center">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-surface-container text-secondary">
                <MaterialIcon name="search_off" className="text-[24px]" />
            </div>
            <span className="font-headline-sm text-headline-sm text-on-surface">Pesanan tidak ditemukan</span>
            <p className="font-body-sm text-body-sm text-secondary">
                Resi {resi ?? "-"} tidak terdaftar di akun ini. Pilih pesanan lain dari daftar.
            </p>
            <Link
                href="/client/orders"
                className="mt-space-xs flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-space-lg font-label-lg text-label-lg text-on-primary transition-colors active:bg-primary-container"
            >
                <MaterialIcon name="receipt_long" className="text-[18px]" />
                <span>Lihat Pesanan</span>
            </Link>
        </div>
    );
}
