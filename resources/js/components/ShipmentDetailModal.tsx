import { type FormEvent, type ReactNode, useCallback, useEffect, useState } from "react";
import { CourierPickerDialog } from "@/components/CourierPickerDialog";
import { Dialog } from "@/components/Dialog";
import { LeafletMap, type MapCircle, type MapMarker } from "@/components/LeafletMap";
import { MaterialIcon } from "@/components/MaterialIcon";
import {
    DECISION_REASON_DEFAULT,
    DELIVERY_SLOTS,
    ISSUE_DECISIONS,
    type IssueDecisionKey,
    STATUS_TONE
} from "@/data/admin";
import {
    ApiError,
    api,
    type CourierOption,
    type ExceptionDetailResponse,
    type ShipmentDetailResponse,
    type Tone
} from "@/lib/api";
import { cx } from "@/lib/cx";
import { todayIso } from "@/lib/date";

interface ShipmentDetailModalProps {
    resi: string;
    onClose: () => void;
    /** Ran after a change the underlying list should reflect. */
    onChanged?: () => void;
}

/**
 * Statuses the assignment endpoint still accepts a courier for; mirrors
 * App\Actions\Packages\AssignCourier::ASSIGNABLE_STATUSES.
 */
const ASSIGNABLE_STATUSES = ["CREATED", "CONFIRMED", "COURIER_ASSIGNED", "CHECKED", "ISSUE_REPORTED"];

