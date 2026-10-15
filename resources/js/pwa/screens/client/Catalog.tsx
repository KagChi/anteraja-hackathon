import { useState } from "react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ActionError } from "../../components/ActionError";
import { StateCard } from "../../components/StateCard";
import { clientApi } from "../../lib/api";
import { addToCart } from "../../lib/cart";
import { Link, useRouter } from "../../lib/router";
import { useCart } from "../../lib/useCart";
import { useLoad } from "../../lib/useLoad";

/** The frozen-goods storefront: what is for sale, and a way into the basket. */
export default function Catalog() {
    const { navigate } = useRouter();
    const { count } = useCart();
    const { data, error, reload } = useLoad(() => clientApi.catalog.list(), "client-catalog");
    const [addedName, setAddedName] = useState("");
    const [actionError, setActionError] = useState<string | null>(null);
    const products = data?.data ?? [];

    function add(productId: number, name: string) {
        addToCart(productId);
        setAddedName(name);
        setActionError(null);
    }

    function openCheckout() {
        if (count === 0) {
            setActionError("Pilih minimal satu produk sebelum checkout.");
            return;
        }

        navigate("/client/checkout");
    }

    return (
        <div className="flex w-full flex-1 flex-col gap-space-lg p-margin pb-space-xl">
            {error !== null ? (
                <StateCard message={error} onRetry={reload} />
            ) : (
                <>
                    <section className="flex w-full flex-col gap-1 rounded-xl bg-surface-container-low p-space-lg">
                        <span className="font-label-sm text-label-sm text-secondary uppercase">
                            Katalog Produk Beku
                        </span>
                        <span className="font-body-md text-body-md text-on-surface">
                            {products.length} produk tersedia. Harga dan berat diambil dari sistem hub.
                        </span>
                    </section>

                    <ActionError message={actionError} />

                    {addedName === "" ? null : (
                        <div className="flex w-full items-center gap-space-sm rounded-lg bg-tertiary-fixed p-space-md">
                            <MaterialIcon name="check_circle" className="text-[18px] text-on-tertiary-fixed" />
                            <span className="flex-1 font-body-sm text-body-sm text-on-tertiary-fixed">
                                {addedName} masuk keranjang.
                            </span>
                        </div>
                    )}

                    {products.map((product) => (
                        <section
                            key={product.id}
                            className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg"
                        >
                            <Link href={`/client/catalog/${product.slug}`} className="flex w-full flex-col gap-2">
                                <span className="flex w-full items-start gap-space-md">
                                    <img
                                        src={product.image_url ?? "/images/product-placeholder.svg"}
                                        alt=""
                                        loading="lazy"
                                        className="h-12 w-12 rounded-xl bg-surface-container object-contain"
                                    />
                                    <span className="flex flex-1 flex-col gap-0.5">
                                        <span className="font-label-sm text-label-sm text-secondary uppercase">
                                            {product.category}
                                        </span>
                                        <span className="font-headline-md text-headline-md text-on-surface">
                                            {product.name}
                                        </span>
                                        <span className="font-body-sm text-body-sm text-secondary">
                                            {product.weight_label} • {product.unit}
                                        </span>
                                    </span>
                                    <span className="font-headline-sm text-headline-sm text-primary">
                                        {product.price_label}
                                    </span>
                                </span>

                                <span className="font-body-sm text-body-sm leading-snug text-secondary">
                                    {product.description}
                                </span>
                            </Link>

                            <div className="flex w-full items-center justify-between gap-space-md pt-1">
                                <Link
                                    href={`/client/catalog/${product.slug}`}
                                    className="flex items-center gap-1 font-body-sm text-body-sm text-secondary"
                                >
                                    Atur jumlah
                                    <MaterialIcon name="arrow_forward" className="text-[14px]" />
                                </Link>

                                <button
                                    type="button"
                                    onClick={() => add(product.id, product.name)}
                                    className="flex items-center gap-1.5 rounded-lg bg-primary px-space-md py-space-sm"
                                >
                                    <MaterialIcon name="add_shopping_cart" className="text-[16px] text-on-primary" />
                                    <span className="font-label-md text-label-md text-on-primary">Tambah</span>
                                </button>
                            </div>
                        </section>
                    ))}

                    {products.length === 0 ? (
                        <span className="font-body-md text-body-md text-secondary">
                            Belum ada produk yang tersedia.
                        </span>
                    ) : null}
                </>
            )}

            {count === 0 ? null : (
                <div className="sticky bottom-0 mt-auto flex w-full items-center justify-between gap-space-md bg-surface-container-low p-space-lg">
                    <span className="flex flex-col gap-0.5">
                        <span className="font-body-sm text-body-sm text-secondary">{count} item di keranjang</span>
                        <span className="font-headline-sm text-headline-sm text-on-surface">Siap dipesan</span>
                    </span>

                    <span className="flex items-center gap-space-sm">
                        <Link
                            href="/client/checkout"
                            className="flex items-center gap-1.5 rounded-lg bg-primary px-space-lg py-space-sm"
                        >
                            <MaterialIcon name="arrow_forward" className="text-[16px] text-on-primary" />
                            <span className="font-label-md text-label-md text-on-primary">Checkout</span>
                        </Link>
                    </span>
                </div>
            )}
        </div>
    );
}
