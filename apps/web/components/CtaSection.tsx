import { CTA } from "@/lib/homepage-data";

export default function CtaSection() {
  return (
    <div className="max-w-[860px] mx-auto w-full px-8 mb-16">
      <div
        className="rounded-[6px] px-8 py-10 text-center"
        style={{
          border: "0.5px solid rgba(201,168,76,0.25)",
          background: "rgba(201,168,76,0.04)",
        }}
      >
        <h2
          className="text-[22px] mb-2"
          style={{ fontFamily: "var(--font-playfair)", color: "#f0e8d8" }}
        >
          {CTA.heading}
        </h2>
        <p
          className="text-[13px] font-light leading-[1.6] mb-6"
          style={{ color: "rgba(232,224,208,0.45)" }}
        >
          {CTA.body}
        </p>
        <a
          href={CTA.cta.href}
          className="inline-block text-[13px] font-medium tracking-[0.02em] no-underline px-[18px] py-[9px] rounded-[4px] transition-colors duration-200"
          style={{ background: "var(--gold)", color: "var(--bg)" }}
        >
          {CTA.cta.label}
        </a>
      </div>
    </div>
  );
}
