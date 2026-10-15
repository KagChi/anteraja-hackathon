/*
 * The shell worker behind both installable apps.
 *
 * It exists so the apps open from the home screen, keep their assets, and say
 * something honest when the phone has no connection. Data is never cached: the
 * API answers what is true right now, and a stale order list would be a lie.
 *
 * Served from the origin root, so its scope covers /client/* and /courier/*
 * (and everything else on the host, which is harmless).
 */

const CACHE = "anteraja-shell-v1";

const PRECACHE = [
    "/offline.html",
    "/client.webmanifest",
    "/courier.webmanifest",
    "/icons/client-192.png",
    "/icons/kurir-192.png"
];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", (event) => {
    const request = event.request;

    if (request.method !== "GET") {
        return;
    }

    const url = new URL(request.url);

    // Third-party assets (fonts, map tiles) and the API stay untouched.
    if (url.origin !== self.location.origin || url.pathname.startsWith("/api/")) {
        return;
    }

    if (request.mode === "navigate") {
        event.respondWith(navigation(request));
        return;
    }

    if (isAsset(url.pathname)) {
        event.respondWith(cacheFirst(request));
    }
});

/** Network first, so a deploy is never masked; the cache is the fallback. */
async function navigation(request) {
    const cache = await caches.open(CACHE);

    try {
        const response = await fetch(request);

        if (response.ok) {
            cache.put(request, response.clone());
        }

        return response;
    } catch {
        const cached = await cache.match(request, { ignoreSearch: true });

        return cached ?? (await cache.match("/offline.html")) ?? Response.error();
    }
}

/** Hashed build assets and images never change under their URL. */
async function cacheFirst(request) {
    const cache = await caches.open(CACHE);
    const cached = await cache.match(request);

    if (cached !== undefined) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok && response.type === "basic") {
        cache.put(request, response.clone());
    }

    return response;
}

function isAsset(pathname) {
    return (
        pathname.startsWith("/build/") ||
        pathname.startsWith("/icons/") ||
        pathname.startsWith("/images/") ||
        pathname.startsWith("/fonts/")
    );
}
