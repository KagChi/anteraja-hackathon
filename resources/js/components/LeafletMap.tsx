import L from "leaflet";
import { useEffect, useRef } from "react";
import type { Tone } from "@/lib/api";
import { cx } from "@/lib/cx";
import "leaflet/dist/leaflet.css";

/**
 * One point on the map. `tone` colours the dot using the same verdict palette
 * as the rest of the console, so a red dot means the same thing here as it does
 * in a table row.
 */
export interface MapMarker {
    lat: number;
    lng: number;
    tone?: Tone;
    popup?: string;
}

/** The geofence the courier action was judged against. */
export interface MapCircle {
    lat: number;
    lng: number;
    radiusM: number;
}

interface LeafletMapProps {
    markers?: MapMarker[];
    circle?: MapCircle | null;
    /** Centre when there is nothing to fit; ignored while `fit` is on. */
    center?: [number, number];
    zoom?: number;
    /** Frame every marker and the circle instead of using `center`/`zoom`. */
    fit?: boolean;
    heightClass?: string;
    className?: string;
    /** Shown instead of the map when nothing has coordinates. */
    emptyLabel?: string;
}

/**
 * Dots are drawn as circle markers rather than Leaflet's default pins: the pin
 * icon is a PNG whose URL Vite would have to be told about, and a coloured dot
 * carries the verdict better anyway.
 */
const TONE_COLOR: Record<Tone, string> = {
    neutral: "#757779",
    muted: "#757779",
    warning: "#b26a00",
    info: "#1f6feb",
    danger: "#ba1a1a",
    dark: "#44474a",
    success: "#2e7d32"
};

const OSM_URL = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

/**
 * A small Leaflet wrapper.
 *
 * The map is created once and then patched: the layer group is emptied and
 * refilled whenever the markers or circle change, so a polling or refreshed
 * dataset never tears down the tile layer underneath it.
 */
export function LeafletMap({
    markers = [],
    circle = null,
    center = [-6.2, 106.8],
    zoom = 13,
    fit = true,
    heightClass = "h-72",
    className,
    emptyLabel = "Belum ada titik lokasi untuk ditampilkan."
}: LeafletMapProps) {
    const containerRef = useRef<HTMLElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const layerRef = useRef<L.LayerGroup | null>(null);
    const hasData = markers.length > 0 || circle !== null;

    useEffect(() => {
        if (!containerRef.current || mapRef.current) return;

        // A throwaway initial view: the effect below sets the real one once it
        // has the markers, so the map never depends on the props at mount.
        const map = L.map(containerRef.current, {
            center: [0, 0],
            zoom: 2,
            attributionControl: true,
            zoomControl: true
        });

        L.tileLayer(OSM_URL, { attribution: OSM_ATTRIBUTION, maxZoom: 19 }).addTo(map);
        layerRef.current = L.layerGroup().addTo(map);
        mapRef.current = map;

        // The container is often laid out a tick after mount; without this the
        // tiles paint into a zero-sized box.
        const frame = requestAnimationFrame(() => map.invalidateSize());

        return () => {
            cancelAnimationFrame(frame);
            map.remove();
            mapRef.current = null;
            layerRef.current = null;
        };
    }, []);

    useEffect(() => {
        const map = mapRef.current;
        const layer = layerRef.current;
        if (!map || !layer) return;

        layer.clearLayers();

        const bounds = L.latLngBounds([]);

        if (circle !== null) {
            const fence = L.circle([circle.lat, circle.lng], {
                radius: circle.radiusM,
                color: "#1f6feb",
                weight: 1,
                fillColor: "#1f6feb",
                fillOpacity: 0.08,
                dashArray: "4 4"
            }).addTo(layer);
            bounds.extend(fence.getBounds());
        }

        for (const marker of markers) {
            const dot = L.circleMarker([marker.lat, marker.lng], {
                radius: 7,
                color: "#ffffff",
                weight: 2,
                fillColor: TONE_COLOR[marker.tone ?? "neutral"],
                fillOpacity: 1
            }).addTo(layer);

            if (marker.popup) dot.bindPopup(marker.popup);
            bounds.extend([marker.lat, marker.lng]);
        }

        if (fit && bounds.isValid()) {
            map.fitBounds(bounds.pad(0.25), { maxZoom: 16 });
        } else if (markers.length === 0 && circle === null) {
            map.setView(center, zoom);
        } else if (markers.length === 1 && circle === null) {
            map.setView([markers[0].lat, markers[0].lng], zoom);
        }

        const frame = requestAnimationFrame(() => map.invalidateSize());
        return () => cancelAnimationFrame(frame);
        // center/zoom are read here too, so re-run when they change.
    }, [markers, circle, fit, center, zoom]);

    return (
        <div
            className={cx(
                "relative overflow-hidden rounded-lg border border-outline-variant bg-surface-container-low",
                heightClass,
                className
            )}
        >
            <section ref={containerRef} aria-label="Peta lokasi" className="h-full w-full" />
            {!hasData ? (
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center bg-surface-container-low/90 p-space-md text-center">
                    <span className="font-body-sm text-body-sm text-secondary">{emptyLabel}</span>
                </div>
            ) : null}
        </div>
    );
}
