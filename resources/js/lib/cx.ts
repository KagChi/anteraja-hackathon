export type ClassValue = string | false | null | undefined;

/**
 * Join conditional class names. A tiny local helper so components can compose
 * Tailwind classes without pulling in an extra dependency.
 */
export function cx(...parts: ClassValue[]): string {
    return parts.filter(Boolean).join(" ");
}
