import type { Metadata } from "next";
import Link from "next/link";
import { getAllBlogPosts } from "@/lib/blog";

export const metadata: Metadata = {
  title: "Dev Blog | Chess Coach Journal",
  description: "Engineering notes from building a full-stack chess analysis product.",
};

export default async function BlogIndexPage() {
  const posts = await getAllBlogPosts();

  return (
    <main className="max-w-3xl mx-auto px-6 py-12">
      <header className="mb-10">
        <p className="text-xs tracking-[0.12em] uppercase mb-3" style={{ color: "var(--text-faint)" }}>
          Dev Blog
        </p>
        <h1 className="text-4xl mb-4" style={{ fontFamily: "var(--font-playfair)" }}>
          Engineering notes from building a full-stack chess analysis product.
        </h1>
        <p className="text-base leading-7" style={{ color: "var(--text-muted)" }}>
          This is where product and engineering decisions are documented: what changed, why it changed,
          and what trade-offs were accepted to keep the core experience fast, teachable, and reliable.
        </p>
      </header>

      <section className="space-y-6">
        {posts.map((post) => (
          <article
            key={post.slug}
            className="rounded-xl border p-5"
            style={{ borderColor: "rgba(232,224,208,0.12)", background: "rgba(255,255,255,0.01)" }}
          >
            <p className="text-xs mb-2" style={{ color: "var(--text-faint)" }}>
              {new Date(post.publishedAt).toLocaleDateString("en-GB", {
                day: "2-digit",
                month: "short",
                year: "numeric",
              })}
            </p>
            <h2 className="text-2xl mb-2" style={{ fontFamily: "var(--font-playfair)" }}>
              <Link className="hover:underline" href={`/blog/${post.slug}`}>
                {post.title}
              </Link>
            </h2>
            <p className="text-sm leading-7 mb-3" style={{ color: "var(--text-muted)" }}>
              {post.summary}
            </p>
            <ul className="flex flex-wrap gap-2">
              {post.tags.map((tag) => (
                <li
                  key={tag}
                  className="text-[11px] tracking-[0.08em] uppercase px-2 py-1 rounded-full border"
                  style={{ borderColor: "rgba(232,224,208,0.18)", color: "var(--text-faint)" }}
                >
                  {tag}
                </li>
              ))}
            </ul>
          </article>
        ))}
      </section>
    </main>
  );
}
