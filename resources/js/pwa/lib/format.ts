/** Display helpers shared by the customer and courier screens. */

export function rupiah(amount: number): string {
    return `Rp ${new Intl.NumberFormat("id-ID").format(amount)}`;
}

export function formatFileSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * An API schedule string ("Kamis, 15 Oktober 2024, 13.00 - 15.00 WIB") is one
 * line; the screens show the date and the window apart.
 */
export function splitScheduleLabel(value: string): [string, string] {
    const trimmed = value.trim();

    if (trimmed === "") {
        return ["", ""];
    }

    const separator = trimmed.lastIndexOf(", ");

    if (separator === -1) {
        return [trimmed, ""];
    }

    return [trimmed.slice(0, separator), trimmed.slice(separator + 2)];
}

/** "2 hari lalu" style labels are server-side; this is only for local times. */
export function timeLabel(date: Date): string {
    return new Intl.DateTimeFormat("id-ID", { hour: "2-digit", minute: "2-digit" }).format(date);
}
