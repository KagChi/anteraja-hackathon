import { type FormEvent, type ReactNode, useState } from "react";
import { CourierPickerDialog } from "@/components/CourierPickerDialog";
import { Dialog } from "@/components/Dialog";
import { MaterialIcon } from "@/components/MaterialIcon";
import {
    DECISION_REASON_DEFAULT,
    detailFromRow,
    ISSUE_CATEGORY_DECISIONS,
    ISSUE_DECISIONS,
    type IssueCategoryKey,
    type IssueDecisionKey,
    RESOLUTION_TONE,
    type ResolutionStatus,
    resolutionFor,
    resolveShipmentDetail,
    type ShipmentRow,
    STATUS_TONE,
    type StatusTone
} from "@/data/admin";
import { cx } from "@/lib/cx";
import { formatLongDate } from "@/lib/date";

interface ShipmentDetailModalProps {
    resi: string;
    /** Live row from the page list; falls back to the static dataset. */
    row?: ShipmentRow;
    onClose: () => void;
    /** Persists a courier change; screens that only display the row can omit it. */
    onCourierChange?: (resi: string, courier: string) => void;
    /** Records a dispatcher decision the same way. */
    onDecisionChange?: (resi: string, update: { decision: IssueDecisionKey; resolution: ResolutionStatus }) => void;
}

interface Proof {
    photo: string;
    caption: string;
    meta: string;
}

/** Status chip shared by the header and the kendala panel. */
function Pill({ tone, children }: { tone: StatusTone; children: ReactNode }) {
    return (
        <span
            className={cx(
                "inline-flex items-center gap-1.5 rounded-full bg-surface-container-low px-space-sm py-1 font-label-sm text-label-sm",
                STATUS_TONE[tone].text
            )}
        >
            <span className={cx("h-1.5 w-1.5 shrink-0 rounded-full", STATUS_TONE[tone].dot)} />
            {children}
        </span>
    );
}

/** One labelled row of the delivery summary. */
function Fact({ label, value, hint }: { label: string; value: ReactNode; hint?: ReactNode }) {
    return (
        <div className="flex flex-col gap-1 px-space-lg py-space-md">
            <span className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">{label}</span>
            <span className="font-body-md text-body-md text-on-surface">{value}</span>
            {hint ? <span className="font-body-sm text-body-sm text-secondary">{hint}</span> : null}
        </div>
    );
}

/** Proof thumbnail that opens the full photo. */
function ProofCard({ proof, onOpen }: { proof: Proof; onOpen: () => void }) {
    return (
        <button
            type="button"
            onClick={onOpen}
            aria-label={`Perbesar ${proof.caption}`}
            className="group overflow-hidden rounded-lg border border-outline-variant transition-colors hover:border-secondary"
        >
            <span className="relative block">
                <img src={proof.photo} alt={proof.caption} className="h-32 w-full object-cover" />
                <span className="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-md bg-inverse-surface/70 text-inverse-on-surface">
                    <MaterialIcon name="open_in_full" className="text-[16px]" />
                </span>
            </span>
            <span className="flex items-center justify-between gap-space-sm border-outline-variant border-t bg-surface-container-lowest px-space-md py-space-xs">
                <span className="font-label-sm text-label-sm text-on-surface">{proof.caption}</span>
                <span className="font-label-sm text-label-sm text-secondary">{proof.meta}</span>
            </span>
        </button>
    );
}

function CloseButton({ onClick }: { onClick: () => void }) {
    return (
        <button
            type="button"
            aria-label="Tutup"
            onClick={onClick}
            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
        >
            <MaterialIcon name="close" className="text-[20px]" />
        </button>
    );
}

function SecondaryButton({
    icon,
    label,
    onClick,
    disabled,
    title
}: {
    icon: string;
    label: string;
    onClick: () => void;
    disabled?: boolean;
    title?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={title}
            className="flex h-9 items-center gap-space-xs rounded-md border border-outline-variant px-space-md font-label-md text-label-md text-on-surface transition-colors hover:border-secondary hover:bg-surface-container disabled:opacity-40 disabled:hover:border-outline-variant disabled:hover:bg-transparent"
        >
            <MaterialIcon name={icon} className="text-[16px]" />
            <span>{label}</span>
        </button>
    );
}

