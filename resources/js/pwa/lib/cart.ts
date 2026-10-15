/**
 * The customer's basket.
 *
 * Product ids and quantities only: checkout re-reads the catalog and re-prices
 * every line, so a stale basket can never quote a price the server would not
 * charge. Lives in the browser rather than the server cache the native shell
 * used, because the SPA has a client store of its own.
 */

const KEY = "anteraja.client.cart";

export const MAX_QUANTITY = 20;

/** Fired whenever the basket changes, so the header badge stays in step. */
export const CART_EVENT = "anteraja:cart";

export type Cart = Record<number, number>;

export function readCart(): Cart {
    try {
        const raw = window.localStorage.getItem(KEY);

        if (raw === null) {
            return {};
        }

        const parsed: unknown = JSON.parse(raw);

        if (parsed === null || typeof parsed !== "object") {
            return {};
        }

        const cart: Cart = {};

        for (const [key, value] of Object.entries(parsed as Record<string, unknown>)) {
            const id = Number.parseInt(key, 10);
            const quantity = Number(value);

            if (Number.isFinite(id) && Number.isFinite(quantity) && id > 0 && quantity > 0) {
                cart[id] = Math.min(MAX_QUANTITY, Math.floor(quantity));
            }
        }

        return cart;
    } catch {
        return {};
    }
}

function write(cart: Cart): Cart {
    try {
        window.localStorage.setItem(KEY, JSON.stringify(cart));
    } catch {
        // Storage unavailable: the basket simply does not survive the visit.
    }

    window.dispatchEvent(new CustomEvent(CART_EVENT));

    return cart;
}

export function addToCart(productId: number, quantity = 1): Cart {
    const cart = readCart();
    cart[productId] = Math.min(MAX_QUANTITY, (cart[productId] ?? 0) + quantity);

    return write(cart);
}

export function setQuantity(productId: number, quantity: number): Cart {
    const cart = readCart();

    if (quantity <= 0) {
        delete cart[productId];
    } else {
        cart[productId] = Math.min(MAX_QUANTITY, Math.floor(quantity));
    }

    return write(cart);
}

export function clearCart(): void {
    try {
        window.localStorage.removeItem(KEY);
    } catch {
        // Nothing to clear.
    }
}

export function cartCount(cart: Cart): number {
    return Object.values(cart).reduce((total, quantity) => total + quantity, 0);
}
