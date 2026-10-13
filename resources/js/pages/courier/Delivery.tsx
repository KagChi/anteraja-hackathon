import { Link } from "@inertiajs/react";
import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ACTIVE_DELIVERY, ISSUE_SHEET_OPTIONS } from "@/data/courier";
import { CourierLayout } from "@/layouts";
import { cx } from "@/lib/cx";

export default function Delivery() {
    const [arrived, setArrived] = useState(false);
    const [sheetOpen, setSheetOpen] = useState(false);
    const delivery = ACTIVE_DELIVERY;

    return (
        <CourierLayout title="Detail Paket" backTo="/courier/tasks">
            <div className="flex w-full flex-col gap-space-md pt-2 pb-6">
                <section className="flex items-center justify-between rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                    <div className="flex flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Nomor Resi</span>
                        <span className="font-data-mono font-semibold text-headline-sm text-on-surface tracking-tight">
                            {delivery.tracking}
                        </span>
                    </div>
                    <span className="rounded-full bg-secondary-container px-2 py-0.5 font-label-sm text-label-sm text-on-secondary-container uppercase tracking-wide">
                        {delivery.service}
                    </span>
                </section>

                <section className="flex flex-col rounded-xl bg-surface-container-lowest p-space-md shadow-sm">
                    <span className="mb-space-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                        Progres Pengantaran
                    </span>
                    <div className="grid grid-cols-3 gap-space-xs">
                        <div className="flex flex-col items-center justify-center rounded-lg bg-surface-container-low px-1 py-2">
                            <div className="mb-1 flex h-6 w-6 items-center justify-center rounded-full bg-tertiary text-on-tertiary">
                                <MaterialIcon name="check" className="text-[16px]" />
                            </div>
                            <span className="font-label-sm text-label-sm text-secondary">Berangkat</span>
                        </div>
                        <div className="flex flex-col items-center justify-center rounded-lg bg-primary-fixed px-1 py-2">
                            <div className="mb-1 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-on-primary">
                                <MaterialIcon name="local_shipping" className="text-[16px]" />
                            </div>
                            <span className="text-center font-bold font-label-sm text-label-sm text-primary">
                                Sedang Diantar
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={() => setArrived(true)}
                            className={cx(
                                "flex flex-col items-center justify-center rounded-lg px-1 py-2 transition-all",
                                arrived ? "bg-primary-fixed" : "bg-surface-container-high hover:bg-surface-dim"
                            )}
                        >
                            <div
                                className={cx(
                                    "mb-1 flex h-6 w-6 items-center justify-center rounded-full",
                                    arrived ? "bg-primary text-on-primary" : "bg-secondary text-on-secondary"
                                )}
                            >
                                <MaterialIcon name="location_on" className="text-[16px]" />
                            </div>
                            <span
                                className={cx(
                                    "text-center font-bold font-label-sm text-label-sm",
                                    arrived ? "text-primary" : "text-secondary"
                                )}
                            >
                                Tiba di Lokasi
                            </span>
                        </button>
                    </div>
                </section>

                <section className="flex flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                    <div className="flex items-start justify-between gap-space-sm">
                        <div className="min-w-0">
                            <span className="font-body-sm text-body-sm text-secondary">Penerima</span>
                            <div className="truncate font-headline-sm text-headline-sm text-on-surface">
                                {delivery.recipient}
                            </div>
                            <div className="mt-0.5 font-body-md text-body-md text-on-surface">{delivery.phone}</div>
                        </div>
                        <a
                            href={delivery.phoneHref}
                            className="flex h-11 shrink-0 items-center gap-1.5 rounded-xl bg-surface-container-low px-4 font-label-md text-label-md text-primary transition-colors active:bg-secondary-container"
                        >
                            <MaterialIcon name="call" className="text-[20px]" />
                            Panggil
                        </a>
                    </div>

                    <div className="h-px w-full bg-surface-container-high" />

                    <div>
                        <div className="mb-1 flex items-center gap-1 font-body-sm text-body-sm text-secondary">
                            <MaterialIcon name="pin_drop" className="text-[16px]" />
                            Alamat Tujuan
                        </div>
                        <p className="font-body-md text-body-md text-on-surface leading-relaxed">{delivery.address}</p>
                    </div>

                    <div className="flex items-start gap-2 rounded-lg bg-error-container p-3 text-on-error-container">
                        <MaterialIcon name="ac_unit" className="shrink-0 text-[20px] text-error" />
                        <div className="font-body-sm leading-snug">
                            <strong className="mb-0.5 block font-label-sm uppercase tracking-wider">
                                Instruksi Khusus
                            </strong>
                            {delivery.instruction}
                        </div>
                    </div>
                </section>

                <section className="rounded-xl bg-surface-container-lowest p-2 shadow-sm">
                    <div
                        className="relative flex h-36 w-full items-end overflow-hidden rounded-lg bg-surface-container p-2"
                        data-location={delivery.mapLabel}
                    >
                        <svg
                            aria-hidden="true"
                            className="absolute inset-0 h-full w-full"
                            preserveAspectRatio="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <defs>
                                <pattern id="courier-map-grid" width="36" height="36" patternUnits="userSpaceOnUse">
                                    <path d="M 36 0 L 0 0 0 36" fill="none" stroke="#dbe3ef" strokeWidth="0.8" />
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="#eef2f9" />
                            <rect width="100%" height="100%" fill="url(#courier-map-grid)" />
                            <path d="M -40 108 Q 220 66 480 96 T 1040 118 L 1060 220 L -40 220 Z" fill="#dde6f2" />
                            <path
                                d="M -40 40 Q 260 14 560 44 T 1060 60 L 1060 96 Q 560 74 260 54 T -40 76 Z"
                                fill="#e6edf7"
                            />
                        </svg>
                        <div className="absolute top-[38%] left-1/2 -translate-x-1/2 -translate-y-full">
                            <MaterialIcon name="location_on" filled className="text-[30px] text-primary drop-shadow" />
                        </div>
                        <a
                            href={delivery.mapHref}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="relative z-10 flex h-10 w-full items-center justify-center gap-1.5 rounded-lg bg-surface font-label-md text-label-md text-on-surface shadow-sm transition-colors active:bg-surface-container-high"
                        >
                            <MaterialIcon name="navigation" className="text-[18px] text-primary" />
                            Buka Navigasi Rute
                        </a>
                    </div>
                </section>

                <div className="flex w-full flex-col gap-space-md">
                    <Link
                        href="/courier/proof"
                        className="flex h-[52px] w-full items-center justify-center gap-2 rounded-xl bg-primary font-headline-sm text-headline-sm text-on-primary shadow-md transition-transform active:scale-[0.99] active:bg-primary-container"
                    >
                        <MaterialIcon name="inventory" className="text-[22px]" />
                        Serahkan Paket
                    </Link>
                    <div className="flex justify-center pb-2">
                        <button
                            type="button"
                            onClick={() => setSheetOpen(true)}
                            className="px-4 py-2 font-body-md text-body-md text-secondary underline transition-colors hover:text-on-surface active:opacity-70"
                        >
                            Laporkan Kendala
                        </button>
                    </div>
                </div>
            </div>

            {sheetOpen ? (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-inverse-surface/40 p-margin">
                    <div className="flex w-full max-w-sm flex-col gap-space-md rounded-xl bg-surface p-space-lg shadow-xl">
                        <div className="flex items-center justify-between">
                            <h2 className="font-headline-sm text-headline-sm text-on-surface">Pilih Kendala</h2>
                            <button
                                type="button"
                                aria-label="Tutup"
                                onClick={() => setSheetOpen(false)}
                                className="flex h-8 w-8 items-center justify-center text-secondary"
                            >
                                <MaterialIcon name="close" className="text-[20px]" />
                            </button>
                        </div>
                        <div className="flex flex-col gap-2">
                            {ISSUE_SHEET_OPTIONS.map((option) => (
                                <Link
                                    key={option}
                                    href="/courier/issue"
                                    className="w-full rounded-lg bg-surface-container-low p-3 text-left font-body-md text-body-md text-on-surface transition-colors hover:bg-surface-container active:bg-secondary-container"
                                >
                                    {option}
                                </Link>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={() => setSheetOpen(false)}
                            className="h-11 w-full rounded-lg bg-surface-container-highest font-label-md text-label-md text-on-surface"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            ) : null}
        </CourierLayout>
    );
}