interface DecisionDialogProps {
    resi: string;
    categoryKey: IssueCategoryKey;
    /** Decision already recorded for this kendala, when there is one. */
    current: IssueDecisionKey | null;
    onClose: () => void;
    onSave: (decision: IssueDecisionKey) => void;
}

/** Dispatcher decision form, stacked above the detail modal. */
function DecisionDialog({ resi, categoryKey, current, onClose, onSave }: DecisionDialogProps) {
    const allowed = ISSUE_CATEGORY_DECISIONS[categoryKey];
    const [decision, setDecision] = useState<IssueDecisionKey>(current ?? allowed[0]);
    const [reason, setReason] = useState(DECISION_REASON_DEFAULT);
    const [saved, setSaved] = useState(false);

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        onSave(decision);
        setSaved(true);
        window.setTimeout(() => setSaved(false), 3500);
    }

    return (
        <Dialog onClose={onClose} label={`Keputusan kendala ${resi}`} width="lg">
            <div className="flex items-start justify-between gap-space-md">
                <div className="flex flex-col">
                    <h2 className="font-headline-sm text-headline-sm text-on-surface">Keputusan</h2>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">{resi}</p>
                </div>
                <CloseButton onClick={onClose} />
            </div>

            <form onSubmit={handleSubmit} className="flex flex-col gap-space-md">
                <div className="flex flex-col gap-1.5">
                    {allowed.map((key) => {
                        const option = ISSUE_DECISIONS[key];
                        const active = decision === key;
                        return (
                            <label
                                key={key}
                                className={cx(
                                    "flex cursor-pointer items-start gap-space-sm rounded-lg border px-space-md py-space-sm transition-colors",
                                    active
                                        ? "border-primary bg-surface-container-low"
                                        : "border-outline-variant hover:border-secondary"
                                )}
                            >
                                <input
                                    type="radio"
                                    name="decision"
                                    value={key}
                                    checked={active}
                                    onChange={() => setDecision(key)}
                                    className="mt-1 accent-primary"
                                />
                                <span className="flex flex-col">
                                    <span className="font-body-md font-medium text-body-md text-on-surface">
                                        {option.label}
                                    </span>
                                    <span className="font-body-sm text-body-sm text-secondary">{option.hint}</span>
                                </span>
                            </label>
                        );
                    })}
                </div>
                <label className="flex flex-col gap-1" htmlFor="decision-reason">
                    <span className="font-body-sm text-body-sm text-secondary">Alasan keputusan</span>
                    <textarea
                        id="decision-reason"
                        rows={3}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        placeholder="Tuliskan alasan dan instruksi untuk kurir..."
                        className="w-full resize-none rounded-lg border border-outline-variant bg-surface-container-lowest p-space-md font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
                    />
                </label>
                <button
                    type="submit"
                    className="flex h-10 w-full items-center justify-center gap-space-sm rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                >
                    <MaterialIcon name="check" className="text-[18px]" />
                    <span>Simpan Keputusan</span>
                </button>
                {saved ? (
                    <div className="rounded-lg bg-tertiary-fixed p-space-sm text-center font-body-sm text-body-sm text-on-tertiary-fixed">
                        Keputusan tersimpan dan tersinkron ke aplikasi kurir.
                    </div>
                ) : null}
            </form>
        </Dialog>
    );
}

/**
 * Detail for one shipment, opened from the shipment and report tables. The
 * summary and the kendala report sit side by side with the actions along the
 * bottom; moving the courier, the proof photo and the decision each open as a
 * dialog stacked on top of this one.
 */
