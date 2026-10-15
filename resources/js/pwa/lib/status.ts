/**
 * Order-state pill tones, ported from App\Support\ClientData.
 *
 * Pesanan, Riwayat and tracking all colour their pills from here, so the three
 * lists cannot drift apart.
 */

export type StatusKey = "transit" | "waiting" | "issue" | "done";

export function statusKey(code: string): StatusKey {
    switch (code) {
        case "AWAITING_CONFIRMATION":
            return "waiting";
        case "ISSUE_REPORTED":
        case "RETURNED":
            return "issue";
        case "RECEIVED":
        case "CLOSED":
            return "done";
        default:
            return "transit";
    }
}

/** Fill and label colour for one pill, as a single utility pair. */
export function statusPillClasses(key: StatusKey): string {
    switch (key) {
        case "waiting":
            return "bg-secondary-container text-on-secondary-container";
        case "issue":
            return "bg-error-container text-on-error-container";
        case "done":
            return "bg-tertiary-fixed text-tertiary";
        default:
            return "bg-primary-fixed text-primary";
    }
}
