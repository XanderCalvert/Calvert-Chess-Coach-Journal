import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getAllBlogPosts, getBlogPostBySlug } from "@/lib/blog";

type BlogPostPageProps = {
  params: Promise<{ slug: string }>;
};

async function getPostMdxComponent(slug: string) {
  try {
    const postModule = await import(`@/content/blog/${slug}.mdx`);
    return postModule.default;
  } catch {
    return null;
  }
}

export async function generateStaticParams() {
  const posts = await getAllBlogPosts();
  return posts.map((post) => ({ slug: post.slug }));
}

export async function generateMetadata({ params }: BlogPostPageProps): Promise<Metadata> {
  const { slug } = await params;
  const post = await getBlogPostBySlug(slug);

  if (!post) {
    return {
      title: "Post not found | Chess Coach Journal",
    };
  }

  return {
    title: `${post.title} | Chess Coach Journal`,
    description: post.summary,
  };
}

export default async function BlogPostPage({ params }: BlogPostPageProps) {
  const { slug } = await params;
  const post = await getBlogPostBySlug(slug);

  if (!post) {
    notFound();
  }

  const PostBody = await getPostMdxComponent(slug);

  if (!PostBody) {
    notFound();
  }

  return (
    <main className="max-w-3xl mx-auto px-6 py-12">
      <header className="mb-10 pb-6 border-b" style={{ borderColor: "rgba(232,224,208,0.12)" }}>
        <p className="text-xs tracking-[0.12em] uppercase mb-3" style={{ color: "var(--text-faint)" }}>
          Dev Blog
        </p>
        <h1 className="text-4xl mb-4" style={{ fontFamily: "var(--font-playfair)" }}>
          {post.title}
        </h1>
        <p className="text-sm mb-4" style={{ color: "var(--text-muted)" }}>
          {new Date(post.publishedAt).toLocaleDateString("en-GB", {
            day: "2-digit",
            month: "long",
            year: "numeric",
          })}
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
      </header>

      <article className="blog-content max-w-none">
        <PostBody />
      </article>
    </main>
  );
}
