"use client";

import {
  BOARD_POSITION,
  BOARD_HIGHLIGHTS,
  ANALYSIS,
  PIECE_GLYPHS,
  PIECE_NAMES,
  FILES,
  RANKS,
  type PieceCode,
} from "@/lib/homepage-data";

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

export default function ChessBoard() {
  return (
    <div className="max-w-[860px] mx-auto w-full px-8">
      <div
        className="rounded-[8px] overflow-hidden grid"
        style={{
          background: "var(--surface)",
          border: "0.5px solid rgba(232,224,208,0.1)",
          gridTemplateColumns: "auto 1fr",
        }}
      >
        {/* Board */}
        <div
          role="grid"
          aria-label="Chess position: key moment, move 24"
          className="grid shrink-0"
          style={{
            gridTemplateColumns: "repeat(8, 36px)",
            gridTemplateRows: "repeat(8, 36px)",
            borderRight: "0.5px solid rgba(232,224,208,0.08)",
          }}
        >
          {BOARD_POSITION.map((row, r) =>
            row.map((piece, c) => {
              const isLight = (r + c) % 2 === 0;
              const isPlayed = BOARD_HIGHLIGHTS.played.some(([pr, pc]) => pr === r && pc === c);
              const isBest = BOARD_HIGHLIGHTS.best.some(([pr, pc]) => pr === r && pc === c);
              const file = FILES[c];
              const rank = RANKS[r];
              const pieceName = piece ? PIECE_NAMES[piece as PieceCode] : null;
              const ariaLabel = pieceName
                ? `${file}${rank}, ${pieceName}`
                : `${file}${rank}, empty`;

              let bg = isLight ? "#2a231a" : "#1a1410";
              if (isPlayed) bg = "rgba(220,60,60,0.25)";
              if (isBest) bg = "rgba(80,200,120,0.2)";

              return (
                <div
                  key={`${r}-${c}`}
                  role="gridcell"
                  aria-label={ariaLabel}
                  className="flex items-center justify-center text-[20px] select-none"
                  style={{ background: bg }}
                >
                  {piece && (
                    <span
                      aria-hidden="true"
                      style={{
                        textShadow:
                          (piece as PieceCode)[0] === "w"
                            ? "0 1px 2px rgba(0,0,0,0.6)"
                            : "0 1px 2px rgba(0,0,0,0.8)",
                        filter: (piece as PieceCode)[0] === "b" ? "brightness(0.75)" : undefined,
                      }}
                    >
                      {PIECE_GLYPHS[piece as PieceCode]}
                    </span>
                  )}
                </div>
              );
            })
          )}
        </div>

        {/* Analysis panel */}
        <div className="flex flex-col gap-3 px-6 py-5">
          <span
            className="text-[10px] tracking-[0.12em] uppercase"
            style={{ fontFamily: "var(--font-dm-mono)", color: "rgba(232,224,208,0.3)" }}
          >
            {ANALYSIS.label}
          </span>

          <div
            className="inline-flex items-center gap-[5px] px-2 py-[3px] rounded-[3px] text-[11px] tracking-wider w-fit"
            style={{
              fontFamily: "var(--font-dm-mono)",
              background: "rgba(220,60,60,0.15)",
              border: "0.5px solid rgba(220,60,60,0.3)",
              color: "#e07070",
            }}
          >
            <span aria-hidden="true">⚠</span>
            {ANALYSIS.mistakeType}
            <span style={{ color: "rgba(232,224,208,0.35)", marginLeft: 4 }}>
              {ANALYSIS.cpLoss}
            </span>
          </div>

          <div className="flex gap-2 items-center">
            <span className="text-[11px]" style={{ color: "rgba(232,224,208,0.3)" }}>
              Played
            </span>
            <span
              className="text-[12px] px-2 py-[3px] rounded-[3px]"
              style={{
                fontFamily: "var(--font-dm-mono)",
                background: "rgba(220,60,60,0.12)",
                color: "#d07070",
                border: "0.5px solid rgba(220,60,60,0.2)",
              }}
            >
              {ANALYSIS.played}
            </span>
            <span className="text-[11px] ml-2" style={{ color: "rgba(232,224,208,0.3)" }}>
              Best
            </span>
            <span
              className="text-[12px] px-2 py-[3px] rounded-[3px]"
              style={{
                fontFamily: "var(--font-dm-mono)",
                background: "rgba(80,200,120,0.1)",
                color: "#70c090",
                border: "0.5px solid rgba(80,200,120,0.2)",
              }}
            >
              {ANALYSIS.best}
            </span>
          </div>

          <div className="h-px" style={{ background: "rgba(232,224,208,0.08)" }} />

          <p className="text-[13px] leading-[1.65] font-light" style={{ color: "rgba(232,224,208,0.7)" }}>
            {ANALYSIS.explanation.map((part, i) =>
              "bold" in part && part.bold ? (
                <strong key={i} style={{ color: "var(--gold)", fontWeight: 500 }}>
                  {part.text}
                </strong>
              ) : (
                <span key={i}>{part.text}</span>
              )
            )}
          </p>

          <div className="flex gap-[6px] flex-wrap mt-1">
            {ANALYSIS.tags.map((tag) => (
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
      </div>
    </div>
  );
}
