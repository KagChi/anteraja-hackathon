/** The customer-service channel behind the help actions and the resend flow. */

const SUPPORT_WHATSAPP = "6281288991234";

export const HELP_MESSAGE = "Halo, saya butuh bantuan dengan pengiriman Anteraja Frozen.";

export function whatsappLink(text: string): string {
    return `https://wa.me/${SUPPORT_WHATSAPP}?text=${encodeURIComponent(text)}`;
}

export function whatsappMessageFor(resi: string): string {
    return `Halo, saya butuh bantuan untuk pesanan ${resi}.`;
}
