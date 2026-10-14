import { Head, Link } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { PostCard } from "@/components/site/PostCard";
import { SiteFooter } from "@/components/site/SiteFooter";
import { SiteHeader } from "@/components/site/SiteHeader";
import { POSTS } from "@/data/posts";

export default function BlogIndex() {
    const posts = [...POSTS].sort((a, b) => b.publishedAt.localeCompare(a.publishedAt));

    return (
        <div className="flex min-h-screen w-full flex-col bg-surface font-sans text-on-surface antialiased">
            <Head title="Blog" />
            <SiteHeader />

            <main className="flex-1 pt-28">
                <section className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 pb-10 sm:px-6">
                    <Link
                        href="/"
                        className="inline-flex w-fit items-center gap-1.5 font-label-md text-label-md text-secondary transition-colors hover:text-primary"
                    >
                        <MaterialIcon name="arrow_back" className="text-[16px]" />
                        Kembali ke beranda
                    </Link>
                    <h1 className="font-headline-lg text-headline-lg text-on-surface sm:text-[34px]">
                        Blog Anteraja Frozen
                    </h1>
                    <p className="max-w-2xl font-body-md text-body-md text-secondary">
                        Catatan lapangan, panduan penanganan produk beku, dan penjelasan cara kerja sistem kami.
                    </p>
                </section>

                <section className="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6">
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {posts.map((post) => (
                            <PostCard key={post.slug} post={post} />
                        ))}
                    </div>
                </section>
            </main>

            <SiteFooter />
        </div>
    );
}
