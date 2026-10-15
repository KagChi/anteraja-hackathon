import L from "leaflet";
import { useCallback, useEffect, useRef, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { cx } from "@/lib/cx";
import "leaflet/dist/leaflet.css";

/** The point the hub pinned for a parcel. */
export interface PickedPoint {
    latitude: number;
    longitude: number;
}

interface LocationPickerProps {
    value: PickedPoint | null;
    /** Fires on every click, drag or "use my location". */
    onChange: (point: PickedPoint) => void;
    /**
     * Best-effort reverse geocode of the pinned point, so the typed address and
     * the pin on the map cannot drift apart. Never blocks the pin itself.
     */
    onResolvedAddress?: (address: string, area: string | null) => void;
    /** Centre before anything is pinned. */
    center?: [number, number];
    zoom?: number;
    heightClass?: string;
    className?: string;
    /** Shown under the map while nothing is pinned. */
    hint?: string;
}

const OSM_URL = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

/**
 * Leaflet draws its default marker from PNGs whose URLs Vite would have to be
 * told about, so the pin is a divIcon carrying an inline SVG instead: no asset
 * pipeline, and the colour matches the console's "info" tone.
 */
const PIN_HTML =
    '<svg width="30" height="30" viewBox="0 0 24 24" fill="#1f6feb" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>';

function pinIcon(): L.DivIcon {
    return L.divIcon({ className: "", html: PIN_HTML, iconSize: [30, 30], iconAnchor: [15, 30] });
}

/**
 * Drop a pin on the destination.
 *
 * The hub can click the map, drag the pin, or stand at the door and press
 * "Lokasi saya". A resolved point is handed back immediately; the address is a
 * follow-up that may never arrive, so the pin is never held hostage to the
 * geocoder.
 */
export function LocationPicker({
    value,
    onChange,
    onResolvedAddress,
    center = [-6.2, 106.8],
    zoom = 12,
    heightClass = "h-64",
    className,
    hint = "Klik peta, geser pin, atau gunakan lokasi perangkat ini."
}: LocationPickerProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const markerRef = useRef<L.Marker | null>(null);
    // The click/drag handlers are installed once; they read the live callbacks
    // through this ref so a re-render never leaves them stale.
    const changeRef = useRef(onChange);
    const geocodeRef = useRef(onResolvedAddress);
    const [locating, setLocating] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);

    changeRef.current = onChange;
    geocodeRef.current = onResolvedAddress;

    /** Move the pin and hand the point back, then try to name it. */
    const applyPoint = useCallback((lat: number, lng: number, reverseGeocode: boolean) => {
        const map = mapRef.current;
        if (!map) return;

        const point = { latitude: Number(lat.toFixed(6)), longitude: Number(lng.toFixed(6)) };

        if (markerRef.current === null) {
            const marker = L.marker([point.latitude, point.longitude], { icon: pinIcon(), draggable: true }).addTo(map);
            marker.on("dragend", () => {
                const at = marker.getLatLng();
                applyPoint(at.lat, at.lng, true);
            });
            markerRef.current = marker;
        } else {
            markerRef.current.setLatLng([point.latitude, point.longitude]);
        }

        changeRef.current(point);

        if (!reverseGeocode || geocodeRef.current === undefined) return;

        void (async () => {
            const resolved = await reverseGeocodeBoundary(point.latitude, point.longitude);
            if (resolved !== null) geocodeRef.current?.(resolved.address, resolved.area);
        })();
    }, []);

    // The map is built once; `value` is reconciled by the effect below.
    // biome-ignore lint/correctness/useExhaustiveDependencies: mount-only
    useEffect(() => {
        if (!containerRef.current || mapRef.current) return;

        const map = L.map(containerRef.current, { center, zoom });
        L.tileLayer(OSM_URL, { attribution: OSM_ATTRIBUTION, maxZoom: 19 }).addTo(map);
        map.on("click", (event: L.LeafletMouseEvent) => {
            setNotice(null);
            applyPoint(event.latlng.lat, event.latlng.lng, true);
        });
        mapRef.current = map;

        const frame = requestAnimationFrame(() => map.invalidateSize());

        return () => {
            cancelAnimationFrame(frame);
            map.remove();
            mapRef.current = null;
            markerRef.current = null;
        };
    }, []);

    // Reflect a point that arrived from outside the map (a prefill, a reset).
    useEffect(() => {
        const map = mapRef.current;
        if (!map) return;

        if (value === null) {
            if (markerRef.current !== null) {
                map.removeLayer(markerRef.current);
                markerRef.current = null;
            }
            return;
        }

        if (markerRef.current === null) {
            const marker = L.marker([value.latitude, value.longitude], { icon: pinIcon(), draggable: true }).addTo(map);
            marker.on("dragend", () => {
                const at = marker.getLatLng();
                applyPoint(at.lat, at.lng, true);
            });
            markerRef.current = marker;
        } else {
            markerRef.current.setLatLng([value.latitude, value.longitude]);
        }

        map.setView([value.latitude, value.longitude], Math.max(map.getZoom(), 15), { animate: false });
    }, [value, applyPoint]);

    function useDeviceLocation() {
        if (!navigator.geolocation) {
            setNotice("Perangkat ini tidak menyediakan layanan lokasi.");
            return;
        }

        setLocating(true);
        setNotice(null);

        navigator.geolocation.getCurrentPosition(
            (position) => {
                setLocating(false);
                applyPoint(position.coords.latitude, position.coords.longitude, true);
            },
            () => {
                setLocating(false);
                setNotice("Izin lokasi ditolak atau tidak tersedia. Pilih titik di peta.");
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    return (
        <div className={cx("flex flex-col gap-space-sm", className)}>
            <div
                className={cx(
                    "relative overflow-hidden rounded-lg border border-outline-variant bg-surface-container-low",
                    heightClass
                )}
            >
                <section ref={containerRef} className="h-full w-full" aria-label="Pilih titik lokasi" />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-space-sm">
                <button
                    type="button"
                    onClick={useDeviceLocation}
                    disabled={locating}
                    className="flex h-9 items-center gap-space-xs rounded-md border border-outline-variant px-space-md font-label-md text-label-md text-on-surface transition-colors hover:border-secondary hover:bg-surface-container disabled:opacity-50"
                >
                    <MaterialIcon name={locating ? "refresh" : "my_location"} className="text-[16px]" />
                    <span>{locating ? "Mencari lokasi…" : "Lokasi saya"}</span>
                </button>
                <span className="font-label-sm text-label-sm text-secondary">
                    {value ? `${value.latitude.toFixed(5)}, ${value.longitude.toFixed(5)}` : "Belum ada titik lokasi"}
                </span>
            </div>

            {notice !== null ? (
                <span className="font-body-sm text-body-sm text-error">{notice}</span>
            ) : value === null ? (
                <span className="font-body-sm text-body-sm text-secondary">{hint}</span>
            ) : null}
        </div>
    );
}

/** Nominatim is best effort: a failure just leaves the typed address alone. */
async function reverseGeocodeBoundary(
    latitude: number,
    longitude: number
): Promise<{ address: string; area: string | null } | null> {
    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}&accept-language=id`;
        const response = await fetch(url, { headers: { Accept: "application/json" } });
        if (!response.ok) return null;

        const data = (await response.json()) as {
            display_name?: string;
            address?: Record<string, string>;
        };
        const address = data.display_name?.trim();
        if (address === undefined || address === "") return null;

        const a = data.address ?? {};
        const area = a.city_district ?? a.suburb ?? a.village ?? a.town ?? a.city ?? a.county ?? null;

        return { address, area: area ?? null };
    } catch {
        return null;
    }
}
