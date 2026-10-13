import { type FormEvent, useEffect, useMemo, useState } from "react";
import { Dialog } from "@/components/Dialog";
import { MaterialIcon } from "@/components/MaterialIcon";
import { COURIERS } from "@/data/admin";
import { cx } from "@/lib/cx";

interface CourierPickerDialogProps {
    open: boolean;
    /** Courier already on the shipment, offered as the starting selection. */
    current: string | null;
    /** Shipment context shown under the title, e.g. resi and buyer. */
    subtitle?: string;
    onClose: () => void;
    onConfirm: (courier: string) => void;
}

/**
 * Searchable courier list shared by the assignment action on the shipment table
 * and the "move courier" action on the detail modal.
 */
export function CourierPickerDialog({ open, current, subtitle, onClose, onConfirm }: CourierPickerDialogProps) {
    const [selected, setSelected] = useState("");
    const [search, setSearch] = useState("");

    useEffect(() => {
        if (!open) return;
        setSelected(current ?? "");
        setSearch("");
    }, [open, current]);

    const matches = useMemo(() => {
        const needle = search.trim().toLowerCase();
        if (!needle) return COURIERS;
        return COURIERS.filter((option) => option.label.toLowerCase().includes(needle));
    }, [search]);

    if (!open) return null;

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!selected) return;
        onConfirm(selected);
    }

    return (
        <Dialog onClose={onClose} label="Tugaskan Kurir">
            <form onSubmit={handleSubmit} className="flex flex-col gap-space-md">
                <div className="flex items-start justify-between gap-space-md">
                    <div className="flex flex-col">
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Tugaskan Kurir</h2>
                        {subtitle ? (
                            <p className="mt-0.5 font-body-sm text-body-sm text-secondary">{subtitle}</p>
                        ) : null}
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup"
                        onClick={onClose}
                        className="flex h-8 w-8 shrink-0 items-center justify-center text-secondary transition-colors hover:text-on-surface"
                    >
                        <MaterialIcon name="close" className="text-[20px]" />
                    </button>
                </div>

                <div className="flex flex-col gap-space-sm">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Pilih kurir *</span>
                    <div className="relative">
                        <MaterialIcon
                            name="search"
                            className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[18px] text-secondary"
                        />
                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari nama kurir..."
                            autoComplete="off"
                            aria-label="Cari nama kurir"
                            className="h-9 w-full rounded-md border border-outline-variant bg-surface-container-low pr-space-md pl-9 font-body-sm text-body-sm text-on-surface transition-colors placeholder:text-secondary hover:border-secondary focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                        />
                    </div>
                    <div className="flex max-h-56 flex-col overflow-y-auto rounded-lg border border-outline-variant">
                        {matches.map((option) => {
                            const active = selected === option.value;
                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => setSelected(option.value)}
                                    className={cx(
                                        "flex h-10 shrink-0 items-center justify-between px-space-md text-left font-label-md text-label-md transition-colors",
                                        active
                                            ? "bg-surface-container-low font-semibold text-primary"
                                            : "text-on-surface hover:bg-surface-container"
                                    )}
                                >
                                    <span>{option.label}</span>
                                    {active ? <MaterialIcon name="check" className="text-[18px] text-primary" /> : null}
                                </button>
                            );
                        })}
                        {matches.length === 0 ? (
                            <span className="px-space-md py-space-md font-body-sm text-body-sm text-secondary">
                                Kurir tidak ditemukan.
                            </span>
                        ) : null}
                    </div>
                </div>

                <p className="font-body-sm text-body-sm text-secondary">
                    {selected
                        ? `Kurir terpilih: ${selected}. Notifikasi tugas dikirim begitu shipment disimpan.`
                        : "Pilih kurir dari daftar untuk melanjutkan."}
                </p>

                <div className="flex items-center justify-end gap-space-sm pt-space-xs">
                    <button
                        type="button"
                        onClick={onClose}
                        className="h-9 rounded-md px-space-md font-label-md text-label-md text-secondary transition-colors hover:text-on-surface"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={!selected}
                        className="flex h-9 items-center gap-space-xs rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-40"
                    >
                        <MaterialIcon name="check" className="text-[16px]" />
                        <span>Tugaskan Kurir</span>
                    </button>
                </div>
            </form>
        </Dialog>
    );
}
