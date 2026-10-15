import { MaterialIcon } from "@/components/MaterialIcon";

interface StateCardProps {
    message: string;
    onRetry: () => void;
}

/** The card every wired screen falls back to when its API call fails. */
export function StateCard({ message, onRetry }: StateCardProps) {
    return (
        <div className="flex w-full flex-col items-center gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
            <span className="flex h-12 w-12 items-center justify-center rounded-full bg-error-container">
                <MaterialIcon name="cloud_off" className="text-[24px] text-on-error-container" />
            </span>
            <span className="font-headline-sm text-headline-sm text-on-surface">Gagal memuat data</span>
            <p className="text-center font-body-sm text-body-sm leading-relaxed text-secondary">{message}</p>
            <button
                type="button"
                onClick={onRetry}
                className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
            >
                <MaterialIcon name="refresh" className="text-[18px]" />
                Coba Lagi
            </button>
        </div>
    );
}

/** Full-screen placeholder while a screen is fetching its first payload. */
export function ScreenLoading({ label = "Memuat…" }: { label?: string }) {
    return (
        <div className="flex w-full flex-1 items-center justify-center p-space-xl">
            <span className="font-body-md text-body-md text-secondary">{label}</span>
        </div>
    );
}
