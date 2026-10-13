import "../css/app.css";
import "./bootstrap";

import { createInertiaApp, type ResolvedComponent } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";

const appName = "Example App";

void createInertiaApp({
    title: (title) => (title ? `${appName} - ${title}` : appName),
    resolve: (name) =>
        resolvePageComponent<ResolvedComponent>(
            `./pages/${name}.tsx`,
            import.meta.glob<ResolvedComponent>("./pages/**/*.tsx")
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    }
});
