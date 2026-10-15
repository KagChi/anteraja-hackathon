import { useEffect, useState } from "react";
import { appendFix, type Fix, startFixWatch } from "./geo";

/**
 * The bounded fix window a courier screen ships with its next action.
 *
 * The watch starts on mount and stops on unmount: the browser only reports
 * location while the screen is open, which is exactly the evidence the server
 * needs for the action that screen performs.
 */
export function useFixWindow(): { fixes: Fix[]; latest: Fix | null } {
    const [fixes, setFixes] = useState<Fix[]>([]);

    useEffect(() => startFixWatch((fix) => setFixes((current) => appendFix(current, fix))), []);

    return { fixes, latest: fixes.length === 0 ? null : fixes[fixes.length - 1] };
}
