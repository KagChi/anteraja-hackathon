import { cx } from "@/lib/cx";

interface AnterajaLogoProps {
    /** Height utility; the width follows the logo's 78:72 ratio. */
    className?: string;
}

/** The official Anteraja logo mark, served from /images/anteraja-logo.png. */
export function AnterajaLogo({ className }: AnterajaLogoProps) {
    return (
        <img
            src="/images/anteraja-logo.png"
            alt="Anteraja"
            width={78}
            height={72}
            className={cx("w-auto shrink-0 object-contain", className)}
        />
    );
}
