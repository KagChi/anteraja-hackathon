import { Link } from "@inertiajs/react";
import { MaterialIcon } from "@/components/MaterialIcon";
import { formatPostDate, type Post } from "@/data/posts";
import { cx } from "@/lib/cx";

interface PostCardProps {
    post: Post;
    className?: string;
}

/**
 * One article card. The art is a gradient tile rather than a photo, so the
 * blog needs no image assets and still reads as designed.
 */
export function PostCard({ post, className }: PostCardProps) {
    return (
        <Link
            href={"/blog/" + post.slug}
            className={cx(
                "group flex flex-col overflow-hidden rounded-2xl border border-black/5 bg-surface-container-lowest transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg",
                className
            )}
        >
            <div className={cx("relative flex h-36 items-end bg-gradient-to-br p-4", post.accent)}>
                <MaterialIcon name={post.icon} className="absolute top-4 right-4 text-[26px] text-white/80" />
                <span className="rounded-full bg-black/25 px-2.5 py-1 font-label-sm text-label-sm text-white backdrop-blur">
                    {post.category}
                </span>
            </div>
            <div className="flex flex-1 flex-col gap-2 p-5">
                <h3 className="font-headline-md text-headline-md text-on-surface">{post.title}</h3>
                <p className="font-body-sm text-body-sm leading-relaxed text-secondary">{post.excerpt}</p>
                <p className="mt-auto flex items-center gap-2 pt-3 font-label-sm text-label-sm text-secondary">
                    <span>{formatPostDate(post.publishedAt)}</span>
                    <span aria-hidden="true">•</span>
                    <span>{post.readMinutes} menit baca</span>
                    <MaterialIcon
                        name="arrow_forward"
                        className="ml-auto text-[16px] text-primary transition-transform group-hover:translate-x-0.5"
                    />
                </p>
            </div>
        </Link>
    );
}
