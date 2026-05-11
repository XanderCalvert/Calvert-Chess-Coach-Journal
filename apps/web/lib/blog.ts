import "server-only";

import fs from "node:fs";
import path from "node:path";
import matter from "gray-matter";

type PostStatus = "published" | "draft";

export type BlogFrontmatter = {
  title: string;
  summary: string;
  publishedAt: string;
  tags: string[];
  status: PostStatus;
  featured?: boolean;
};

export type BlogPostMeta = BlogFrontmatter & {
  slug: string;
};

const BLOG_CONTENT_DIR = path.join(process.cwd(), "content", "blog");

function shouldIncludeDrafts(): boolean {
  return process.env.BLOG_INCLUDE_DRAFTS === "true";
}

function isValidStatus(value: unknown): value is PostStatus {
  return value === "published" || value === "draft";
}

function parsePostMeta(slug: string, source: string): BlogPostMeta {
  const { data } = matter(source);

  if (typeof data.title !== "string" || !data.title.trim()) {
    throw new Error(`Missing or invalid title in blog post: ${slug}`);
  }

  if (typeof data.summary !== "string" || !data.summary.trim()) {
    throw new Error(`Missing or invalid summary in blog post: ${slug}`);
  }

  if (typeof data.publishedAt !== "string" || !data.publishedAt.trim()) {
    throw new Error(`Missing or invalid publishedAt in blog post: ${slug}`);
  }

  if (!Array.isArray(data.tags) || data.tags.some((tag) => typeof tag !== "string")) {
    throw new Error(`Missing or invalid tags in blog post: ${slug}`);
  }

  if (!isValidStatus(data.status)) {
    throw new Error(`Missing or invalid status in blog post: ${slug}`);
  }

  if (typeof data.featured !== "undefined" && typeof data.featured !== "boolean") {
    throw new Error(`Invalid featured flag in blog post: ${slug}`);
  }

  return {
    slug,
    title: data.title,
    summary: data.summary,
    publishedAt: data.publishedAt,
    tags: data.tags,
    status: data.status,
    featured: data.featured,
  };
}

function getMdxFiles(): string[] {
  if (!fs.existsSync(BLOG_CONTENT_DIR)) {
    return [];
  }

  return fs.readdirSync(BLOG_CONTENT_DIR).filter((file) => file.endsWith(".mdx"));
}

function getPostSource(slug: string): string {
  const fullPath = path.join(BLOG_CONTENT_DIR, `${slug}.mdx`);
  return fs.readFileSync(fullPath, "utf8");
}

export async function getAllBlogPosts(): Promise<BlogPostMeta[]> {
  const includeDrafts = shouldIncludeDrafts();
  const files = getMdxFiles();
  const posts = files.map((file) => {
    const slug = file.replace(/\.mdx$/, "");
    return parsePostMeta(slug, getPostSource(slug));
  });

  return posts
    .filter((post) => includeDrafts || post.status === "published")
    .sort((a, b) => new Date(b.publishedAt).getTime() - new Date(a.publishedAt).getTime());
}

export async function getBlogPostBySlug(slug: string): Promise<BlogPostMeta | null> {
  const posts = await getAllBlogPosts();
  return posts.find((post) => post.slug === slug) ?? null;
}
