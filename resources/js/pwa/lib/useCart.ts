import { useEffect, useState } from "react";
import { CART_EVENT, type Cart, cartCount, readCart } from "./cart";

/** The live basket, kept in step with writes from any screen. */
export function useCart(): { cart: Cart; count: number } {
    const [cart, setCart] = useState<Cart>(readCart);

    useEffect(() => {
        const onChange = () => setCart(readCart());
        window.addEventListener(CART_EVENT, onChange);

        return () => window.removeEventListener(CART_EVENT, onChange);
    }, []);

    return { cart, count: cartCount(cart) };
}
