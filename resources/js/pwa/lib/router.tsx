import { createContext, type MouseEvent, type ReactNode, useContext, useEffect, useMemo, useState } from "react";

export interface NavigateOptions {
    replace?: boolean;
}

interface RouterValue {
    /** Current pathname, without the query string. */
    path: string;
    query: URLSearchParams;
    navigate: (to: string, options?: NavigateOptions) => void;
}

const RouterContext = createContext<RouterValue | null>(null);

function currentPath(): string {
    return window.location.pathname;
}

/**
 * History-based routing for the PWA bundle.
 *
 * The server hands every /client/* and /courier/* path the same shell, so the
 * app owns the route table. This is deliberately small: pushState/popstate, a
 * path matcher, and a Link that stays out of the way for external hrefs.
 */
export function RouterProvider({ children }: { children: ReactNode }) {
    const [path, setPath] = useState(currentPath);

    useEffect(() => {
        // The app restores its own scroll position on every navigation, so the
        // browser must not restore the previous document's scroll for us.
        if ("scrollRestoration" in window.history) {
            window.history.scrollRestoration = "manual";
        }

        const onPop = () => setPath(currentPath());
        window.addEventListener("popstate", onPop);

        return () => window.removeEventListener("popstate", onPop);
    }, []);

    const value = useMemo<RouterValue>(() => {
        const navigate = (to: string, options: NavigateOptions = {}) => {
            const url = new URL(to, window.location.origin);
            const target = `${url.pathname}${url.search}`;
            const current = `${window.location.pathname}${window.location.search}`;

            if (target === current) {
                return;
            }

            if (options.replace === true) {
                window.history.replaceState(null, "", target);
            } else {
                window.history.pushState(null, "", target);
            }

            setPath(url.pathname);
            window.scrollTo(0, 0);
        };

        return { path, query: new URLSearchParams(window.location.search), navigate };
    }, [path]);

    return <RouterContext.Provider value={value}>{children}</RouterContext.Provider>;
}

export function useRouter(): RouterValue {
    const value = useContext(RouterContext);

    if (value === null) {
        throw new Error("useRouter must be used inside <RouterProvider>.");
    }

    return value;
}

/**
 * Match "/client/tracking/:resi" against "/client/tracking/AJ-1".
 *
 * @returns the decoded params, or null when the pattern does not fit.
 */
export function matchPath(pattern: string, path: string): Record<string, string> | null {
    const patternParts = pattern.split("/").filter((part) => part !== "");
    const pathParts = path.split("/").filter((part) => part !== "");

    if (patternParts.length !== pathParts.length) {
        return null;
    }

    const params: Record<string, string> = {};

    for (let index = 0; index < patternParts.length; index += 1) {
        const expected = patternParts[index];
        const actual = pathParts[index];

        if (expected.startsWith(":")) {
            params[expected.slice(1)] = decodeURIComponent(actual);
            continue;
        }

        if (expected !== actual) {
            return null;
        }
    }

    return params;
}

interface LinkProps {
    href: string;
    className?: string;
    replace?: boolean;
    "aria-label"?: string;
    children: ReactNode;
}

/** In-app anchor: internal hrefs go through the router, external ones do not. */
export function Link({ href, className, replace = false, children, ...rest }: LinkProps) {
    const { navigate } = useRouter();
    const external = !href.startsWith("/");

    const onClick = (event: MouseEvent<HTMLAnchorElement>) => {
        if (external || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        navigate(href, { replace });
    };

    return (
        <a href={href} className={className} onClick={onClick} {...rest}>
            {children}
        </a>
    );
}
