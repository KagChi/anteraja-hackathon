import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import type { ScreenProps } from "../../App";
import { rupiah } from "../../lib/format";
import { readReceipt } from "../../lib/receipt";
import { useRouter } from "../../lib/router";

/** The confirmation the customer sees once their parcel exists. */
export default function OrderSuccess({ query }: ScreenProps) {
    const { navigate } = useRouter();
    const resi = query.get("resi") ?? "";
    const [receipt] = useState(() => (resi === "" ? null : readReceipt(resi)));

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin pb-space-xl">
            <section className="flex w-full flex-col items-center gap-space-sm rounded-2xl bg-primary p-space-xl">
                <span className="flex h-14 w-14 items-center justify-center rounded-full bg-surface">
                    <MaterialIcon name="verified" className="text-[26px] text-primary" />
                </span>
                <span className="font-headline-md text-headline-md text-on-primary">Pesanan berhasil dibuat</span>
                <span className="text-center font-body-sm text-body-sm text-on-primary">
                    Hub mengonfirmasi jadwal ke penerima lewat WhatsApp, lalu kurir berangkat mengantar paket.
                </span>
            </section>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between gap-2">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Nomor resi</span>
                    <span className="font-headline-sm text-headline-sm text-on-surface select-all">
                        {resi === "" ? "-" : resi}
                    </span>
                </div>

                {receipt === null ? null : (
                    <>
                        <hr className="border-outline-variant" />

                        <Row label="Status" value={receipt.status_label} />
                        <Row label="Layanan" value={receipt.service_label} />
                        <Row label="Jadwal" value={receipt.scheduled_label} alignRight />
                        <Row label="Penerima" value={receipt.recipient.name ?? "-"} alignRight />
                        <div className="flex w-full items-center justify-between gap-2">
                            <span className="font-body-sm text-body-sm text-secondary">Total produk</span>
                            <span className="font-headline-sm text-headline-sm text-primary">
                                {rupiah(receipt.total_idr)}
                            </span>
                        </div>
                    </>
                )}
            </section>

            {receipt === null || receipt.items.length === 0 ? null : (
                <section className="flex w-full flex-col gap-2 rounded-xl bg-surface-container-lowest p-space-lg">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Isi paket</span>

                    {receipt.items.map((item) => (
                        <div key={item.name} className="flex w-full items-center justify-between gap-2">
                            <span className="flex-1 font-body-md text-body-md text-on-surface">
                                {item.quantity}× {item.name}
                            </span>
                            <span className="font-body-md text-body-md text-secondary">
                                {rupiah(item.line_total_idr)}
                            </span>
                        </div>
                    ))}
                </section>
            )}

            <section className="flex w-full flex-col gap-space-sm rounded-xl bg-surface-container-low p-space-lg">
                <span className="flex items-center gap-space-sm">
                    <MaterialIcon name="info" className="text-[18px] text-tertiary" />
                    <span className="font-label-sm text-label-sm text-tertiary uppercase">Langkah berikutnya</span>
                </span>
                <span className="font-body-sm text-body-sm leading-snug text-secondary">
                    Buka tab Pesanan untuk melacak status paket. Penerima yang mengonfirmasi jadwal kedatangan lewat
                    WhatsApp.
                </span>
            </section>

            <button
                type="button"
                onClick={() => navigate("/client/orders", { replace: true })}
                className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary"
            >
                <MaterialIcon name="list" className="text-[20px]" />
                Lihat Pesanan
            </button>
            <button
                type="button"
                onClick={() => navigate("/client/catalog", { replace: true })}
                className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
            >
                <MaterialIcon name="storefront" className="text-[20px]" />
                Belanja Lagi
            </button>
        </div>
    );
}

function Row({ label, value, alignRight = false }: { label: string; value: string; alignRight?: boolean }) {
    return (
        <div className="flex w-full items-center justify-between gap-2">
            <span className="font-body-sm text-body-sm text-secondary">{label}</span>
            <span className={`font-body-md text-body-md text-on-surface ${alignRight ? "flex-1 text-right" : ""}`}>
                {value}
            </span>
        </div>
    );
}