/** Status chip shared by the header and the kendala panel. */
function Pill({ tone, children }: { tone: Tone; children: ReactNode }) {
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
function ProofCard({
    photo,
    caption,
    meta,
    onOpen
}: {
    photo: string;
    caption: string;
    meta: string;
    onOpen: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onOpen}
            aria-label={`Perbesar ${caption}`}
            className="group overflow-hidden rounded-lg border border-outline-variant transition-colors hover:border-secondary"
        >
            <span className="relative block">
                <img src={photo} alt={caption} className="h-32 w-full object-cover" />
                <span className="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-md bg-inverse-surface/70 text-inverse-on-surface">
                    <MaterialIcon name="open_in_full" className="text-[16px]" />
                </span>
            </span>
            <span className="flex items-center justify-between gap-space-sm border-outline-variant border-t bg-surface-container-lowest px-space-md py-space-xs">
                <span className="font-label-sm text-label-sm text-on-surface">{caption}</span>
                <span className="font-label-sm text-label-sm text-secondary">{meta}</span>
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

/** Inline failure notice shared by the modal and its stacked dialogs. */
function ErrorNotice({ message }: { message: string }) {
    return (
        <div
            role="alert"
            className="flex items-start gap-space-xs rounded-lg bg-error-container p-space-sm font-body-sm text-body-sm text-on-error-container"
        >
            <MaterialIcon name="error" className="text-[18px]" />
            <span>{message}</span>
        </div>
    );
}

function decisionLabel(value: string | null): string {
    if (value === null) return "Belum diputuskan";
    return ISSUE_DECISIONS[value as IssueDecisionKey]?.label ?? value;
}

interface DecisionDialogProps {
    resi: string;
    issue: ExceptionDetailResponse;
    onClose: () => void;
    onSaved: () => Promise<void>;
}

/** Dispatcher decision form, stacked above the detail modal. */
function DecisionDialog({ resi, issue, onClose, onSaved }: DecisionDialogProps) {
    const allowed = issue.allowedDecisions;
    const [decision, setDecision] = useState(allowed[0]?.value ?? "RETURN");
    const [reason, setReason] = useState(DECISION_REASON_DEFAULT);
    const [note, setNote] = useState(issue.issueReport.quality_inspection_note ?? "");
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (saving) return;

        if (reason.trim().length < 10) {
            setError("Alasan keputusan minimal 10 karakter.");
            return;
        }

        setSaving(true);
        setError(null);

        try {
            await api.exceptions.decide(resi, {
                decision,
                decision_reason: reason.trim(),
                ...(note.trim() === "" ? {} : { quality_inspection_note: note.trim() })
            });
            setSaved(true);
            await onSaved();
            window.setTimeout(() => onClose(), 900);
        } catch (caught) {
            setError(caught instanceof ApiError ? caught.message : "Keputusan gagal disimpan.");
            setSaving(false);
        }
    }

    return (
        <Dialog onClose={onClose} label={`Keputusan kendala ${resi}`} width="lg">
            <div className="flex items-start justify-between gap-space-md">
                <div className="flex flex-col">
                    <h2 className="font-headline-sm text-headline-sm text-on-surface">Keputusan</h2>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        {resi} · {issue.category.label}
                    </p>
                </div>
                <CloseButton onClick={onClose} />
            </div>

            <form onSubmit={handleSubmit} className="flex flex-col gap-space-md">
                <div className="flex flex-col gap-1.5">
                    {allowed.map((option) => {
                        const active = decision === option.value;
                        return (
                            <label
                                key={option.value}
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
                                    value={option.value}
                                    checked={active}
                                    onChange={() => setDecision(option.value)}
                                    className="mt-1 accent-primary"
                                />
                                <span className="flex flex-col">
                                    <span className="font-body-md font-medium text-body-md text-on-surface">
                                        {option.label}
                                    </span>
                                    <span className="font-body-sm text-body-sm text-secondary">{option.helpText}</span>
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
                <label className="flex flex-col gap-1" htmlFor="decision-note">
                    <span className="font-body-sm text-body-sm text-secondary">
                        Catatan inspeksi kualitas (opsional)
                    </span>
                    <textarea
                        id="decision-note"
                        rows={2}
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        placeholder="Temuan tim kontrol kualitas..."
                        className="w-full resize-none rounded-lg border border-outline-variant bg-surface-container-lowest p-space-md font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
                    />
                </label>

                {error ? <ErrorNotice message={error} /> : null}

                <button
                    type="submit"
                    disabled={saving}
                    className="flex h-10 w-full items-center justify-center gap-space-sm rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-50"
                >
                    <MaterialIcon name={saving ? "refresh" : "check"} className="text-[18px]" />
                    <span>{saving ? "Menyimpan…" : "Simpan Keputusan"}</span>
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

interface RescheduleDialogProps {
    resi: string;
    defaultDate: string | null;
    onClose: () => void;
    onSaved: () => Promise<void>;
}

/** Schedules a new delivery window for a shipment waiting on one. */
function RescheduleDialog({ resi, defaultDate, onClose, onSaved }: RescheduleDialogProps) {
    const [date, setDate] = useState(defaultDate ?? todayIso());
    const [slot, setSlot] = useState(DELIVERY_SLOTS[0]);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (saving) return;

        setSaving(true);
        setError(null);

        try {
            await api.shipments.reschedule(resi, date, slot);
            await onSaved();
            onClose();
        } catch (caught) {
            setError(caught instanceof ApiError ? caught.message : "Jadwal gagal diperbarui.");
            setSaving(false);
        }
    }

    return (
        <Dialog onClose={onClose} label={`Jadwal ulang ${resi}`}>
            <div className="flex items-start justify-between gap-space-md">
                <div className="flex flex-col">
                    <h2 className="font-headline-sm text-headline-sm text-on-surface">Jadwal Ulang</h2>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">{resi}</p>
                </div>
                <CloseButton onClick={onClose} />
            </div>

            <form onSubmit={handleSubmit} className="flex flex-col gap-space-md">
                <label className="flex flex-col gap-1" htmlFor="reschedule-date">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Tanggal</span>
                    <input
                        id="reschedule-date"
                        type="date"
                        value={date}
                        onChange={(event) => setDate(event.target.value)}
                        required
                        className="h-9 cursor-pointer rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                    />
                </label>
                <label className="flex flex-col gap-1" htmlFor="reschedule-slot">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Jendela Pengantaran</span>
                    <select
                        id="reschedule-slot"
                        value={slot}
                        onChange={(event) => setSlot(event.target.value)}
                        className="h-9 cursor-pointer rounded-md border border-outline-variant bg-surface-container-lowest px-space-md font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
                    >
                        {DELIVERY_SLOTS.map((option) => (
                            <option key={option} value={option}>
                                {option} WIB
                            </option>
                        ))}
                    </select>
                </label>

                {error ? <ErrorNotice message={error} /> : null}

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
                        disabled={saving}
                        className="flex h-9 items-center gap-space-xs rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-50"
                    >
                        <MaterialIcon name={saving ? "refresh" : "event_repeat"} className="text-[16px]" />
                        <span>{saving ? "Menyimpan…" : "Simpan Jadwal"}</span>
                    </button>
                </div>
            </form>
        </Dialog>
    );
}

/**
 * Detail for one shipment, opened from the shipment and report tables.
 *
 * Both halves come from the API: the shipment endpoint carries the delivery
 * summary and the proof photo, the exception endpoint the kendala report, its
 * allowed decisions and the inspection checklist. Moving the courier, the proof
 * photo, the decision and the reschedule each open as a dialog on top of this
 * one.
 */
export function ShipmentDetailModal({ resi, onClose, onChanged }: ShipmentDetailModalProps) {
    const [detail, setDetail] = useState<ShipmentDetailResponse | null>(null);
    const [issue, setIssue] = useState<ExceptionDetailResponse | null>(null);
    const [couriers, setCouriers] = useState<CourierOption[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [proofOpen, setProofOpen] = useState(false);
    const [decisionOpen, setDecisionOpen] = useState(false);
    const [rescheduleOpen, setRescheduleOpen] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const shipment = await api.shipments.show(resi);
            setDetail(shipment);

            if (shipment.package.status === "ISSUE_REPORTED") {
                try {
                    setIssue(await api.exceptions.show(resi));
                } catch {
                    // A package can be flagged without a condition report yet.
                    setIssue(null);
                }
            } else {
                setIssue(null);
            }
        } catch (caught) {
            setError(caught instanceof ApiError ? caught.message : "Detail pengiriman gagal dimuat.");
        } finally {
            setLoading(false);
        }
    }, [resi]);

    useEffect(() => {
        void load();
    }, [load]);

    // The picker is the only thing that needs the roster, so it is fetched the
    // first time the dialog is opened rather than on every detail view.
    useEffect(() => {
        if (!pickerOpen || couriers.length > 0) return;

        void (async () => {
            try {
                const master = await api.shipments.createOptions();
                setCouriers(master.couriers);
            } catch {
                setCouriers([]);
            }
        })();
    }, [pickerOpen, couriers.length]);

    async function afterChange() {
        await load();
        onChanged?.();
    }

    async function moveCourier(courierId: number) {
        setActionError(null);

        try {
            await api.shipments.assign(resi, courierId);
            setPickerOpen(false);
            await afterChange();
        } catch (caught) {
            setActionError(caught instanceof ApiError ? caught.message : "Kurir gagal ditugaskan.");
        }
    }

    async function requestConfirmation() {
        setActionError(null);

        try {
            await api.shipments.requestConfirmation(resi);
            await afterChange();
        } catch (caught) {
            setActionError(caught instanceof ApiError ? caught.message : "Permintaan konfirmasi gagal dikirim.");
        }
    }

    const photoUrl = detail?.issuePhotoUrl ?? detail?.proofPhotoUrl ?? null;
    const photoCaption = issue ? "Foto pemeriksaan" : "Foto serah terima";
    const photoMeta = issue ? "Laporan kurir" : "Diterima pembeli";
    const checklist = issue?.checklist.filter((item) => item.answered) ?? [];
    // Mirrors AssignCourier::ASSIGNABLE_STATUSES. Past handover the parcel is
    // on the road, so the desk cannot swap the courier and the button says so
    // instead of letting the picker fail on submit.
    const canAssignCourier = ASSIGNABLE_STATUSES.includes(detail?.package.status ?? "");

    // The detail map: destination (the recipient point), its geofence, and the
    // last place the courier's device reported from.
    const destination = detail?.location?.destination ?? null;
    const latestFix = detail?.location?.latest_fix ?? null;
    const mapMarkers: MapMarker[] = [
        ...(destination
            ? [
                  {
                      lat: destination.latitude,
                      lng: destination.longitude,
                      tone: "info" as Tone,
                      popup: "Titik penerima"
                  }
              ]
            : []),
        ...(latestFix
            ? [
                  {
                      lat: latestFix.latitude,
                      lng: latestFix.longitude,
                      tone: (latestFix.inside === false ? "danger" : "success") as Tone,
                      popup: `Posisi kurir terakhir${
                          latestFix.action_label ? ` · ${latestFix.action_label}` : ""
                      }${latestFix.inside === false ? " · di luar radius" : ""}`
                  }
              ]
            : [])
    ];
    const mapCircle: MapCircle | null =
        destination && detail?.location
            ? { lat: destination.latitude, lng: destination.longitude, radiusM: detail.location.radius_m }
            : null;
    const hasMapData = destination !== null || latestFix !== null;

    return (
        <>
            <Dialog
                onClose={onClose}
                label={`Detail pengiriman ${resi}`}
                width="4xl"
                className="max-h-[calc(100dvh-3rem)] overflow-y-auto"
            >
                {loading ? (
                    <div className="flex flex-col items-center gap-space-sm py-space-xl text-center">
                        <MaterialIcon name="progress_activity" className="animate-spin text-[24px] text-secondary" />
                        <span className="font-body-sm text-body-sm text-secondary">Memuat detail…</span>
                    </div>
                ) : error !== null || detail === null ? (
                    <div className="flex flex-col items-center gap-space-sm py-space-md text-center">
                        <MaterialIcon name="cloud_off" className="text-[24px] text-secondary" />
                        <span className="font-headline-sm text-headline-sm text-on-surface">
                            Detail tidak dapat dimuat
                        </span>
                        <p className="font-body-sm text-body-sm text-secondary">{error}</p>
                        <div className="mt-space-xs flex items-center gap-space-sm">
                            <button
                                type="button"
                                onClick={() => void load()}
                                className="flex h-9 items-center rounded-md border border-outline-variant px-space-lg font-label-md text-label-md text-on-surface transition-colors hover:border-secondary"
                            >
                                Coba lagi
                            </button>
                            <button
                                type="button"
                                onClick={onClose}
                                className="flex h-9 items-center rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="flex items-start gap-space-md">
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <div className="flex flex-wrap items-center gap-space-sm">
                                    <span className="font-data-mono font-semibold text-headline-sm text-on-surface">
                                        {detail.package.tracking_number}
                                    </span>
                                    <Pill tone={detail.tone}>{detail.statusLabel}</Pill>
                                </div>
                                <p className="truncate font-body-sm text-body-sm text-secondary">
                                    {detail.package.recipient?.name ?? "-"} · {detail.package.recipient?.address ?? "-"}
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
                                    <Fact
                                        label="Pengirim"
                                        value={detail.package.buyer?.name ?? "-"}
                                        hint={detail.package.buyer?.address ?? "-"}
                                    />
                                    <Fact
                                        label="Penerima"
                                        value={detail.package.recipient?.name ?? "-"}
                                        hint={detail.package.recipient?.address ?? "-"}
                                    />
                                    <Fact
                                        label="Kurir"
                                        value={detail.package.courier?.name ?? "Belum Ditugaskan"}
                                        hint={
                                            detail.package.courier
                                                ? (detail.package.cooler_box_code ?? "Cooler box belum tercatat")
                                                : "Tugaskan kurir untuk mulai mengantar"
                                        }
                                    />
                                    <Fact label="Jadwal" value={detail.scheduleLabel} />
                                    <Fact
                                        label="Muatan"
                                        value={`${detail.package.koli_count ?? 1} Koli · ${detail.package.weight_kg ?? "-"} kg`}
                                    />
                                    <Fact label="Suhu target" value="-18°C s.d. -25°C" />
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
                                                {issue.category.label}
                                            </span>
                                            <span className="ml-auto">
                                                <Pill tone={issue.resolutionTone}>{issue.resolutionStatus}</Pill>
                                            </span>
                                        </div>
                                        <span className="font-body-sm text-body-sm text-secondary">
                                            Dilaporkan {issue.reportedAtFormatted ?? "-"}
                                        </span>
                                        {issue.issueReport.description ? (
                                            <p className="border-primary/30 border-l-2 pl-space-md font-body-md text-body-md text-on-surface italic leading-relaxed">
                                                “{issue.issueReport.description}”
                                            </p>
                                        ) : null}
                                        <div className="flex flex-wrap gap-1.5">
                                            {checklist.length === 0 ? (
                                                <span className="rounded-full border border-outline-variant bg-surface-container-lowest px-2 py-0.5 font-label-sm text-label-sm text-secondary">
                                                    Semua item pemeriksaan normal
                                                </span>
                                            ) : (
                                                checklist.map((item) => (
                                                    <span
                                                        key={item.key}
                                                        className="rounded-full border border-outline-variant bg-surface-container-lowest px-2 py-0.5 font-label-sm text-label-sm text-secondary"
                                                    >
                                                        {item.label} · {item.detail}
                                                    </span>
                                                ))
                                            )}
                                        </div>
                                        {photoUrl ? (
                                            <ProofCard
                                                photo={photoUrl}
                                                caption={photoCaption}
                                                meta={photoMeta}
                                                onOpen={() => setProofOpen(true)}
                                            />
                                        ) : null}
                                        <div className="flex flex-wrap items-center justify-between gap-space-sm border-outline-variant border-t pt-space-md">
                                            <span className="font-label-sm text-label-sm text-secondary">
                                                Keputusan
                                            </span>
                                            <span className="font-body-sm text-body-sm text-on-surface">
                                                {decisionLabel(issue.issueReport.decision)}
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
                                            {photoUrl
                                                ? "Bukti serah terima tersedia untuk paket ini."
                                                : "Bukti penyerahan muncul setelah kurir mengunggahnya di lokasi."}
                                        </p>
                                        {photoUrl ? (
                                            <ProofCard
                                                photo={photoUrl}
                                                caption={photoCaption}
                                                meta={photoMeta}
                                                onOpen={() => setProofOpen(true)}
                                            />
                                        ) : null}
                                    </div>
                                )}
                            </section>
                        </div>

                        <section className="flex flex-col gap-space-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                                    Lokasi Pengiriman
                                </h3>
                                {latestFix ? (
                                    <span className="font-label-sm text-label-sm text-secondary">
                                        Posisi terakhir {latestFix.level_label ?? ""}
                                        {latestFix.at ? ` · ${latestFix.at}` : ""}
                                    </span>
                                ) : null}
                            </div>
                            <LeafletMap
                                markers={mapMarkers}
                                circle={mapCircle}
                                heightClass="h-64"
                                emptyLabel="Penerima belum punya koordinat, jadi lokasinya belum bisa dipetakan."
                            />
                            {!hasMapData ? null : (
                                <p className="font-label-sm text-label-sm text-secondary">
                                    Titik biru adalah penerima; lingkaran putus-putus adalah radius yang diizinkan
                                    {detail?.location ? ` (${detail.location.radius_m} m)` : ""}.
                                </p>
                            )}
                        </section>

                        {actionError ? <ErrorNotice message={actionError} /> : null}

                        <div className="flex flex-wrap items-center gap-space-sm border-outline-variant border-t pt-space-md">
                            <SecondaryButton
                                icon="swap_horiz"
                                label={detail.package.courier ? "Pindahkan kurir" : "Tugaskan kurir"}
                                onClick={() => {
                                    setActionError(null);
                                    setPickerOpen(true);
                                }}
                                disabled={!canAssignCourier}
                                title={
                                    canAssignCourier
                                        ? undefined
                                        : "Paket sudah diantar atau selesai; kurir tidak dapat diubah."
                                }
                            />
                            <SecondaryButton
                                icon="image"
                                label="Lihat bukti"
                                onClick={() => setProofOpen(true)}
                                disabled={photoUrl === null}
                                title={photoUrl === null ? "Belum ada bukti" : undefined}
                            />
                            {detail.package.status === "COURIER_ASSIGNED" ? (
                                <SecondaryButton
                                    icon="event_available"
                                    label="Minta konfirmasi jadwal"
                                    onClick={() => void requestConfirmation()}
                                />
                            ) : null}
                            {detail.package.status === "RESCHEDULE_REQUESTED" ? (
                                <SecondaryButton
                                    icon="event_repeat"
                                    label="Jadwal ulang"
                                    onClick={() => setRescheduleOpen(true)}
                                />
                            ) : null}
                            <div className="ml-auto flex items-center gap-space-sm">
                                {issue && issue.issueReport.decision === null ? (
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
                    </>
                )}
            </Dialog>

            <CourierPickerDialog
                open={pickerOpen}
                couriers={couriers}
                currentId={detail?.package.courier_id ?? null}
                subtitle={
                    detail
                        ? `${detail.package.recipient?.name ?? "-"} (${detail.package.recipient?.address ?? "-"})`
                        : undefined
                }
                onClose={() => setPickerOpen(false)}
                onConfirm={(courierId) => void moveCourier(courierId)}
            />

            {proofOpen && photoUrl ? (
                <Dialog onClose={() => setProofOpen(false)} label={`Bukti pengiriman ${resi}`} width="lg">
                    <div className="flex items-start justify-between gap-space-md">
                        <h2 className="font-headline-sm text-headline-sm text-on-surface">Bukti pengiriman</h2>
                        <CloseButton onClick={() => setProofOpen(false)} />
                    </div>
                    <div className="overflow-hidden rounded-lg border border-outline-variant">
                        <img src={photoUrl} alt={photoCaption} className="h-72 w-full object-cover" />
                        <div className="flex items-center justify-between border-outline-variant border-t bg-surface-container-low px-space-md py-space-sm">
                            <span className="font-body-sm text-body-sm text-secondary">{photoCaption}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{photoMeta}</span>
                        </div>
                    </div>
                </Dialog>
            ) : null}

            {decisionOpen && issue ? (
                <DecisionDialog
                    resi={resi}
                    issue={issue}
                    onClose={() => setDecisionOpen(false)}
                    onSaved={afterChange}
                />
            ) : null}

            {rescheduleOpen ? (
                <RescheduleDialog
                    resi={resi}
                    defaultDate={detail?.scheduledDate ?? null}
                    onClose={() => setRescheduleOpen(false)}
                    onSaved={afterChange}
                />
            ) : null}
        </>
    );
}