export function ShipmentDetailModal({
    resi,
    row,
    onClose,
    onCourierChange,
    onDecisionChange
}: ShipmentDetailModalProps) {
    const detail = row ? detailFromRow(row) : resolveShipmentDetail(resi);
    const [courier, setCourier] = useState(detail?.courier ?? null);
    const [decision, setDecision] = useState<IssueDecisionKey | null>(detail?.issue?.decision ?? null);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [proofOpen, setProofOpen] = useState(false);
    const [decisionOpen, setDecisionOpen] = useState(false);

    if (!detail) {
        return (
            <Dialog onClose={onClose} label="Detail pengiriman">
                <div className="flex flex-col items-center gap-space-sm py-space-md text-center">
                    <MaterialIcon name="search_off" className="text-[24px] text-secondary" />
                    <span className="font-headline-sm text-headline-sm text-on-surface">
                        Pengiriman tidak ditemukan
                    </span>
                    <p className="font-body-sm text-body-sm text-secondary">
                        Resi {resi} tidak ada di daftar pengiriman.
                    </p>
                    <button
                        type="button"
                        onClick={onClose}
                        className="mt-space-xs flex h-9 items-center rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        Tutup
                    </button>
                </div>
            </Dialog>
        );
    }

    const proof = detail.proof;
    const issue = detail.issue;
    const resolution = resolutionFor(decision);

    function moveCourier(name: string) {
        setCourier(name);
        setPickerOpen(false);
        onCourierChange?.(resi, name);
    }

    function saveDecision(next: IssueDecisionKey) {
        setDecision(next);
        onDecisionChange?.(resi, { decision: next, resolution: resolutionFor(next) });
    }

    return (
        <>
            <Dialog
                onClose={onClose}
                label={`Detail pengiriman ${resi}`}
                width="4xl"
                className="max-h-[calc(100dvh-3rem)] overflow-y-auto"
            >
                <div className="flex items-start gap-space-md">
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-space-sm">
                            <span className="font-data-mono font-semibold text-headline-sm text-on-surface">
                                {resi}
                            </span>
                            <Pill tone={detail.tone}>{detail.status}</Pill>
                        </div>
                        <p className="truncate font-body-sm text-body-sm text-secondary">
                            {detail.buyer} · {detail.address}
                        </p>
                    </div>
                    <CloseButton onClick={onClose} />
                </div>

                <div className="grid grid-cols-1 gap-space-lg lg:grid-cols-2">
                    <section className="flex flex-col gap-space-md">
                        <h3 className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                            Pengiriman
                        </h3>
                        <div className="divide-y divide-surface-container rounded-lg border border-outline-variant">
                            <Fact label="Pembeli" value={detail.buyer} hint={detail.address} />
                            <Fact
                                label="Kurir"
                                value={courier ?? "Belum Ditugaskan"}
                                hint={courier ? detail.box : "Tugaskan kurir untuk mulai mengantar"}
                            />
                            <Fact label="Jadwal" value={detail.schedule} hint={formatLongDate(detail.scheduledDate)} />
                            <Fact label="Suhu target" value={`${detail.temperature} s.d. -25°C`} />
                        </div>
                    </section>

                    <section className="flex flex-col gap-space-md">
                        <h3 className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                            {issue ? "Laporan kendala" : "Bukti pengiriman"}
                        </h3>
                        {issue ? (
                            <div className="flex flex-col gap-space-md rounded-lg border border-outline-variant bg-surface-container-low p-space-lg">
                                <div className="flex flex-wrap items-center gap-space-xs">
                                    <MaterialIcon name="report_problem" className="text-[18px] text-primary" />
                                    <span className="font-label-md font-semibold text-label-md text-on-surface">
                                        {issue.label}
                                    </span>
                                    <span className="ml-auto">
                                        <Pill tone={RESOLUTION_TONE[resolution]}>{resolution}</Pill>
                                    </span>
                                </div>
                                <span className="font-body-sm text-body-sm text-secondary">
                                    Dilaporkan {formatLongDate(detail.scheduledDate)}, {issue.reportedAt}
                                </span>
                                <p className="border-primary/30 border-l-2 pl-space-md font-body-md text-body-md text-on-surface italic leading-relaxed">
                                    “{issue.note}”
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {issue.condition.map((item) => (
                                        <span
                                            key={item}
                                            className="rounded-full border border-outline-variant bg-surface-container-lowest px-2 py-0.5 font-label-sm text-label-sm text-secondary"
                                        >
                                            {item}
                                        </span>
                                    ))}
                                </div>
                                {proof ? <ProofCard proof={proof} onOpen={() => setProofOpen(true)} /> : null}
                                <div className="flex flex-wrap items-center justify-between gap-space-sm border-outline-variant border-t pt-space-md">
                                    <span className="font-label-sm text-label-sm text-secondary">Keputusan</span>
                                    <span className="font-body-sm text-body-sm text-on-surface">
                                        {decision ? ISSUE_DECISIONS[decision].label : "Belum diputuskan"}
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-space-md rounded-lg border border-outline-variant bg-surface-container-low p-space-lg">
                                <div className="flex items-center gap-space-xs">
                                    <MaterialIcon name="verified" className="text-[18px] text-tertiary" />
                                    <span className="font-label-md font-semibold text-label-md text-on-surface">
                                        Tidak ada kendala dilaporkan
                                    </span>
                                </div>
                                <p className="font-body-sm text-body-sm text-secondary">
                                    {proof
                                        ? "Bukti serah terima tersedia untuk paket ini."
                                        : "Bukti penyerahan muncul setelah kurir mengunggahnya di lokasi."}
                                </p>
                                {proof ? <ProofCard proof={proof} onOpen={() => setProofOpen(true)} /> : null}
                            </div>
                        )}
                    </section>
                </div>

                <div className="flex flex-wrap items-center gap-space-sm border-outline-variant border-t pt-space-md">
                    <SecondaryButton
                        icon="swap_horiz"
                        label={courier ? "Pindahkan kurir" : "Tugaskan kurir"}
                        onClick={() => setPickerOpen(true)}
                    />
                    <SecondaryButton
                        icon="image"
                        label="Lihat bukti"
                        onClick={() => setProofOpen(true)}
                        disabled={!proof}
                        title={proof ? undefined : "Belum ada bukti"}
                    />
                    <div className="ml-auto flex items-center gap-space-sm">
                        {issue ? (
                            <button
                                type="button"
                                onClick={() => setDecisionOpen(true)}
                                className="flex h-9 items-center gap-space-xs rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                            >
                                <MaterialIcon name="gavel" className="text-[16px]" />
                                <span>Buat keputusan</span>
                            </button>
                        ) : null}
                        <button
                            type="button"
                            onClick={onClose}
                            className="h-9 rounded-md px-space-md font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container hover:text-on-surface"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </Dialog>

            <CourierPickerDialog
                open={pickerOpen}
                current={courier}
                subtitle={`${detail.buyer} (${detail.address})`}
                onClose={() => setPickerOpen(false)}
                onConfirm={moveCourier}
            />

            {proofOpen && proof ? (
                <Dialog onClose={() => setProofOpen(false)} label={`Bukti pengiriman ${resi}`} width="lg">
                    <div className="flex items-start justify-between gap-space-md">
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Bukti pengiriman</h2>
                        <CloseButton onClick={() => setProofOpen(false)} />
                    </div>
                    <div className="overflow-hidden rounded-lg border border-outline-variant">
                        <img src={proof.photo} alt={proof.caption} className="h-72 w-full object-cover" />
                        <div className="flex items-center justify-between border-outline-variant border-t bg-surface-container-low px-space-md py-space-sm">
                            <span className="font-body-sm text-body-sm text-secondary">{proof.caption}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{proof.meta}</span>
                        </div>
                    </div>
                </Dialog>
            ) : null}

            {decisionOpen && issue ? (
                <DecisionDialog
                    resi={resi}
                    categoryKey={issue.categoryKey}
                    current={decision}
                    onClose={() => setDecisionOpen(false)}
                    onSave={saveDecision}
                />
            ) : null}
        </>
    );
}
