/**
 * Staff session for the admin console.
 *
 * Mounted by AdminLayout rather than around the whole app: the courier and
 * customer screens are not authenticated, and asking the API for a session on
 * every page would bounce them to the login screen.
 */
import { createContext, type ReactNode, useCallback, useContext, useEffect, useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ApiError, api, type StaffUser } from "@/lib/api";

interface AuthContextValue {
    user: StaffUser;
    signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function useAuth(): AuthContextValue {
    const value = useContext(AuthContext);

    if (value === null) {
        throw new Error("useAuth must be used inside <AuthProvider>.");
    }

    return value;
}

type SessionStatus = "loading" | "ready" | "error";

/** Full-page placeholder shown while the session loads or after it fails. */
function SessionScreen({ icon, message, onRetry }: { icon: string; message: string; onRetry?: () => void }) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-surface px-margin">
            <div className="flex max-w-sm flex-col items-center gap-space-sm text-center">
                <MaterialIcon name={icon} className="text-[28px] text-secondary" />
                <p className="font-body-md text-body-md text-secondary">{message}</p>
                {onRetry ? (
                    <button
                        type="button"
                        onClick={onRetry}
                        className="mt-space-xs h-9 rounded-md bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        Coba lagi
                    </button>
                ) : null}
            </div>
        </div>
    );
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<StaffUser | null>(null);
    const [status, setStatus] = useState<SessionStatus>("loading");
    const [message, setMessage] = useState("Memuat sesi…");

    const load = useCallback(async () => {
        setStatus("loading");
        setMessage("Memuat sesi…");

        try {
            const { user: current } = await api.auth.me();
            setUser(current);
            setStatus("ready");
        } catch (error) {
            // The session expired after the page was already open; send the
            // browser back through the login screen for a fresh one.
            if (error instanceof ApiError && error.status === 401) {
                window.location.assign("/login");
                return;
            }

            setMessage(error instanceof ApiError ? error.message : "Tidak dapat memuat sesi.");
            setStatus("error");
        }
    }, []);

    useEffect(() => {
        void load();
    }, [load]);

    const signOut = useCallback(async () => {
        try {
            await api.auth.logout();
        } catch {
            // The session is unusable either way; still leave the console.
        }

        window.location.assign("/login");
    }, []);

    if (status === "loading") {
        return <SessionScreen icon="progress_activity" message="Memuat sesi…" />;
    }

    if (status === "error" || user === null) {
        return <SessionScreen icon="cloud_off" message={message} onRetry={load} />;
    }

    return <AuthContext.Provider value={{ user, signOut }}>{children}</AuthContext.Provider>;
}
