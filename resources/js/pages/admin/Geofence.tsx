import { Head } from "@inertiajs/react";
import { type FormEvent, useCallback, useEffect, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { AdminLayout } from "@/layouts";
import { ApiError, api, type GeofenceResponse, type GeofenceSegment } from "@/lib/api";
import { cx } from "@/lib/cx";

interface Draft {
    [serviceType: string]: number;
}

/**
 * Pengaturan Radius GPS.
 *
 * The radius decides how close a courier has to be to the destination for proof
 * of delivery to be accepted, per service segment. Every save is stamped with
 * the admin who made it, which is why the panel explains the effect rather than
 * just showing a number.
 */
export default function Geofence() {
    const [data, setData] = useState<GeofenceResponse | null>(null);
    const [draft, setDraft] = useState<Draft>({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            const response = await api.geofence.show();
            setData(response);
            setDraft(Object.fromEntries(response.segments.map((segment) => [segment.service_type, segment.radius_m])));
            setError(null);
        } catch (caught) {
            setError(caught instanceof ApiError ? caught.message : "Pengaturan radius gagal dimuat.");
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void load();
    }, [load]);

    async function save(event: FormEvent) {
        event.preventDefault();
        setSaving(true);
        setError(null);

        try {
            const response = await api.geofence.update(draft);
            setData({ segments: response.segments, updated_at: response.updated_at });
            setNotice(response.message);
        } catch (caught) {
            setError(caught instanceof ApiError ? caught.message : "Radius gagal disimpan.");
        } finally {
            setSaving(false);
        }
    }

    const segments = data?.segments ?? [];
    const dirty = data !== null && segments.some((segment) => draft[segment.service_type] !== segment.radius_m);

    function update(segment: GeofenceSegment, value: number) {
        setNotice(null);
        setDraft((current) => ({ ...current, [segment.service_type]: value }));
    }

    return (
        <AdminLayout>
            <Head title="Radius GPS" />

            <div className="flex flex-col justify-between gap-space-md pb-space-lg md:flex-row md:items-center">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Radius GPS</h1>
                    <p className="mt-0.5 font-body-sm text-body-sm text-secondary">
                        Toleransi jarak kurir dari titik penerima saat serah terima, per jenis layanan
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => void load()}
                    className="flex h-9 items-center gap-1.5 self-start rounded-md border border-outline-variant px-space-md font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container"
                >
                    <MaterialIcon name="refresh" className="text-[16px]" />
                    Muat ulang
                </button>
            </div>

            {notice ? (
                <div className="mb-space-md flex items-center justify-between gap-space-sm rounded-lg bg-tertiary-fixed p-space-md font-body-sm text-body-sm text-on-tertiary-fixed">
                    <span>{notice}</span>
                    <button
                        type="button"
                        onClick={() => setNotice(null)}
                        className="shrink-0 rounded-md px-2 py-1 font-label-sm text-label-sm"
                    >
                        Tutup
                    </button>
                </div>
            ) : null}

            {error ? (
                <div
                    role="alert"
                    className="mb-space-md rounded-lg bg-error-container p-space-md font-body-sm text-body-sm text-on-error-container"
                >
                    {error}
                </div>
            ) : null}

            {loading && data === null ? (
                <p className="font-body-sm text-body-sm text-secondary">Memuat pengaturan…</p>
            ) : (
                <form onSubmit={save} className="flex flex-col gap-space-lg">
                    <div className="grid gap-space-md lg:grid-cols-2">
                        {segments.map((segment) => {
                            const value = draft[segment.service_type] ?? segment.radius_m;
                            const changed = value !== segment.radius_m;

                            return (
                                <section
                                    key={segment.service_type}
                                    className={cx(
                                        "flex flex-col gap-space-sm rounded-lg border bg-surface-container-lowest p-space-lg",
                                        changed ? "border-primary" : "border-outline-variant"
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex flex-col">
                                            <span className="font-headline-sm text-headline-sm text-on-surface">
                                                {segment.label}
                                            </span>
                                            <span className="font-label-sm text-label-sm text-primary uppercase">
                                                {segment.sla}
                                            </span>
                                        </div>
                                        <span className="rounded-full bg-surface-container px-2.5 py-1 font-label-sm text-label-sm text-secondary">
                                            Bawaan {segment.default_m} m
                                        </span>
                                    </div>

                                    <p className="font-body-sm text-body-sm leading-relaxed text-secondary">
                                        {segment.description}
                                    </p>

                                    <label className="flex flex-col gap-1.5">
                                        <span className="font-label-sm text-label-sm text-secondary uppercase">
                                            Radius serah terima (meter)
                                        </span>
                                        <div className="flex items-center gap-3">
                                            <input
                                                type="range"
                                                min={segment.min}
                                                max={segment.max}
                                                step={segment.step}
                                                value={value}
                                                onChange={(event) => update(segment, Number(event.target.value))}
                                                className="h-1.5 flex-1 cursor-pointer accent-primary"
                                                aria-label={"Radius " + segment.label}
                                            />
                                            <input
                                                type="number"
                                                min={segment.min}
                                                max={segment.max}
                                                step={segment.step}
                                                value={value}
                                                onChange={(event) => update(segment, Number(event.target.value))}
                                                className="h-9 w-24 rounded-md border border-outline-variant bg-surface-container-low px-2 text-right font-data-mono text-body-sm text-on-surface focus:border-primary focus:outline-none"
                                            />
                                        </div>
                                        <span className="font-label-sm text-label-sm text-secondary">
                                            Boleh {segment.min} - {segment.max} m, langkah {segment.step} m.
                                        </span>
                                    </label>
                                </section>
                            );
                        })}
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-space-sm rounded-lg border border-outline-variant bg-surface-container-lowest p-space-md">
                        <span className="font-body-sm text-body-sm text-secondary">
                            Perubahan berlaku langsung untuk pengiriman berikutnya; paket yang sudah berjalan memakai
                            radius saat pemeriksaannya.
                        </span>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                disabled={!dirty}
                                onClick={() =>
                                    setDraft(
                                        Object.fromEntries(
                                            segments.map((segment) => [segment.service_type, segment.radius_m])
                                        )
                                    )
                                }
                                className="h-9 rounded-md px-space-lg font-label-md text-label-md text-secondary transition-colors hover:bg-surface-container disabled:opacity-50"
                            >
                                Batalkan perubahan
                            </button>
                            <button
                                type="submit"
                                disabled={!dirty || saving}
                                className="flex h-9 items-center gap-1.5 rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-50"
                            >
                                <MaterialIcon name="save" className="text-[16px]" />
                                {saving ? "Menyimpan…" : "Simpan radius"}
                            </button>
                        </div>
                    </div>
                </form>
            )}
        </AdminLayout>
    );
}
