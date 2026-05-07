export const NAV_LINKS = [
  { label: "Features", href: "#features" },
  { label: "How it works", href: "#how-it-works" },
  { label: "GitHub", href: "https://github.com" },
] as const;

export const HERO = {
  eyebrow: "Post-game analysis · Plain English",
  headlinePrefix: "Understand why",
  headlineEmphasis: "fail.",
  headlineMid: "your moves ",
  subtitle:
    "Chess Coach Journal analyses your games with Stockfish, then explains your key mistakes in plain English — not centipawn scores. Track patterns. Study smarter.",
  primaryCta: { label: "Paste a PGN →", href: "#" },
  secondaryCta: { label: "See a sample analysis", href: "#" },
  stat: "600–1400 Elo · MVP",
} as const;

export type PieceCode =
  | "wK" | "wQ" | "wR" | "wB" | "wN" | "wP"
  | "bK" | "bQ" | "bR" | "bB" | "bN" | "bP";

export const PIECE_GLYPHS: Record<PieceCode, string> = {
  wK: "♔", wQ: "♕", wR: "♖", wB: "♗", wN: "♘", wP: "♙",
  bK: "♚", bQ: "♛", bR: "♜", bB: "♝", bN: "♞", bP: "♟",
};

export const PIECE_NAMES: Record<PieceCode, string> = {
  wK: "White King", wQ: "White Queen", wR: "White Rook",
  wB: "White Bishop", wN: "White Knight", wP: "White Pawn",
  bK: "Black King", bQ: "Black Queen", bR: "Black Rook",
  bB: "Black Bishop", bN: "Black Knight", bP: "Black Pawn",
};

export const FILES = ["a", "b", "c", "d", "e", "f", "g", "h"] as const;
export const RANKS = [8, 7, 6, 5, 4, 3, 2, 1] as const;

export const BOARD_POSITION: (PieceCode | null)[][] = [
  ["bR", "bN", "bB", "bQ", "bK", "bB", null, "bR"],
  ["bP", "bP", "bP",  null,  null, "bP", "bP", "bP"],
  [ null, null, null, null, "bP",  null, null, null],
  [ null, null, null, "bP",  null, null, null, null],
  [ null, null, null, null, "wP",  null, null, null],
  [ null, null, "wN", null,  null, "wN", null, null],
  ["wP", "wP", "wP", "wP",  null, "wP", "wP", "wP"],
  ["wR",  null, "wB", "wQ", "wK", "wB", null, "wR"],
];

export const BOARD_HIGHLIGHTS = {
  played: [[5, 2]] as [number, number][],
  best:   [[7, 5]] as [number, number][],
};

export const ANALYSIS = {
  label: "Key moment · Move 24 · Middlegame",
  mistakeType: "Blunder",
  cpLoss: "−187 cp",
  played: "Nxd5",
  best: "Rf1",
  explanation: [
    { text: "Playing " },
    { text: "Nxd5", bold: true },
    { text: " won a pawn but left the f2 square completely undefended. Black's rook on f8 could immediately capture on f2, forking your king and winning material back with interest. The engine suggests " },
    { text: "Rf1", bold: true },
    { text: " first — defending the weakness before going pawn-hunting. In chess, king safety takes priority over material gain." },
  ],
  tags: [
    { label: "Hanging piece", variant: "high" as const },
    { label: "Add club note", variant: "low" as const },
  ],
} as const;

export const PILLARS = [
  {
    num: "01",
    title: "Explain, not evaluate",
    body: "Engine scores translated into the language of chess ideas — threats, principles, and patterns — not just numbers.",
  },
  {
    num: "02",
    title: "Remember, not report",
    body: "Mistakes are tracked across every game. See that you keep missing back-rank tactics before your coach does.",
  },
  {
    num: "03",
    title: "Human feedback loop",
    body: "Record your chess club coach's notes alongside engine analysis. Mark where they agree — and where they don't.",
  },
] as const;

export const TREND = {
  title: "Accuracy · Last 10 games",
  subtitle: "As White · Club + Online",
  accuracy: "74%",
  accuracyLabel: "avg accuracy",
  sparklineData: [62, 58, 71, 69, 75, 72, 78, 73, 80, 74],
  tags: [
    { label: "Hanging piece ×7", variant: "high" as const },
    { label: "King safety ×4", variant: "med" as const },
    { label: "Missed capture ×3", variant: "med" as const },
    { label: "Poor development ×2", variant: "low" as const },
    { label: "Bad trade ×1", variant: "low" as const },
  ],
} as const;

export const CTA = {
  heading: "Start with a real game",
  body: "Paste any PGN and receive a full analysis — three key moments, plain-English explanations, and mistake tags — within a minute.",
  cta: { label: "Import your first game ↗", href: "#" },
} as const;

export const FOOTER = {
  logo: "♟ Chess Coach Journal",
  note: "Open source · Portfolio project · v1.0",
} as const;
