import type { ElementType, ReactNode } from "react";
import { useEffect, useRef, useState } from "react";
import { cx } from "@/lib/cx";

interface RevealProps {
    children: ReactNode;
    /** Seconds to wait before the element animates in, for staggering a grid. */
    delay?: number;
    className?: string;
    as?: ElementType;
}

/**
 * Fades a section in as it scrolls into view.
 *
 * It is progressive: without IntersectionObserver, or when the visitor asks for
 * reduced motion, the content simply renders visible. Nothing on the page
 * depends on the animation having run.
 */
export function Reveal({ children, delay = 0, className, as: Tag = "div" }: RevealProps) {
    const ref = useRef<HTMLElement | null>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const node = ref.current;

        if (!node || typeof IntersectionObserver === "undefined") {
            setVisible(true);
            return;
        }

        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            setVisible(true);
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        setVisible(true);
                        observer.disconnect();
                    }
                }
            },
            { rootMargin: "-60px 0px -10% 0px", threshold: 0.05 }
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    return (
        <Tag
            ref={ref as never}
            className={cx("reveal", visible && "is-visible", className)}
            style={delay > 0 ? { transitionDelay: delay + "ms" } : undefined}
        >
            {children}
        </Tag>
    );
}
