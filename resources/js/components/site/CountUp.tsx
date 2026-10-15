import { useEffect, useRef, useState } from "react";

interface CountUpProps {
    value: number;
    suffix?: string;
    durationMs?: number;
}

/** Counts a headline number up once, the first time it is seen. */
export function CountUp({ value, suffix = "", durationMs = 1400 }: CountUpProps) {
    const ref = useRef<HTMLSpanElement | null>(null);
    const [display, setDisplay] = useState(0);
    const started = useRef(false);

    useEffect(() => {
        const node = ref.current;

        if (!node || typeof IntersectionObserver === "undefined") {
            setDisplay(value);
            return;
        }

        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            setDisplay(value);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting || started.current) continue;

                started.current = true;
                const startedAt = performance.now();

                const step = (now: number) => {
                    const progress = Math.min(1, (now - startedAt) / durationMs);
                    // easeOutExpo: fast start, gentle landing.
                    const eased = progress === 1 ? 1 : 1 - 2 ** (-10 * progress);
                    setDisplay(Math.round(value * eased));

                    if (progress < 1) requestAnimationFrame(step);
                };

                requestAnimationFrame(step);
                observer.disconnect();
            }
        });

        observer.observe(node);

        return () => observer.disconnect();
    }, [value, durationMs]);

    return (
        <span ref={ref}>
            {display}
            {suffix}
        </span>
    );
}
