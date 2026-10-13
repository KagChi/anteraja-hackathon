/** Local ISO date (YYYY-MM-DD); toISOString() would shift the day in +07:00. */
export function todayIso(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    return `${now.getFullYear()}-${month}-${day}`;
}

/** Shift an ISO date by whole days, staying in local time. */
export function addDaysIso(iso: string, days: number): string {
    const shifted = parse(iso);
    shifted.setDate(shifted.getDate() + days);
    const month = String(shifted.getMonth() + 1).padStart(2, "0");
    const day = String(shifted.getDate()).padStart(2, "0");
    return `${shifted.getFullYear()}-${month}-${day}`;
}

const LONG_DATE = new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "long", year: "numeric" });
const SHORT_DATE = new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", year: "numeric" });

function parse(iso: string): Date {
    return new Date(`${iso}T00:00:00`);
}

/** "13 Oktober 2026". */
export function formatLongDate(iso: string): string {
    return LONG_DATE.format(parse(iso));
}

/** "13 Okt 2026", for the compact filter chip. */
export function formatShortDate(iso: string): string {
    return SHORT_DATE.format(parse(iso));
}
