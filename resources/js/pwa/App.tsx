import { type ComponentType, lazy, Suspense, useEffect, useMemo } from "react";
import { type Chrome, Shell } from "./components/Shell";
import { ScreenLoading } from "./components/StateCard";
import { UNAUTHORIZED_EVENT } from "./lib/api";
import { matchPath, RouterProvider, useRouter } from "./lib/router";
import { isSignedIn, loginPath, type Realm, signedInHome } from "./lib/token";

export interface ScreenProps {
    params: Record<string, string>;
    query: URLSearchParams;
}

interface RouteEntry {
    path: string;
    chrome: Chrome;
    title: string;
    /**
     * Screens that need a session. Without a token the visitor is walked
     * through the realm's login (or register, for checkout) and sent back.
     */
    guard?: boolean;
    /** Where an unauthenticated visitor goes instead of the realm's login. */
    guardTarget?: string;
    /** Where the back affordance goes when the screen is pushed. */
    back?: (params: Record<string, string>) => string;
    action?: "cart" | "help";
    screen: ComponentType<ScreenProps>;
}

type ScreenModule = Promise<{ default: ComponentType<ScreenProps> }>;

const ROUTES: Record<Realm, RouteEntry[]> = {
    client: [
        {
            path: "/client",
            chrome: "plain",
            title: "Anteraja Frozen",
            screen: lazy(() => import("./screens/client/Start"))
        },
        {
            path: "/client/landing",
            chrome: "tabs",
            title: "Anteraja Frozen",
            screen: lazy(() => import("./screens/client/Landing") as ScreenModule)
        },
        {
            path: "/client/catalog",
            chrome: "back",
            title: "Katalog Frozen",
            back: () => "/client/landing",
            action: "cart",
            screen: lazy(() => import("./screens/client/Catalog") as ScreenModule)
        },
        {
            path: "/client/catalog/:slug",
            chrome: "back",
            title: "Detail Produk",
            back: () => "/client/catalog",
            action: "cart",
            screen: lazy(() => import("./screens/client/ProductDetail") as ScreenModule)
        },
        {
            path: "/client/login",
            chrome: "plain",
            title: "Masuk",
            screen: lazy(() => import("./screens/client/Login"))
        },
        {
            path: "/client/register",
            chrome: "back",
            title: "Buat Akun",
            back: () => "/client/login",
            screen: lazy(() => import("./screens/client/Register") as ScreenModule)
        },
        {
            path: "/client/checkout",
            chrome: "back",
            title: "Checkout",
            guard: true,
            guardTarget: "/client/register?next=/client/checkout",
            back: () => "/client/catalog",
            screen: lazy(() => import("./screens/client/Checkout") as ScreenModule)
        },
        {
            path: "/client/order-success",
            chrome: "title",
            title: "Pesanan Dibuat",
            screen: lazy(() => import("./screens/client/OrderSuccess") as ScreenModule)
        },
        {
            path: "/client/orders",
            chrome: "tabs",
            title: "Pesanan",
            guard: true,
            screen: lazy(() => import("./screens/client/Orders") as ScreenModule)
        },
        {
            path: "/client/history",
            chrome: "tabs",
            title: "Riwayat",
            guard: true,
            screen: lazy(() => import("./screens/client/History") as ScreenModule)
        },
        {
            path: "/client/profile",
            chrome: "tabs",
            title: "Profil",
            guard: true,
            screen: lazy(() => import("./screens/client/Profile") as ScreenModule)
        },
        {
            path: "/client/tracking/:resi",
            chrome: "back",
            title: "Pelacakan Kurir Beku",
            guard: true,
            back: () => "/client/orders",
            action: "help",
            screen: lazy(() => import("./screens/client/Tracking") as ScreenModule)
        },
        {
            path: "/client/schedule/:resi",
            chrome: "back",
            title: "Konfirmasi Jadwal",
            guard: true,
            back: () => "/client/orders",
            action: "help",
            screen: lazy(() => import("./screens/client/Schedule") as ScreenModule)
        },
        {
            path: "/client/issue/:resi",
            chrome: "back",
            title: "Kendala Pengiriman",
            guard: true,
            back: () => "/client/orders",
            action: "help",
            screen: lazy(() => import("./screens/client/Issue") as ScreenModule)
        }
    ],
    courier: [
        {
            path: "/courier",
            chrome: "plain",
            title: "Anteraja Kurir",
            screen: lazy(() => import("./screens/courier/Start"))
        },
        {
            path: "/courier/login",
            chrome: "plain",
            title: "Masuk",
            screen: lazy(() => import("./screens/courier/Login"))
        },
        {
            path: "/courier/tasks",
            chrome: "tabs",
            title: "Daftar Tugas",
            guard: true,
            screen: lazy(() => import("./screens/courier/Tasks") as ScreenModule)
        },
        {
            path: "/courier/history",
            chrome: "tabs",
            title: "Riwayat Pengiriman",
            guard: true,
            screen: lazy(() => import("./screens/courier/History") as ScreenModule)
        },
        {
            path: "/courier/profile",
            chrome: "tabs",
            title: "Profil Kurir",
            guard: true,
            screen: lazy(() => import("./screens/courier/Profile") as ScreenModule)
        },
        {
            path: "/courier/delivery/:resi",
            chrome: "back",
            title: "Detail Paket",
            guard: true,
            back: () => "/courier/tasks",
            screen: lazy(() => import("./screens/courier/Delivery") as ScreenModule)
        },
        {
            path: "/courier/proof/:resi",
            chrome: "back",
            title: "Konfirmasi Pengiriman",
            guard: true,
            back: (params) => `/courier/delivery/${params.resi}`,
            screen: lazy(() => import("./screens/courier/Proof") as ScreenModule)
        },
        {
            path: "/courier/issue/:resi",
            chrome: "back",
            title: "Lapor Masalah",
            guard: true,
            back: (params) => `/courier/delivery/${params.resi}`,
            screen: lazy(() => import("./screens/courier/Issue") as ScreenModule)
        }
    ]
};

