/**
 * Device location for the courier screens.
 *
 * The server is what judges a fix; the app's job is to ship the evidence — the
 * latest fix plus the bounded window behind it — with every location-checked
 * action. The window shape matches the native shells' Geolocation stream, so
 * the FRD-06 heuristics see the same signal they always did.
 */

/** Must match App\Services\Gps\FakeGpsDetector::MAX_FIX_WINDOW. */
export const MAX_FIX_WINDOW = 60;

export interface Fix {
    latitude: number;
    longitude: number;
    accuracy: number | null;
    timestamp: number;
}

export interface GpsFields {
    latitude?: number;
    longitude?: number;
    device_timestamp?: number;
    gps_signals?: Fix[];
}

/**
 * Stream fixes while a screen is mounted.
 *
 * @returns a stop function; a device without geolocation simply never fires.
 */
export function startFixWatch(onFix: (fix: Fix) => void): () => void {
    if (!("geolocation" in navigator)) {
        return () => undefined;
    }

    const id = navigator.geolocation.watchPosition(
        (position) => {
            onFix({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: Number.isFinite(position.coords.accuracy) ? position.coords.accuracy : null,
                timestamp: position.timestamp
            });
        },
        () => undefined,
        { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 }
    );

    return () => navigator.geolocation.clearWatch(id);
}

/** Keep the newest fix at the end and the window bounded. */
export function appendFix(window: Fix[], fix: Fix): Fix[] {
    const next = [...window, fix];

    return next.length > MAX_FIX_WINDOW ? next.slice(next.length - MAX_FIX_WINDOW) : next;
}

/** The body every depart / arrive / proof action ships. */
export function gpsFields(window: Fix[], latest: Fix | null): GpsFields {
    if (latest === null) {
        return {};
    }

    return {
        latitude: latest.latitude,
        longitude: latest.longitude,
        device_timestamp: latest.timestamp,
        gps_signals: window
    };
}

/** Great-circle distance in metres, mirroring App\Support\GeoDistance. */
export function haversineMeters(aLat: number, aLng: number, bLat: number, bLng: number): number {
    const radius = 6371000;
    const toRad = (degrees: number) => (degrees * Math.PI) / 180;
    const dLat = toRad(bLat - aLat);
    const dLng = toRad(bLng - aLng);
    const lat1 = toRad(aLat);
    const lat2 = toRad(bLat);

    const h = Math.sin(dLat / 2) ** 2 + Math.sin(dLng / 2) ** 2 * Math.cos(lat1) * Math.cos(lat2);

    return 2 * radius * Math.asin(Math.min(1, Math.sqrt(h)));
}

/** How far the courier is from the recipient's door, in the app's words. */
export function distanceLabel(meters: number): string {
    if (meters < 1000) {
        return `${Math.round(meters)} m dari titik penerima`;
    }

    return `${(meters / 1000).toFixed(1).replace(".", ",")} km dari titik penerima`;
}

export function accuracyLabel(accuracy: number | null): string | null {
    return accuracy === null ? null : `Akurasi sinyal ±${Math.round(accuracy)} m`;
}

/** One-shot fix, used by the distance chip on the task board. */
export function currentPosition(): Promise<{ latitude: number; longitude: number } | null> {
    if (!("geolocation" in navigator)) {
        return Promise.resolve(null);
    }

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (position) => resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
}
