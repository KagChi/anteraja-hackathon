import { useEffect, useState } from "react";
import { LeafletMap, type MapMarker } from "@/components/LeafletMap";
import { MaterialIcon } from "@/components/MaterialIcon";
import type { ScreenProps } from "../../App";
import { ActionError } from "../../components/ActionError";
import { Sheet } from "../../components/Sheet";
import { StateCard } from "../../components/StateCard";
import { ApiError, courierApi, firstError } from "../../lib/api";
import { accuracyLabel, distanceLabel, gpsFields, haversineMeters } from "../../lib/geo";
import { useRouter } from "../../lib/router";
import { useFixWindow } from "../../lib/useFixWindow";
import { useLoad } from "../../lib/useLoad";

/** The shipment currently on the road, with its location validation. */
export default function Delivery({ params }: ScreenProps) {
    const resi = params.resi ?? "";
    const { navigate } = useRouter();
    const { fixes, latest } = useFixWindow();
    const { data, error, reload } = useLoad(
        () =>
            courierApi.deliveries.show(resi).catch((failure: unknown) => {
                if (failure instanceof ApiError && failure.status === 404) {
                    return null;
                }

                throw failure;
            }),
        `courier-delivery-${resi}`
    );
    const [arrived, setArrived] = useState(false);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [issues, setIssues] = useState<string[]>([]);
    const [actionError, setActionError] = useState<string | null>(null);
    const [gpsNotice, setGpsNotice] = useState("");
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (resi === "") {
            return;
        }

        void courierApi.deliveries
            .issueForm(resi)
            .then((form) => setIssues(form.issueForm.options.map((option) => option.label)))
            .catch(() => setIssues([]));
    }, [resi]);

    if (error !== null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <StateCard message={error} onRetry={reload} />
            </div>
        );
    }

    if (data === null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <section className="flex w-full flex-col items-center gap-space-sm rounded-xl bg-surface-container-lowest p-space-xl">
                    <span className="font-headline-md text-headline-md text-on-surface">Paket tidak ditemukan</span>
                    <span className="text-center font-body-sm text-body-sm text-secondary">
                        Paket ini tidak dapat dibuka. Pilih tugas lain dari daftar.
                    </span>
                    <button
                        type="button"
                        onClick={() => navigate("/courier/tasks")}
                        className="mt-space-sm inline-flex h-11 items-center justify-center rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                    >
                        Kembali ke Tugas
                    </button>
                </section>
            </div>
        );
    }

    const delivery = data.delivery;
    const gps = delivery.gps;
    const destination = gps.destination;
    const hasMap = destination !== null && destination.latitude !== null && destination.longitude !== null;
    const meters =
        hasMap && latest !== null
            ? haversineMeters(
                  latest.latitude,
                  latest.longitude,
                  destination.latitude as number,
                  destination.longitude as number
              )
            : null;
    const inside = meters !== null && meters <= gps.radius_m;
    const markers: MapMarker[] = [];

    if (hasMap) {
        markers.push({
            lat: destination.latitude as number,
            lng: destination.longitude as number,
            tone: "info",
            popup: "Titik penerima"
        });
    }

    if (latest !== null) {
        markers.push({
            lat: latest.latitude,
            lng: latest.longitude,
            tone: inside ? "success" : "warning",
            popup: "Posisi Anda"
        });
    }

    async function send(action: "depart" | "arrive") {
        if (busy) {
            return;
        }

        setBusy(true);
        setActionError(null);

        try {
            const fields = gpsFields(fixes, latest);

            if (action === "depart") {
                await courierApi.deliveries.depart(resi, fields);
            } else {
                await courierApi.deliveries.arrive(resi, fields);
                setArrived(true);
            }

            reload();
        } catch (failure) {
            setActionError(reasonMessage(failure));
        } finally {
            setBusy(false);
        }
    }

    async function requestReview() {
        setActionError(null);

        try {
            await courierApi.deliveries.requestReview(resi, gps.action ?? "POD");
            setGpsNotice("Permintaan tinjauan terkirim ke hub.");
            reload();
        } catch (failure) {
            setActionError(firstError(failure));
        }
    }

    async function requestConfirmation() {
        if (busy) {
            return;
        }

        setBusy(true);
        setActionError(null);

        try {
            await courierApi.deliveries.requestConfirmation(resi);
            reload();
        } catch (failure) {
            setActionError(firstError(failure));
        } finally {
            setBusy(false);
        }
    }

    const levelTone =
        delivery.gps.level === "BLOCKED" ? "error" : delivery.gps.level === "SUSPECTED" ? "suspected" : "clean";
    const phone = delivery.phoneHref === "" ? "" : delivery.phoneHref;

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin pb-space-xl">
            <div className="flex w-full items-center justify-between rounded-xl bg-surface-container-lowest p-space-xl">
                <span className="flex flex-col gap-0.5">
                    <span className="font-body-sm text-body-sm text-secondary">Nomor Resi</span>
                    <span className="font-headline-md text-headline-md tracking-tight text-on-surface select-all">
                        {delivery.tracking}
                    </span>
                </span>
                <span className="rounded-full bg-secondary-container px-space-md py-0.5 font-label-sm text-label-sm text-on-secondary-container">
                    {delivery.service}
                </span>
            </div>

            <section className="flex w-full flex-col gap-2 rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Progres Pengantaran</span>
                <div className="flex w-full gap-2">
                    <Step icon="check" label="Berangkat" done={delivery.stage !== "ASSIGNED"} />
                    <Step icon="local_shipping" label="Sedang Diantar" active={delivery.stage !== "ARRIVED"} />
                    <button
                        type="button"
                        onClick={() => void send("arrive")}
                        disabled={busy}
                        className={`flex flex-1 flex-col items-center gap-1 rounded-lg p-2 ${
                            arrived || delivery.stage === "ARRIVED" ? "bg-primary-fixed" : "bg-surface-container-high"
                        }`}
                    >
                        <MaterialIcon
                            name="location_on"
                            className={`text-[16px] ${
                                arrived || delivery.stage === "ARRIVED" ? "text-primary" : "text-secondary"
                            }`}
                        />
                        <span
                            className={`font-label-sm text-label-sm font-bold ${
                                arrived || delivery.stage === "ARRIVED" ? "text-primary" : "text-secondary"
                            }`}
                        >
                            Tiba di Lokasi
                        </span>
                    </button>
                </div>
            </section>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between gap-2">
                    <span className="flex items-center gap-space-sm">
                        <MaterialIcon name="location_on" className="text-[18px] text-secondary" />
                        <span className="font-label-sm text-label-sm text-secondary uppercase">Validasi Lokasi</span>
                    </span>
                    <span className={levelBadgeClasses(levelTone)}>{gps.level_label}</span>
                </div>

                {meters === null ? (
                    <span className="font-body-sm text-body-sm text-secondary">
                        Menunggu sinyal GPS. Titik terkumpul: {fixes.length}.
                    </span>
                ) : (
                    <span className="font-headline-sm text-headline-sm text-on-surface">{distanceLabel(meters)}</span>
                )}

                <div className="flex w-full items-center justify-between gap-2">
                    <span className="font-body-sm text-body-sm text-secondary">Radius diizinkan {gps.radius_m} m</span>
                    {latest === null ? null : (
                        <span className="font-body-sm text-body-sm text-secondary">
                            {accuracyLabel(latest.accuracy)}
                        </span>
                    )}
                </div>

                {gps.reasons.map((reason) => (
                    <div
                        key={`${reason.code ?? "reason"}-${reason.label ?? ""}`}
                        className="flex w-full items-start gap-space-sm"
                    >
                        <MaterialIcon
                            name="error_outline"
                            className={`text-[16px] ${reason.severity === "strong" ? "text-error" : "text-secondary"}`}
                        />
                        <span className="flex flex-1 flex-col gap-0.5">
                            <span className="font-body-sm text-body-sm font-bold text-on-surface">{reason.label}</span>
                            <span className="font-body-sm text-body-sm leading-snug text-secondary">
                                {reason.detail}
                            </span>
                        </span>
                    </div>
                ))}

                {gps.lock === null ? null : (
                    <div className="flex w-full items-center gap-space-sm rounded-lg bg-surface-container-low p-space-md">
                        <MaterialIcon name="schedule" className="text-[16px] text-secondary" />
                        <span className="flex-1 font-body-sm text-body-sm text-secondary">
                            Tinjauan hub: {gps.lock.status_label}
                        </span>
                    </div>
                )}

                {gpsNotice === "" ? null : (
                    <div className="flex w-full items-center gap-space-sm rounded-lg bg-tertiary-fixed p-space-md">
                        <MaterialIcon name="check_circle" className="text-[16px] text-on-tertiary-fixed" />
                        <span className="flex-1 font-body-sm text-body-sm text-on-tertiary-fixed">{gpsNotice}</span>
                    </div>
                )}

                {gps.can_request_review ? (
                    <button
                        type="button"
                        onClick={() => void requestReview()}
                        className="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
                    >
                        <MaterialIcon name="help_outline" className="text-[18px]" />
                        Minta Tinjauan Hub
                    </button>
                ) : null}
            </section>

            {delivery.canRequestConfirmation ? (
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => void requestConfirmation()}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary disabled:opacity-60"
                >
                    <MaterialIcon name="event_available" className="text-[20px]" />
                    Minta Konfirmasi Jadwal Penerimaan
                </button>
            ) : null}

            {delivery.canDepart ? (
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => void send("depart")}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary disabled:opacity-60"
                >
                    <MaterialIcon name="local_shipping" className="text-[20px]" />
                    Mulai Berangkat
                </button>
            ) : null}

            <section className="flex w-full flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-xl">
                <div className="flex w-full items-start justify-between gap-2">
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">Penerima</span>
                        <span className="font-headline-lg text-headline-lg text-on-surface">{delivery.recipient}</span>
                        <span className="font-body-lg text-body-lg text-on-surface">{delivery.phone}</span>
                    </span>
                    {phone === "" ? null : (
                        <a
                            href={phone}
                            className="inline-flex h-11 items-center justify-center gap-1.5 rounded-lg bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
                        >
                            <MaterialIcon name="call" className="text-[18px]" />
                            Panggil
                        </a>
                    )}
                </div>

                <hr className="border-outline-variant" />

                <span className="flex w-full flex-col gap-1">
                    <span className="flex items-center gap-1">
                        <MaterialIcon name="pin_drop" className="text-[16px] text-secondary" />
                        <span className="font-body-sm text-body-sm text-secondary">Alamat Tujuan</span>
                    </span>
                    <span className="font-body-lg text-body-lg leading-relaxed text-on-surface">
                        {delivery.address}
                    </span>
                </span>

                <div className="flex w-full items-start gap-space-sm rounded-lg bg-error-container p-space-md">
                    <MaterialIcon name="ac_unit" className="text-[20px] text-error" />
                    <span className="flex flex-1 flex-col gap-0.5">
                        <span className="font-label-sm text-label-sm text-on-error-container uppercase">
                            Instruksi Khusus
                        </span>
                        <span className="font-body-sm text-body-sm leading-snug text-on-error-container">
                            {delivery.instruction ?? "Tidak ada instruksi khusus."}
                        </span>
                    </span>
                </div>
            </section>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="flex items-center gap-space-sm">
                    <MaterialIcon name="map" className="text-[18px] text-secondary" />
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Peta Lokasi</span>
                </span>

                {hasMap ? (
                    <LeafletMap
                        markers={markers}
                        circle={{
                            lat: destination.latitude as number,
                            lng: destination.longitude as number,
                            radiusM: gps.radius_m
                        }}
                        heightClass="h-64"
                    />
                ) : (
                    <span className="font-body-sm text-body-sm leading-snug text-secondary">
                        Titik penerima belum punya koordinat, jadi peta belum bisa ditampilkan.
                    </span>
                )}

                <span className="font-body-sm text-body-sm text-secondary">{delivery.mapLabel}</span>

                <a
                    href={delivery.mapHref}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
                >
                    <MaterialIcon name="navigation" className="text-[18px]" />
                    Buka Navigasi Rute
                </a>
            </section>

            <ActionError message={actionError} />

            {delivery.canProof ? (
                <button
                    type="button"
                    onClick={() => navigate(`/courier/proof/${resi}`)}
                    className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                >
                    <MaterialIcon name="inventory" className="text-[20px]" />
                    Serahkan Paket
                </button>
            ) : null}

            {delivery.canReportIssue ? (
                <button
                    type="button"
                    onClick={() => setSheetOpen(true)}
                    className="py-2 font-body-md text-body-md text-secondary underline"
                >
                    Laporkan Kendala
                </button>
            ) : null}

            {sheetOpen ? (
                <Sheet onClose={() => setSheetOpen(false)} label="Pilih Kendala">
                    <div className="flex w-full flex-col gap-space-lg p-space-lg">
                        <span className="font-headline-md text-headline-md text-on-surface">Pilih Kendala</span>
                        {issues.map((issue) => (
                            <button
                                key={issue}
                                type="button"
                                onClick={() => {
                                    setSheetOpen(false);
                                    navigate(`/courier/issue/${resi}?reason=${encodeURIComponent(issue)}`);
                                }}
                                className="w-full rounded-lg bg-surface-container-low p-space-md text-left font-body-md text-body-md text-on-surface"
                            >
                                {issue}
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={() => setSheetOpen(false)}
                            className="inline-flex h-11 w-full items-center justify-center rounded-lg bg-surface-container-high font-label-md text-label-md text-on-surface"
                        >
                            Batal
                        </button>
                    </div>
                </Sheet>
            ) : null}
        </div>
    );
}

function Step({
    icon,
    label,
    done = false,
    active = false
}: {
    icon: string;
    label: string;
    done?: boolean;
    active?: boolean;
}) {
    return (
        <span
            className={`flex flex-1 flex-col items-center gap-1 rounded-lg p-2 ${
                active ? "bg-primary-fixed" : done ? "bg-surface-container-low" : "bg-surface-container-high"
            }`}
        >
            <MaterialIcon name={icon} className={`text-[16px] ${active ? "text-primary" : "text-secondary"}`} />
            <span className={`font-label-sm text-label-sm ${active ? "text-primary" : "text-secondary"}`}>{label}</span>
        </span>
    );
}

function levelBadgeClasses(tone: "clean" | "suspected" | "error"): string {
    switch (tone) {
        case "error":
            return "rounded-full bg-error-container px-space-md py-0.5 font-label-sm text-label-sm text-on-error-container";
        case "suspected":
            return "rounded-full bg-secondary-container px-space-md py-0.5 font-label-sm text-label-sm text-on-secondary-container";
        default:
            return "rounded-full bg-tertiary-fixed px-space-md py-0.5 font-label-sm text-label-sm text-tertiary";
    }
}

/** A refused action explains itself: the server sends the reasons with it. */
function reasonMessage(failure: unknown): string {
    if (failure instanceof ApiError) {
        const reasons = failure.payload.reasons;

        if (Array.isArray(reasons) && reasons.length > 0) {
            const first = reasons[0] as { label?: unknown };

            if (typeof first.label === "string" && first.label !== "") {
                return `${failure.message} ${first.label}.`;
            }
        }
    }

    return firstError(failure);
}
