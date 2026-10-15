/**
 * Where each realm keeps its API token.
 *
 * The two apps share one origin, so the keys are namespaced per realm: signing
 * out of the courier app never touches the customer's session. The token is the
 * same 30-day Sanctum bearer token the mobile shells used, handed out by the
 * /api/{realm}/auth/login endpoints.
 */

export type Realm = "client" | "courier";

const TOKEN_KEYS: Record<Realm, string> = {
    client: "anteraja.client.token",
    courier: "anteraja.courier.token"
};

const LOGIN_PATHS: Record<Realm, string> = {
    client: "/client/login",
    courier: "/courier/login"
};

/** Where a signed-in visitor belongs when they open the app. */
const HOME_PATHS: Record<Realm, string> = {
    client: "/client/orders",
    courier: "/courier/tasks"
};

/** Where a visitor without a token belongs when they open the app. */
const ENTRY_PATHS: Record<Realm, string> = {
    client: "/client/landing",
    courier: "/courier/login"
};

export function realms(): Realm[] {
    return ["client", "courier"];
}

export function realmFromPath(pathname: string): Realm | null {
    if (pathname === "/client" || pathname.startsWith("/client/")) {
        return "client";
    }

    if (pathname === "/courier" || pathname.startsWith("/courier/")) {
        return "courier";
    }

    return null;
}

export function getToken(realm: Realm): string | null {
    try {
        const token = window.localStorage.getItem(TOKEN_KEYS[realm]);

        return token === null || token === "" ? null : token;
    } catch {
        return null;
    }
}

export function setToken(realm: Realm, token: string): void {
    try {
        window.localStorage.setItem(TOKEN_KEYS[realm], token);
    } catch {
        // A browser in private mode without storage still works for this page;
        // the visitor is simply asked to sign in again next visit.
    }
}

export function clearToken(realm: Realm): void {
    try {
        window.localStorage.removeItem(TOKEN_KEYS[realm]);
    } catch {
        // Nothing to clear.
    }
}

export function isSignedIn(realm: Realm): boolean {
    return getToken(realm) !== null;
}

export function loginPath(realm: Realm, next?: string): string {
    const base = LOGIN_PATHS[realm];

    return next === undefined || next === "" ? base : `${base}?next=${encodeURIComponent(next)}`;
}

export function signedInHome(realm: Realm): string {
    return HOME_PATHS[realm];
}

export function signedOutEntry(realm: Realm): string {
    return ENTRY_PATHS[realm];
}

/**
 * The in-app path to come back to after signing in, read from ?next=.
 *
 * Only paths inside the realm are accepted, so a crafted link cannot send the
 * browser off-origin after a login.
 */
export function nextPath(query: URLSearchParams, realm: Realm, fallback: string): string {
    const next = query.get("next");

    if (next === null || next === "" || !next.startsWith(`/${realm}/`)) {
        return fallback;
    }

    return next;
}
