import "../../css/app.css";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { PwaApp } from "./App";
import { registerServiceWorker } from "./lib/service-worker";

const root = document.getElementById("pwa-root");
const app = document.body.dataset.app;

if (root !== null && (app === "client" || app === "courier")) {
    createRoot(root).render(
        <StrictMode>
            <PwaApp realm={app} />
        </StrictMode>
    );

    registerServiceWorker();
}
