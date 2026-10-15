/**
 * Registers the shell worker the PWA installs from.
 *
 * Production only: a service worker in front of the Vite dev server would serve
 * stale bundles to the developer. The worker file itself lives in public/ and is
 * served from the origin root, which is what gives it the whole site as scope.
 */

export function registerServiceWorker(): void {
    if (!import.meta.env.PROD || !("serviceWorker" in navigator)) {
        return;
    }

    window.addEventListener("load", () => {
        void navigator.serviceWorker.register("/sw.js").catch(() => undefined);
    });
}
