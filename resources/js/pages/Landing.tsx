import { Head, Link, usePage } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { CountUp } from "@/components/site/CountUp";
import { type DownloadApp, DownloadSection } from "@/components/site/DownloadSection";
import { PostCard } from "@/components/site/PostCard";
import { Reveal } from "@/components/site/Reveal";
import { ShippingFeeCard } from "@/components/site/ShippingFeeCard";
import { SiteFooter } from "@/components/site/SiteFooter";
import { SiteHeader } from "@/components/site/SiteHeader";
import { TrackResiCard } from "@/components/site/TrackResiCard";
import { ADVANTAGES, COVERAGE, HERO_STATS, SERVICES } from "@/data/landing";
import { latestPosts } from "@/data/posts";
import { cx } from "@/lib/cx";

/** One catalog highlight, matching App\Http\Resources\Client\ProductResource. */
interface LandingProduct {
    id: number;
    name: string;
    slug: string;
    category: string;
    price_label: string;
    weight_label: string;
    image_url: string | null;
}

interface LandingProps {
    products: LandingProduct[];
}

export default function Landing({ products }: LandingProps) {
    const page = usePage();
    const downloads = (page.props.downloads ?? []) as DownloadApp[];
    const posts = latestPosts(3);

    return (
        <div className="flex min-h-screen w-full flex-col bg-surface font-sans text-on-surface antialiased">
            <Head title="Kiriman beku yang bisa Anda percaya" />
            <SiteHeader />

            <main className="flex-1">
                {/* Hero: the only dark surface on the page, so the aurora layers
                    read as depth rather than decoration. */}
                <section className="relative isolate overflow-hidden bg-[#0d1524] pt-28 pb-40 sm:pt-32 sm:pb-48">
                    <div aria-hidden="true" className="absolute inset-0 overflow-hidden">
                        <span className="aurora top-[-18%] left-[-8%] h-[420px] w-[420px] bg-primary/40" />
                        <span
                            className="aurora top-[6%] right-[-10%] h-[380px] w-[380px] bg-cyan-400/25"
                            style={{ animationDelay: "-6s", animationDuration: "23s" }}
                        />
                        <span
                            className="aurora bottom-[-24%] left-[28%] h-[360px] w-[360px] bg-emerald-400/20"
                            style={{ animationDelay: "-11s", animationDuration: "27s" }}
                        />
                        <span className="mist absolute inset-x-0 bottom-0 h-56 bg-gradient-to-t from-white/10 to-transparent" />
                        <span className="hairline-grid absolute inset-0 opacity-60" />
                    </div>

                    <div className="relative mx-auto flex w-full max-w-6xl flex-col items-center gap-6 px-4 text-center sm:px-6">
                        <Reveal>
                            <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 font-label-sm text-label-sm text-white backdrop-blur">
                                <MaterialIcon name="ac_unit" className="text-[15px]" />
                                Rantai dingin -18°C dengan bukti serah terima tervalidasi
                            </span>
                        </Reveal>

                        <Reveal delay={80}>
                            <h1 className="max-w-3xl font-headline-lg text-[32px] leading-tight text-white sm:text-[46px] sm:leading-[1.1]">
                                Kiriman beku Anda sampai utuh, dingin, dan tercatat
                            </h1>
                        </Reveal>

                        <Reveal delay={140}>
                            <p className="max-w-2xl font-body-lg text-body-lg text-white/70">
                                Anteraja Frozen mengantar produk beku dengan freeze box, kurir khusus, dan verifikasi
                                lokasi di titik serah terima. Setiap tahap terekam dan bisa diaudit.
                            </p>
                        </Reveal>

                        <Reveal delay={200}>
                            <div className="flex flex-wrap items-center justify-center gap-3">
                                <a
                                    href="#unduh"
                                    className="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 font-label-md text-label-md text-on-primary transition-transform hover:-translate-y-0.5 hover:bg-primary-container"
                                >
                                    <MaterialIcon name="android" className="text-[18px]" />
                                    Unduh aplikasi pelanggan
                                </a>
                                <a
                                    href="#layanan"
                                    className="inline-flex items-center gap-2 rounded-full border border-white/25 px-6 py-3 font-label-md text-label-md text-white transition-colors hover:border-white hover:bg-white/10"
                                >
                                    <MaterialIcon name="grid_view" className="text-[18px]" />
                                    Lihat layanan
                                </a>
                            </div>
                        </Reveal>

                        <Reveal delay={260} className="mt-6 w-full">
                            <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                {HERO_STATS.map((stat) => (
                                    <div
                                        key={stat.label}
                                        className="flex flex-col gap-0.5 rounded-2xl border border-white/10 bg-white/5 p-4 text-left backdrop-blur"
                                    >
                                        <dt className="sr-only">{stat.label}</dt>
                                        <dd className="font-headline-lg text-headline-lg text-white">
                                            <CountUp value={stat.value} suffix={stat.suffix} />
                                        </dd>
                                        <dd className="font-label-md text-label-md text-white/80">{stat.label}</dd>
                                        <dd className="font-label-sm text-label-sm text-white/50">{stat.detail}</dd>
                                    </div>
                                ))}
                            </dl>
                        </Reveal>
                    </div>
                </section>

                {/* The two showcase panels overlap the hero, like the real
                    anteraja.id home does with its track / fee widgets. */}
                <section className="relative z-10 mx-auto -mt-32 w-full max-w-6xl px-4 sm:-mt-36 sm:px-6">
                    <div className="grid gap-5 lg:grid-cols-2">
                        <Reveal>
                            <TrackResiCard className="h-full" />
                        </Reveal>
                        <Reveal delay={90}>
                            <ShippingFeeCard className="h-full" />
                        </Reveal>
                    </div>
                </section>

                <section id="produk" className="py-16 sm:py-20">
                    <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 sm:px-6">
                        <Reveal className="flex flex-wrap items-end justify-between gap-4">
                            <div className="flex flex-col gap-2">
                                <span className="font-label-sm text-label-sm text-primary uppercase">Produk</span>
                                <h2 className="font-headline-lg text-headline-lg text-on-surface sm:text-[30px]">
                                    Produk beku yang paling sering dikirim
                                </h2>
                                <p className="max-w-2xl font-body-md text-body-md text-secondary">
                                    Katalog ini yang Anda lihat di aplikasi pelanggan: pilih produk, jumlah, dan jadwal
                                    antar dalam satu alur.
                                </p>
                            </div>
                        </Reveal>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {products.map((product, index) => (
                                <Reveal key={product.id} delay={index * 70}>
                                    <article className="flex h-full flex-col overflow-hidden rounded-2xl border border-black/5 bg-surface-container-lowest transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg">
                                        <div className="flex h-32 items-center justify-center bg-gradient-to-br from-sky-100 via-cyan-50 to-emerald-50">
                                            {product.image_url !== null ? (
                                                <img
                                                    src={product.image_url}
                                                    alt={product.name}
                                                    width={96}
                                                    height={96}
                                                    className="h-24 w-24 object-contain"
                                                />
                                            ) : (
                                                <MaterialIcon name="ac_unit" className="text-[40px] text-sky-500/70" />
                                            )}
                                        </div>
                                        <div className="flex flex-1 flex-col gap-1.5 p-4">
                                            <span className="font-label-sm text-label-sm text-secondary uppercase">
                                                {product.category}
                                            </span>
                                            <h3 className="font-headline-sm text-headline-sm text-on-surface">
                                                {product.name}
                                            </h3>
                                            <span className="font-body-sm text-body-sm text-secondary">
                                                {product.weight_label} • dibekukan
                                            </span>
                                            <span className="mt-auto pt-2 font-headline-sm text-headline-sm text-primary">
                                                {product.price_label}
                                            </span>
                                        </div>
                                    </article>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="layanan" className="border-black/5 border-y bg-surface-container-low py-16 sm:py-20">
                    <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 sm:px-6">
                        <Reveal className="flex flex-col items-center gap-2 text-center">
                            <span className="font-label-sm text-label-sm text-primary uppercase">Layanan</span>
                            <h2 className="font-headline-lg text-headline-lg text-on-surface sm:text-[30px]">
                                Pilih sesuai kebutuhan kiriman
                            </h2>
                        </Reveal>

                        <div className="grid gap-4 md:grid-cols-2">
                            {SERVICES.map((service, index) => (
                                <Reveal key={service.title} delay={index * 70}>
                                    <article
                                        className={cx(
                                            "flex h-full flex-col gap-3 rounded-2xl border bg-surface-container-lowest p-6",
                                            service.featured ? "border-primary/40 shadow-lg" : "border-black/5"
                                        )}
                                    >
                                        <div className="flex items-center gap-3">
                                            <span
                                                className={cx(
                                                    "flex h-11 w-11 items-center justify-center rounded-xl",
                                                    service.featured
                                                        ? "bg-primary text-on-primary"
                                                        : "bg-surface-container text-primary"
                                                )}
                                            >
                                                <MaterialIcon name={service.icon} className="text-[22px]" />
                                            </span>
                                            <div className="flex flex-col">
                                                <span className="font-headline-md text-headline-md text-on-surface">
                                                    {service.title}
                                                </span>
                                                <span className="font-label-sm text-label-sm text-secondary uppercase">
                                                    {service.sla}
                                                </span>
                                            </div>
                                            {service.featured ? (
                                                <span className="ml-auto rounded-full bg-primary/10 px-2.5 py-1 font-label-sm text-label-sm text-primary">
                                                    Tercepat
                                                </span>
                                            ) : null}
                                        </div>

                                        <p className="font-body-sm text-body-sm leading-relaxed text-secondary">
                                            {service.description}
                                        </p>

                                        <ul className="mt-auto flex list-none flex-col gap-1.5 p-0">
                                            {service.points.map((point) => (
                                                <li
                                                    key={point}
                                                    className="flex items-center gap-2 font-body-sm text-body-sm text-on-surface"
                                                >
                                                    <MaterialIcon
                                                        name="check_circle"
                                                        className="text-[15px] text-tertiary"
                                                    />
                                                    {point}
                                                </li>
                                            ))}
                                        </ul>
                                    </article>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="keunggulan" className="py-16 sm:py-20">
                    <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 sm:px-6">
                        <Reveal className="flex flex-col items-center gap-2 text-center">
                            <span className="font-label-sm text-label-sm text-primary uppercase">Keunggulan</span>
                            <h2 className="font-headline-lg text-headline-lg text-on-surface sm:text-[30px]">
                                Kenapa rantai dinginnya bisa dipercaya
                            </h2>
                        </Reveal>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {ADVANTAGES.map((item, index) => (
                                <Reveal key={item.title} delay={index * 70}>
                                    <article className="flex h-full flex-col gap-3 rounded-2xl border border-black/5 bg-surface-container-lowest p-5">
                                        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container text-primary">
                                            <MaterialIcon name={item.icon} className="text-[20px]" />
                                        </span>
                                        <h3 className="font-headline-sm text-headline-sm text-on-surface">
                                            {item.title}
                                        </h3>
                                        <p className="font-body-sm text-body-sm leading-relaxed text-secondary">
                                            {item.description}
                                        </p>
                                    </article>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="jangkauan" className="border-black/5 border-y bg-surface-container-low py-16 sm:py-20">
                    <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 sm:px-6 lg:grid-cols-[1fr_1.2fr]">
                        <Reveal className="flex flex-col gap-3">
                            <span className="font-label-sm text-label-sm text-primary uppercase">Jangkauan</span>
                            <h2 className="font-headline-lg text-headline-lg text-on-surface sm:text-[30px]">
                                Dari Jakarta Selatan ke seluruh Jabodetabek
                            </h2>
                            <p className="font-body-md text-body-md text-secondary">
                                Hub kami melayani Jakarta, Depok, Tangerang, Bekasi, dan Bogor. Di luar Jabodetabek
                                belum ada rute, karena paket beku tidak boleh transit selama itu.
                            </p>
                            <div className="relative mt-2 h-40 overflow-hidden rounded-2xl border border-black/5 bg-gradient-to-br from-sky-50 via-white to-emerald-50">
                                <span className="drift absolute inset-0 flex items-center justify-around">
                                    {COVERAGE.slice(0, 6).map((area, index) => (
                                        <span
                                            key={area.region}
                                            className="mist flex flex-col items-center gap-1"
                                            style={{ animationDelay: -(index * 1.4) + "s" }}
                                        >
                                            <span className="h-2.5 w-2.5 rounded-full bg-primary" />
                                            <span className="font-label-sm text-label-sm text-secondary">
                                                {area.areas[0]}
                                            </span>
                                        </span>
                                    ))}
                                </span>
                            </div>
                        </Reveal>

                        <Reveal delay={90}>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {COVERAGE.map((area) => (
                                    <article
                                        key={area.region}
                                        className="flex flex-col gap-1.5 rounded-xl border border-black/5 bg-surface-container-lowest p-4"
                                    >
                                        <span className="font-headline-sm text-headline-sm text-on-surface">
                                            {area.region}
                                        </span>
                                        <span className="font-body-sm text-body-sm text-secondary">
                                            {area.areas.join(" • ")}
                                        </span>
                                        <span className="font-label-sm text-label-sm text-primary">{area.eta}</span>
                                    </article>
                                ))}
                            </div>
                        </Reveal>
                    </div>
                </section>

                <section id="blog" className="py-16 sm:py-20">
                    <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 sm:px-6">
                        <Reveal className="flex flex-wrap items-end justify-between gap-4">
                            <div className="flex flex-col gap-2">
                                <span className="font-label-sm text-label-sm text-primary uppercase">Blog</span>
                                <h2 className="font-headline-lg text-headline-lg text-on-surface sm:text-[30px]">
                                    Catatan operasional rantai dingin
                                </h2>
                            </div>
                            <Link
                                href="/blog"
                                className="inline-flex items-center gap-1.5 rounded-full border border-outline-variant px-4 py-2 font-label-md text-label-md text-on-surface transition-colors hover:border-primary hover:text-primary"
                            >
                                Semua artikel
                                <MaterialIcon name="arrow_forward" className="text-[16px]" />
                            </Link>
                        </Reveal>

                        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                            {posts.map((post, index) => (
                                <Reveal key={post.slug} delay={index * 80}>
                                    <PostCard post={post} className="h-full" />
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                <DownloadSection apps={downloads} />
            </main>

            <SiteFooter />
        </div>
    );
}
