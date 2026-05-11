import Link from "next/link";
import { FOOTER } from "@/lib/homepage-data";

export default function Footer() {
  return (
    <footer
      className="max-w-[860px] mx-auto w-full px-8 py-6 flex justify-between items-center"
      style={{ borderTop: "0.5px solid rgba(232,224,208,0.08)" }}
    >
      <span
        className="text-[13px]"
        style={{ fontFamily: "var(--font-playfair)", color: "rgba(232,224,208,0.3)" }}
      >
        {FOOTER.logo}
      </span>
      <div className="flex items-center gap-4">
        <Link
          href={FOOTER.devBlog.href}
          className="text-[10px] tracking-[0.08em] hover:underline"
          style={{ fontFamily: "var(--font-dm-mono)", color: "rgba(232,224,208,0.35)" }}
        >
          {FOOTER.devBlog.label}
        </Link>
        <span
          className="text-[10px] tracking-[0.08em]"
          style={{ fontFamily: "var(--font-dm-mono)", color: "rgba(232,224,208,0.2)" }}
        >
          {FOOTER.note}
        </span>
      </div>
    </footer>
  );
}
