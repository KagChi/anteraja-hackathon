import { fileURLToPath, URL } from "node:url";
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import viteReact from "@vitejs/plugin-react";

// Two entries share this build: the Inertia app behind the landing page, the
// blog and the admin console, and the PWA bundle behind /client/* and
// /courier/*. The PWA is a client-side React SPA, so it is its own entry rather
// than a page the Inertia resolver would look for.
export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/js/app.tsx", "resources/js/pwa/main.tsx"],
            refresh: true
        }),
        viteReact()
    ],
    resolve: {
        alias: {
            "@": fileURLToPath(new URL("./resources/js", import.meta.url))
        }
    }
});

