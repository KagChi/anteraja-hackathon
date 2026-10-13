export interface NavItem {
    label: string;
    icon: string;
    href: string;
    /** Show the small notification dot on the icon. */
    badge?: boolean;
}

/** True when the href is the current route or a parent of it. */
export function isActive(pathname: string, href: string): boolean {
    return pathname === href || pathname.startsWith(`${href}/`);
}
