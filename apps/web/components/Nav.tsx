import Link from "next/link";
import { NAV_LINKS } from "@/lib/homepage-data";

export default function Nav() {
  return (
    <nav
      aria-label="Main navigation"
      className="flex justify-between items-center px-8 py-5 border-b sticky top-0 z-50"
      style={{
        borderColor: "rgba(232,224,208,0.12)",
        background: "rgba(15,13,11,0.92)",
        backdropFilter: "blur(8px)",
      }}
    >
      <span
        className="flex items-center gap-2 text-[17px] font-semibold tracking-[0.01em]"
        style={{ fontFamily: "var(--font-playfair)", color: "var(--text)" }}
      >
        <span aria-hidden="true">♟</span>
        Chess Coach Journal
      </span>

      <ul className="flex gap-6 items-center list-none">
        {NAV_LINKS.map((link) => (
          <li key={link.label}>
            <a
              href={link.href}
              className="nav-link text-[13px] tracking-[0.03em] no-underline"
            >
              {link.label}
            </a>
          </li>
        ))}
        <li>
          <Link
            href="/games"
            className="nav-link text-[13px] tracking-[0.03em] no-underline"
          >
            My games
          </Link>
        </li>
        <li>
          <Link
            href="/settings"
            className="nav-link text-[13px] tracking-[0.03em] no-underline"
          >
            Settings
          </Link>
        </li>
        <li>
          <Link
            href="/import"
            className="text-[13px] font-medium tracking-[0.02em] no-underline px-[18px] py-2 rounded-[4px] transition-colors duration-200"
            style={{ background: "var(--gold)", color: "var(--bg)" }}
          >
            Import a game
          </Link>
        </li>
      </ul>
    </nav>
  );
}
