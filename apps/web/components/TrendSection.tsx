import { TREND } from "@/lib/homepage-data";
import Sparkline from "@/components/Sparkline";

const TAG_STYLES: Record<"high" | "med" | "low", React.CSSProperties> = {
  high: {
    background: "rgba(220,60,60,0.1)",
    color: "#d07070",
    borderColor: "rgba(220,60,60,0.2)",
  },
  med: {
    background: "rgba(201,168,76,0.1)",
    color: "var(--gold)",
    borderColor: "rgba(201,168,76,0.2)",
  },
  low: {
    background: "rgba(232,224,208,0.06)",
    color: "rgba(232,224,208,0.4)",
    borderColor: "rgba(232,224,208,0.12)",
  },
};

export default function TrendSection() {
  return (
    <section className="max-w-[860px] mx-auto w-full px-8 mb-16">
      <div
        className="flex items-center gap-3 text-[11px] tracking-[0.1em] mb-10"
        style={{
          fontFamily: "var(--font-playfair)",
          fontStyle: "italic",
          color: "rgba(232,224,208,0.3)",
        }}
      >
        Trends over time
        <span className="flex-1 h-px" style={{ background: "rgba(232,224,208,0.1)" }} aria-hidden="true" />
      </div>

      <div
        className="rounded-[8px] p-6"
        style={{
          background: "var(--surface)",
          border: "0.5px solid rgba(232,224,208,0.1)",
        }}
      >
        <div className="flex justify-between items-start mb-5">
          <div>
            <p
              className="text-[15px] mb-[3px]"
              style={{ fontFamily: "var(--font-playfair)", color: "var(--text)" }}
            >
              {TREND.title}
            </p>
            <p
              className="text-[12px] tracking-[0.05em]"
              style={{ fontFamily: "var(--font-dm-mono)", color: "rgba(232,224,208,0.35)" }}
            >
              {TREND.subtitle}
            </p>
          </div>
          <div className="text-right">
            <p
              className="text-[22px] font-[500]"
              style={{ fontFamily: "var(--font-dm-mono)", color: "var(--gold)" }}
              aria-label={`Average accuracy: ${TREND.accuracy}`}
            >
              {TREND.accuracy}
            </p>
            <p
              className="text-[10px] tracking-[0.08em]"
              style={{ color: "rgba(232,224,208,0.3)" }}
            >
              {TREND.accuracyLabel}
            </p>
          </div>
        </div>

        <Sparkline data={TREND.sparklineData} />

        <div
          className="flex gap-[6px] flex-wrap mt-4 pt-4"
          style={{ borderTop: "0.5px solid rgba(232,224,208,0.08)" }}
        >
          <span
            className="text-[11px] tracking-[0.06em] mr-1"
            style={{ fontFamily: "var(--font-dm-mono)", color: "rgba(232,224,208,0.3)" }}
          >
            Top tags ·
          </span>
          {TREND.tags.map((tag) => (
            <span
              key={tag.label}
              className="text-[11px] px-[10px] py-[3px] rounded-[20px] tracking-[0.04em]"
              style={{
                fontFamily: "var(--font-dm-mono)",
                border: "0.5px solid",
                ...TAG_STYLES[tag.variant],
              }}
            >
              {tag.label}
            </span>
          ))}
        </div>
      </div>
    </section>
  );
}
