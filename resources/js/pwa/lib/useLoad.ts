import { useCallback, useEffect, useRef, useState } from "react";
import { firstError } from "./api";

export interface LoadState<T> {
    data: T | null;
    /** Message for the retry card, or null while the call is fine. */
    error: string | null;
    loading: boolean;
    reload: () => void;
}

/**
 * The one read every screen shares: call the API on mount, keep the data, and
 * expose a retry that re-runs the same call.
 *
 * `key` is what the call depends on (a resi, a tab). The loader itself is read
 * through a ref, so a re-render never re-fetches and a stale response never
 * overwrites a newer one.
 */
export function useLoad<T>(loader: () => Promise<T>, key: string): LoadState<T> {
    const loaderRef = useRef(loader);
    loaderRef.current = loader;

    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [nonce, setNonce] = useState(0);

    // biome-ignore lint/correctness/useExhaustiveDependencies: the loader is read through a ref, so the key and the retry nonce are what re-run the call
    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError(null);

        loaderRef
            .current()
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    setLoading(false);
                }
            })
            .catch((failure: unknown) => {
                if (!cancelled) {
                    setData(null);
                    setError(firstError(failure));
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [key, nonce]);

    const reload = useCallback(() => setNonce((value) => value + 1), []);

    return { data, error, loading, reload };
}
