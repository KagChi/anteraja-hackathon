import { MaterialIcon } from "@/components/MaterialIcon";

/**
 * A failed mutation, shown beside the action instead of replacing the screen,
 * so a half-filled form keeps what was typed.
 */
export function ActionError({ message }: { message: string | null }) {
    if (message === null) {
        return null;
    }

    return (
        <div className="flex w-full items-start gap-space-sm rounded-lg bg-error-container p-space-md">
            <MaterialIcon name="error_outline" className="text-[18px] text-on-error-container" />
            <span className="flex-1 font-body-sm text-body-sm leading-snug text-on-error-container">{message}</span>
        </div>
    );
}
