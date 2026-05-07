import { PILLARS } from "@/lib/homepage-data";

export default function FeaturesSection() {
  return (
    <section id="features" className="max-w-[860px] mx-auto w-full px-8 my-16">
      <div
        className="flex items-center gap-3 text-[11px] tracking-[0.1em] mb-10"
        style={{
          fontFamily: "var(--font-playfair)",
          fontStyle: "italic",
          color: "rgba(232,224,208,0.3)",
        }}
      >
        Three pillars
        <span className="flex-1 h-px" style={{ background: "rgba(232,224,208,0.1)" }} aria-hidden="true" />
      </div>

      <div
        className="grid grid-cols-3 rounded-[6px] overflow-hidden"
        role="list"
        style={{
          gap: "1px",
          background: "rgba(232,224,208,0.08)",
          border: "0.5px solid rgba(232,224,208,0.08)",
        }}
      >
        {PILLARS.map((pillar) => (
          <article
            key={pillar.num}
            role="listitem"
            className="px-6 py-7"
            style={{ background: "var(--bg)" }}
          >
            <div
              className="text-[10px] tracking-[0.1em] mb-4"
              style={{ fontFamily: "var(--font-dm-mono)", color: "var(--gold)", opacity: 0.7 }}
            >
              {pillar.num}
            </div>
            <h3
              className="text-[17px] font-semibold leading-[1.25] mb-[0.6rem]"
              style={{ fontFamily: "var(--font-playfair)", color: "#f0e8d8" }}
            >
              {pillar.title}
            </h3>
            <p
              className="text-[13px] leading-[1.65] font-light"
              style={{ color: "rgba(232,224,208,0.5)" }}
            >
              {pillar.body}
            </p>
          </article>
        ))}
      </div>
    </section>
  );
}
