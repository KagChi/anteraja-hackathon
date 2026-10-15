import { MaterialIcon } from "@/components/MaterialIcon";
import { InstallCard } from "../../components/InstallCard";
import { StateCard } from "../../components/StateCard";
import { clientApi } from "../../lib/api";
import { Link, useRouter } from "../../lib/router";
import { isSignedIn } from "../../lib/token";
import { useCart } from "../../lib/useCart";
import { useLoad } from "../../lib/useLoad";

const REASONS = [
    {
        icon: "thermostat",
        title: "Suhu dicatat sebelum berangkat",
        detail: "Kurir memeriksa kondisi paket dan mencatat suhunya di setiap keberangkatan."
    },
    {
        icon: "location_on",
        title: "Serah terima tervalidasi",
        detail: "Lokasi kurir diperiksa di titik penerima sebelum bukti pengantaran diterima."
    },
    {
        icon: "edit_note",
        title: "Jadwal yang bisa Anda pilih",
        detail: "Tentukan sendiri jendela antar supaya paket tidak menunggu di teras."
    }
];

/**
 * The storefront home: what the service promises, the basket once it has
 * something in it, the first four catalog products, and the way in.
 */
export default function Landing() {
    const { navigate } = useRouter();
    const signedIn = isSignedIn("client");
    const { count } = useCart();
    const { data, error, reload } = useLoad(() => clientApi.catalog.list(), "client-catalog");
    const featured = (data?.data ?? []).slice(0, 4);

    return (
        <div className="flex w-full flex-col gap-space-lg p-margin pb-space-xl">
            <section className="flex w-full flex-col gap-space-md rounded-2xl bg-primary p-space-xl">
                <div className="flex items-center gap-space-sm">
                    <MaterialIcon name="ac_unit" className="text-[18px] text-on-primary" />
                    <span className="font-label-sm text-label-sm text-on-primary uppercase">Kirim ke keluarga</span>
                </div>

                <h2 className="font-headline-lg text-headline-lg leading-snug text-on-primary">
                    Kiriman Anda sampai ke penerima, utuh, dingin, dan tercatat
                </h2>

                <p className="font-body-sm text-body-sm leading-snug text-on-primary">
                    Pilih isi paket, isi alamat penerima, lalu pantau kurir sampai paket diterima.
                </p>

                <div className="flex w-full gap-space-sm pt-1">
                    <Link
                        href="/client/catalog"
                        className="flex flex-1 items-center justify-center gap-space-sm rounded-xl bg-surface px-space-lg py-space-md"
                    >
                        <MaterialIcon name="shopping_cart" className="text-[18px] text-primary" />
                        <span className="font-headline-sm text-headline-sm text-primary">Lihat Katalog</span>
                    </Link>

                    <Link
                        href={signedIn ? "/client/orders" : "/client/register"}
                        className="flex flex-1 items-center justify-center gap-space-sm rounded-xl bg-primary-fixed px-space-lg py-space-md"
                    >
                        <MaterialIcon
                            name={signedIn ? "list" : "person_add"}
                            className="text-[18px] text-on-primary-fixed"
                        />
                        <span className="font-headline-sm text-headline-sm text-on-primary-fixed">
                            {signedIn ? "Pesanan Saya" : "Daftar"}
                        </span>
                    </Link>
                </div>
            </section>

            {count > 0 ? (
                <Link
                    href="/client/checkout"
                    className="flex w-full items-center justify-between gap-space-md rounded-xl bg-surface-container-lowest p-space-lg"
                >
                    <span className="flex items-center gap-space-md">
                        <MaterialIcon name="add_shopping_cart" className="text-[20px] text-primary" />
                        <span className="flex flex-col">
                            <span className="font-headline-sm text-headline-sm text-on-surface">Keranjang Anda</span>
                            <span className="font-body-sm text-body-sm text-secondary">{count} item siap dipesan</span>
                        </span>
                    </span>
                    <MaterialIcon name="arrow_forward" className="text-[18px] text-primary" />
                </Link>
            ) : null}

            {error === null ? null : <StateCard message={error} onRetry={reload} />}

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Kenapa Anteraja Frozen</span>

                {REASONS.map((reason) => (
                    <div key={reason.icon} className="flex w-full items-start gap-space-md">
                        <MaterialIcon name={reason.icon} className="text-[20px] text-tertiary" />
                        <div className="flex flex-1 flex-col gap-0.5">
                            <span className="font-headline-sm text-headline-sm text-on-surface">{reason.title}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{reason.detail}</span>
                        </div>
                    </div>
                ))}
            </section>

            <section className="flex w-full flex-col gap-space-md">
                <div className="flex w-full items-center justify-between">
                    <span className="font-headline-md text-headline-md text-on-surface">Produk Populer</span>
                    <button
                        type="button"
                        onClick={() => navigate("/client/catalog")}
                        className="flex items-center gap-1"
                    >
                        <span className="font-headline-sm text-headline-sm text-primary">Semua</span>
                        <MaterialIcon name="arrow_forward" className="text-[16px] text-primary" />
                    </button>
                </div>

                {featured.length === 0 && error === null ? (
                    <span className="font-body-md text-body-md text-secondary">
                        Katalog belum tersedia. Coba lagi sebentar lagi.
                    </span>
                ) : null}

                {featured.map((product) => (
                    <Link
                        key={product.id}
                        href={`/client/catalog/${product.slug}`}
                        className="flex w-full items-center gap-space-md rounded-xl bg-surface-container-lowest p-space-lg"
                    >
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
                            <span className="font-headline-sm text-headline-sm text-on-surface">{product.name}</span>
                            <span className="font-body-sm text-body-sm text-secondary">{product.weight_label}</span>
                        </span>
                        <span className="font-headline-sm text-headline-sm text-primary">{product.price_label}</span>
                    </Link>
                ))}
            </section>

            {signedIn ? null : (
                <section className="flex w-full flex-col gap-space-sm rounded-xl bg-surface-container-low p-space-lg">
                    <span className="font-headline-sm text-headline-sm text-on-surface">Sudah punya akun?</span>
                    <span className="font-body-sm text-body-sm text-secondary">
                        Masuk untuk melihat pesanan dan konfirmasi jadwal pengantaran Anda.
                    </span>
                    <button
                        type="button"
                        onClick={() => navigate("/client/login")}
                        className="inline-flex h-11 items-center justify-center gap-1.5 rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        <MaterialIcon name="login" className="text-[18px]" />
                        Masuk
                    </button>
                </section>
            )}

            <InstallCard realm="client" />
        </div>
    );
}
