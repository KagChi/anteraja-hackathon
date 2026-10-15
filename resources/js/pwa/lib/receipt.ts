import type { OrderReceipt } from "./api";

/**
 * The confirmation the customer app shows once a parcel is created.
 *
 * It is carried in the browser, not the server cache the native shell used: the
 * order success screen is the only reader, and it is only meaningful right after
 * the order was placed.
 */

const KEY = "anteraja.client.receipt";

export function putReceipt(receipt: OrderReceipt): void {
    try {
        window.sessionStorage.setItem(KEY, JSON.stringify(receipt));
    } catch {
        // Storage unavailable; the success screen falls back to the order list.
    }
}

export function readReceipt(resi: string): OrderReceipt | null {
    try {
        const raw = window.sessionStorage.getItem(KEY);

        if (raw === null) {
            return null;
        }

        const receipt = JSON.parse(raw) as OrderReceipt;

        return receipt.tracking_number === resi ? receipt : null;
    } catch {
        return null;
    }
}
