import { Link } from "@inertiajs/react";
import { AnterajaLogo } from "@/components/AnterajaLogo";
import { MaterialIcon } from "@/components/MaterialIcon";
import { CONSOLE_LINKS } from "@/data/landing";
import { latestPosts } from "@/data/posts";

/** The landing page footer, including the three console entry points. */
export function SiteFooter() {
    const posts = latestPosts(3);

    return (
        <footer className="border-white/10 border-t bg-[#111c2d] text-white">
            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div className="flex flex-col gap-3">
                    <div className="flex items-center gap-2">
                        <AnterajaLogo className="h-7" />
                        <span className="font-headline-sm text-headline-sm">Anteraja Frozen</span>
                    </div>
                    <p className="max-w-xs font-body-sm text-body-sm text-white/60">
                        Layanan pengiriman khusus produk beku: suhu terjaga, serah terima tervalidasi, dan setiap
                        insiden tercatat.
                    </p>
                    <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 font-label-sm text-label-sm text-white/80">
                        <MaterialIcon name="ac_unit" className="text-[14px]" />
                        Rantai dingin -18°C
                    </span>
                </div>

                <div className="flex flex-col gap-2">
                    <span className="font-label-md text-label-md text-white/50 uppercase">Mulai</span>
                    {CONSOLE_LINKS.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className="font-body-sm text-body-sm text-white/80 transition-colors hover:text-white"
                        >
                            {link.title}
                        </Link>
                    ))}
                    <Link
                        href="/login"
                        className="font-body-sm text-body-sm text-white/80 transition-colors hover:text-white"
                    >
                        Masuk Admin
                    </Link>
                </div>

                <div className="flex flex-col gap-2">
                    <span className="font-label-md text-label-md text-white/50 uppercase">Artikel</span>
                    {posts.map((post) => (
                        <Link
                            key={post.slug}
                            href={"/blog/" + post.slug}
                            className="font-body-sm text-body-sm text-white/80 transition-colors hover:text-white"
                        >
                            {post.title}
                        </Link>
                    ))}
                </div>

                <div className="flex flex-col gap-2">
                    <span className="font-label-md text-label-md text-white/50 uppercase">Aplikasi</span>
                    <a
                        href="#unduh"
                        className="font-body-sm text-body-sm text-white/80 transition-colors hover:text-white"
                    >
                        Aplikasi pelanggan
                    </a>
                    <a
                        href="#unduh"
                        className="font-body-sm text-body-sm text-white/80 transition-colors hover:text-white"
                    >
                        Aplikasi kurir
                    </a>
                    <Link
                        href="/blog"
                        className="font-body-sm text-body-sm text-white/80 transition-colors hover:text-white"
                    >
                        Semua artikel
                    </Link>
                </div>
            </div>

            <div className="border-white/10 border-t">
                <p className="mx-auto flex w-full max-w-6xl flex-col gap-1 px-4 py-5 font-body-sm text-body-sm text-white/50 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <span>Purwarupa Anteraja Frozen untuk hackathon. Bukan layanan komersial.</span>
                    <span>Hub Jakarta Selatan • Suhu standar -18°C s/d -25°C</span>
                </p>
            </div>
        </footer>
    );
}
