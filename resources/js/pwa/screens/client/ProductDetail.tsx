import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import type { ScreenProps } from "../../App";
import { ActionError } from "../../components/ActionError";
import { StateCard } from "../../components/StateCard";
import { clientApi } from "../../lib/api";
import { addToCart, MAX_QUANTITY } from "../../lib/cart";
import { useRouter } from "../../lib/router";
import { useCart } from "../../lib/useCart";
import { useLoad } from "../../lib/useLoad";

/** One product, its handling note, and the quantity to add to the basket. */
export default function ProductDetail({ params }: ScreenProps) {
    const slug = params.slug ?? "";
    const { navigate } = useRouter();
    const { count } = useCart();
    const { data, error, reload } = useLoad(() => clientApi.catalog.show(slug), `client-product-${slug}`);
    const [quantity, setQuantity] = useState(1);
    const [justAdded, setJustAdded] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const product = data?.data ?? null;

    function openCheckout() {
        if (count === 0) {
            setActionError("Keranjang Anda masih kosong.");
            return;
        }

        navigate("/client/checkout");
    }

    if (error !== null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <StateCard message={error} onRetry={reload} />
            </div>
        );
    }

    if (product === null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <section className="flex w-full flex-col gap-space-sm rounded-xl bg-surface-container-lowest p-space-lg">
                    <MaterialIcon name="search" className="text-[26px] text-secondary" />
                    <span className="font-headline-md text-headline-md text-on-surface">Produk tidak ditemukan</span>
                    <span className="font-body-md text-body-md text-secondary">
                        Produk ini sudah tidak dijual. Silakan pilih produk lain di katalog.
                    </span>
                    <button
                        type="button"
                        onClick={() => navigate("/client/catalog")}
                        className="mt-space-sm inline-flex h-11 items-center justify-center gap-1.5 rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                    >
                        <MaterialIcon name="list" className="text-[18px]" />
                        Kembali ke katalog
                    </button>
                </section>
            </div>
        );
    }

    return (
        <div className="flex w-full flex-1 flex-col gap-space-lg p-margin pb-space-xl">
            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-xl">
                <div className="flex w-full items-start gap-space-md">
                    <img
                        src={product.image_url ?? "/images/product-placeholder.svg"}
                        alt=""
                        className="h-14 w-14 rounded-2xl bg-surface-container object-contain"
                    />
                    <div className="flex flex-1 flex-col gap-0.5">
                        <span className="font-label-sm text-label-sm text-secondary uppercase">{product.category}</span>
                        <span className="font-headline-lg text-headline-lg text-on-surface">{product.name}</span>
                        <span className="font-body-sm text-body-sm text-secondary">
                            {product.weight_label} • dijual per {product.unit}
                        </span>
                    </div>
                </div>

                <span className="font-headline-lg text-headline-lg text-primary">{product.price_label}</span>

                <hr className="border-outline-variant" />

                <p className="font-body-lg text-body-lg leading-relaxed text-on-surface">{product.description}</p>
            </section>

            <section className="flex w-full flex-col gap-2 rounded-xl bg-surface-container-low p-space-lg">
                <span className="flex items-center gap-space-sm">
                    <MaterialIcon name="thermostat" className="text-[18px] text-tertiary" />
                    <span className="font-label-sm text-label-sm text-tertiary uppercase">Catatan penanganan</span>
                </span>
                <span className="font-body-sm text-body-sm leading-snug text-secondary">
                    {product.handling_notes !== ""
                        ? product.handling_notes
                        : "Dikirim dalam cooler box dengan ice gel."}
                </span>
            </section>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Jumlah</span>

                <div className="flex w-full items-center gap-space-md">
                    <button
                        type="button"
                        aria-label="Kurangi jumlah"
                        onClick={() => {
                            setJustAdded(false);
                            setQuantity((value) => Math.max(1, value - 1));
                        }}
                        className="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-container"
                    >
                        <MaterialIcon name="remove" className="text-[18px] text-on-surface" />
                    </button>

                    <span className="font-headline-lg text-headline-lg text-on-surface">{quantity}</span>

                    <button
                        type="button"
                        aria-label="Tambah jumlah"
                        onClick={() => {
                            setJustAdded(false);
                            setQuantity((value) => Math.min(MAX_QUANTITY, value + 1));
                        }}
                        className="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-container"
                    >
                        <MaterialIcon name="add" className="text-[18px] text-on-surface" />
                    </button>

                    <span className="flex flex-1 justify-end font-body-sm text-body-sm text-secondary">
                        maks {MAX_QUANTITY}
                    </span>
                </div>
            </section>

            <ActionError message={actionError} />

            {justAdded ? (
                <div className="flex w-full items-center gap-space-sm rounded-lg bg-tertiary-fixed p-space-md">
                    <MaterialIcon name="check_circle" className="text-[18px] text-on-tertiary-fixed" />
                    <span className="flex-1 font-body-sm text-body-sm text-on-tertiary-fixed">
                        {quantity} {product.name} masuk keranjang.
                    </span>
                </div>
            ) : null}

            <div className="sticky bottom-0 mt-auto flex w-full flex-col gap-2 bg-surface-container-low p-space-lg">
                {count === 0 ? null : (
                    <span className="font-body-sm text-body-sm text-secondary">{count} item siap dipesan</span>
                )}

                <div className="flex w-full gap-space-sm">
                    <button
                        type="button"
                        onClick={() => {
                            addToCart(product.id, quantity);
                            setJustAdded(true);
                            setActionError(null);
                        }}
                        className="flex h-11 flex-1 items-center justify-center gap-1.5 rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                    >
                        <MaterialIcon name="add_shopping_cart" className="text-[18px]" />
                        Tambah ke Keranjang
                    </button>
                    <button
                        type="button"
                        onClick={openCheckout}
                        className="flex h-11 flex-1 items-center justify-center gap-1.5 rounded-lg bg-surface-container-high px-space-lg font-label-md text-label-md text-on-surface"
                    >
                        <MaterialIcon name="arrow_forward" className="text-[18px]" />
                        Checkout
                    </button>
                </div>
            </div>
        </div>
    );
}
