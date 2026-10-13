import { fileURLToPath, URL } from "node:url";
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import viteReact from "@vitejs/plugin-react";

// NativePHP's Vite plugin exists for the Android and iOS bundles only: it
// rewrites the dev-server host for hot reload, redirects the build output on
// iOS, and requires axios to be a real dependency. The Android shells load the
// deployed site, so the bundle the server hands them has to stay a plain
// browser build; keeping the plugin behind the mode keeps that explicit and
// stops iOS-only rewrites from leaking into a web build.
export default defineConfig(async ({ mode }) => {
    const nativeBuild = mode === "android" || mode === "ios";
    const nativephp = nativeBuild
        ? await import("./vendor/nativephp/mobile/resources/js/vite-plugin.js")
        : null;

    return {
        plugins: [
            laravel({
                input: ["resources/js/app.tsx"],
                refresh: true,
                ...(nativephp ? { hotFile: nativephp.nativephpHotFile() } : {})
            }),
            viteReact(),
            ...(nativephp ? [nativephp.nativephpMobile()] : [])
        ],
        resolve: {
            alias: {
                "@": fileURLToPath(new URL("./resources/js", import.meta.url))
            }
        }
    };
});

