import { HERO } from "@/lib/homepage-data";

export default function HeroSection() {
  return (
    <section className="px-8 pt-20 pb-12 max-w-[860px] mx-auto w-full">
      <div
        className="flex items-center gap-2 text-[11px] tracking-[0.15em] uppercase mb-6 font-[500]"
        style={{ fontFamily: "var(--font-dm-mono)", color: "var(--gold)" }}
      >
        <span aria-hidden="true" className="w-7 h-px" style={{ background: "var(--gold)", opacity: 0.6 }} />
        {HERO.eyebrow}
      </div>

      <h1
        className="text-[clamp(36px,6vw,58px)] font-semibold leading-[1.12] mb-6"
        style={{ fontFamily: "var(--font-playfair)", color: "#f0e8d8" }}
      >
        {HERO.headlinePrefix}
        <br />
        {HERO.headlineMid}
        <em style={{ fontStyle: "italic", color: "var(--gold)" }}>{HERO.headlineEmphasis}</em>
      </h1>

      <p
        className="text-[16px] leading-[1.7] mb-10 max-w-[520px] font-light"
        style={{ color: "rgba(232,224,208,0.6)" }}
      >
        {HERO.subtitle}
      </p>

      <div className="flex gap-3 items-center flex-wrap">
        <a
          href={HERO.primaryCta.href}
          className="text-[13px] font-medium tracking-[0.02em] no-underline px-[18px] py-[9px] rounded-[4px] transition-colors duration-200"
          style={{ background: "var(--gold)", color: "var(--bg)" }}
        >
          {HERO.primaryCta.label}
        </a>
        <a
          href={HERO.secondaryCta.href}
          className="text-[13px] no-underline px-[18px] py-[9px] rounded-[4px] transition-all duration-200"
          style={{
            border: "0.5px solid rgba(232,224,208,0.25)",
            color: "rgba(232,224,208,0.7)",
          }}
        >
          {HERO.secondaryCta.label}
        </a>
        <span
          className="text-[11px] tracking-[0.08em] ml-2 pl-4"
          style={{
            fontFamily: "var(--font-dm-mono)",
            color: "rgba(232,224,208,0.35)",
            borderLeft: "0.5px solid rgba(232,224,208,0.15)",
          }}
        >
          {HERO.stat}
        </span>
      </div>
    </section>
  );
}
