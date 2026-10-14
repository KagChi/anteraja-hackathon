import { Head, Link } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { PostCard } from "@/components/site/PostCard";
import { SiteFooter } from "@/components/site/SiteFooter";
import { SiteHeader } from "@/components/site/SiteHeader";
import { findPost, formatPostDate, latestPosts } from "@/data/posts";
import { cx } from "@/lib/cx";

interface BlogShowProps {
    slug: string;
}

/**
 * One article.
 *
 * Posts live in resources/js/data/posts.ts, so the page resolves the slug
 * itself; an unknown slug renders the not-found panel instead of a 404 page
 * that would need the same list duplicated in PHP.
 */
export default function BlogShow({ slug }: BlogShowProps) {
    const post = findPost(slug);
    const others = latestPosts(3).filter((item) => item.slug !== slug);

    if (post === undefined) {
        return (
            <div className="flex min-h-screen w-full flex-col bg-surface font-sans text-on-surface antialiased">
                <Head title="Artikel tidak ditemukan" />
                <SiteHeader />
                <main className="flex flex-1 flex-col items-center justify-center gap-4 px-4 py-32 text-center">
                    <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-container text-secondary">
                        <MaterialIcon name="search_off" className="text-[26px]" />
                    </span>
                    <h1 className="font-headline-lg text-headline-lg text-on-surface">Artikel tidak ditemukan</h1>
                    <p className="max-w-md font-body-md text-body-md text-secondary">
                        Tulisan yang Anda cari sudah dipindahkan atau belum pernah ada.
                    </p>
                    <Link
                        href="/blog"
                        className="inline-flex items-center gap-1.5 rounded-full bg-primary px-5 py-2.5 font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container"
                    >
                        <MaterialIcon name="arrow_back" className="text-[16px]" />
                        Lihat semua artikel
                    </Link>
                </main>
                <SiteFooter />
            </div>
        );
    }

    return (
        <div className="flex min-h-screen w-full flex-col bg-surface font-sans text-on-surface antialiased">
            <Head title={post.title} />
            <SiteHeader />

            <main className="flex-1 pt-16">
                <div className={cx("h-40 w-full bg-gradient-to-br", post.accent)} aria-hidden="true" />

                <article className="mx-auto -mt-20 w-full max-w-3xl px-4 sm:px-6">
                    <div className="flex flex-col gap-4 rounded-2xl border border-black/5 bg-surface-container-lowest p-6 shadow-lg sm:p-8">
                        <Link
                            href="/blog"
                            className="inline-flex w-fit items-center gap-1.5 font-label-md text-label-md text-secondary transition-colors hover:text-primary"
                        >
                            <MaterialIcon name="arrow_back" className="text-[16px]" />
                            Semua artikel
                        </Link>

                        <div className="flex flex-wrap items-center gap-3">
                            <span className="rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm text-primary">
                                {post.category}
                            </span>
                            <span className="font-label-sm text-label-sm text-secondary">
                                {formatPostDate(post.publishedAt)}
                            </span>
                            <span aria-hidden="true" className="text-secondary">
                                •
                            </span>
                            <span className="font-label-sm text-label-sm text-secondary">
                                {post.readMinutes} menit baca
                            </span>
                        </div>

                        <h1 className="font-headline-lg text-headline-lg text-on-surface sm:text-[34px] sm:leading-tight">
                            {post.title}
                        </h1>
                        <p className="font-body-lg text-body-lg text-secondary">{post.excerpt}</p>
                        <p className="flex items-center gap-2 font-label-md text-label-md text-on-surface">
                            <MaterialIcon name="edit_note" className="text-[16px] text-primary" />
                            {post.author}
                        </p>
                    </div>

                    <div className="flex flex-col gap-8 py-10">
                        {post.sections.map((section, index) => (
                            <section key={section.heading ?? "section-" + index} className="flex flex-col gap-3">
                                {section.heading !== undefined ? (
                                    <h2 className="font-headline-md text-headline-md text-on-surface">
                                        {section.heading}
                                    </h2>
                                ) : null}

                                {(section.paragraphs ?? []).map((paragraph) => (
                                    <p
                                        key={paragraph.slice(0, 40)}
                                        className="font-body-md text-body-md leading-relaxed text-secondary"
                                    >
                                        {paragraph}
                                    </p>
                                ))}

                                {section.bullets !== undefined ? (
                                    <ul className="flex list-none flex-col gap-2 p-0">
                                        {section.bullets.map((bullet) => (
                                            <li
                                                key={bullet.slice(0, 40)}
                                                className="flex items-start gap-2 font-body-md text-body-md text-on-surface"
                                            >
                                                <MaterialIcon
                                                    name="chevron_right"
                                                    className="mt-0.5 text-[18px] text-primary"
                                                />
                                                <span className="leading-relaxed">{bullet}</span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                            </section>
                        ))}
                    </div>
                </article>

                {others.length > 0 ? (
                    <section className="border-black/5 border-t bg-surface-container-low py-14">
                        <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 sm:px-6">
                            <h2 className="font-headline-md text-headline-md text-on-surface">Artikel lainnya</h2>
                            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                {others.map((item) => (
                                    <PostCard key={item.slug} post={item} />
                                ))}
                            </div>
                        </div>
                    </section>
                ) : null}
            </main>

            <SiteFooter />
        </div>
    );
}