/** Splash shown for the instant a gate or a redirect decides where to go. */
function Redirect({ to }: { to: string }) {
    const { navigate } = useRouter();

    useEffect(() => {
        navigate(to, { replace: true });
    }, [navigate, to]);

    return <ScreenLoading label="Membuka aplikasi…" />;
}

function Routed({ realm }: { realm: Realm }) {
    const { path, query, navigate } = useRouter();

    // An expired or revoked token is not a screen error: the API layer drops it
    // and raises this, and the app walks the visitor back through login.
    useEffect(() => {
        const onUnauthorized = (event: Event) => {
            const detail = (event as CustomEvent<{ realm: Realm }>).detail;

            if (detail.realm !== realm) {
                return;
            }

            navigate(loginPath(realm, window.location.pathname + window.location.search), { replace: true });
        };

        window.addEventListener(UNAUTHORIZED_EVENT, onUnauthorized);

        return () => window.removeEventListener(UNAUTHORIZED_EVENT, onUnauthorized);
    }, [navigate, realm]);

    const match = useMemo(() => {
        for (const route of ROUTES[realm]) {
            const params = matchPath(route.path, path);

            if (params !== null) {
                return { route, params };
            }
        }

        return null;
    }, [path, realm]);

    if (match === null) {
        return <Redirect to={`/${realm}`} />;
    }

    const { route, params } = match;
    const Screen = route.screen;

    if (route.guard === true && !isSignedIn(realm)) {
        return <Redirect to={route.guardTarget ?? loginPath(realm, `${path}${window.location.search}`)} />;
    }

    return (
        <Shell
            realm={realm}
            chrome={route.chrome}
            title={route.title}
            back={route.back?.(params)}
            action={route.action}
        >
            <Suspense fallback={<ScreenLoading />}>
                <Screen params={params} query={query} />
            </Suspense>
        </Shell>
    );
}

export function PwaApp({ realm }: { realm: Realm }) {
    return (
        <RouterProvider>
            <Routed realm={realm} />
        </RouterProvider>
    );
}

export { signedInHome };
