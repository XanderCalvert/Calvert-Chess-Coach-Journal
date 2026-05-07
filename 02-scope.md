# Chess Coach Journal — Scope & Roadmap

## MVP (6–10 weeks)
> *The smallest version that makes a game feel analysed, explained, and remembered.*

### Features
1. **PGN Import** — paste a PGN string; app parses moves, headers, metadata; validation with readable errors
2. **Stockfish Analysis** — server-side evaluation per position; centipawn scores, best move, 3–4 move engine line stored; background job with progress indicator
3. **Key Moment Identification** — top 3 moves by centipawn loss; classified as blunder (>150cp), mistake (>50cp), inaccuracy (>20cp); one per game phase preferred
4. **Plain-English Explanations** — LLM-generated, 2–4 sentences per key moment; factually grounded in position data
5. **Played Move vs Best Move** — side-by-side board view with engine line below
6. **Mistake Tagging** — one primary tag per key moment; heuristic detection for MVP tags; user can override
7. **Game Summary** — LLM-generated paragraph; opening, accuracy, key moment count, top theme
8. **Basic Trend Tracking** — per-game summary row stored; trends page with table and line chart
9. **Manual Notes / Club Feedback** — freetext notes on games and key moments; coach agreed/disagreed toggle

### MVP Definition of Done
- User can paste a PGN and receive a fully analysed game within 60 seconds
- Three key moments identified with explanations describing the *idea*, not just the score
- Each mistake has a tag that the user can override
- User can add a club note and mark coach agreement
- Trends page shows accuracy and mistake frequency across all analysed games
- Dashboard provides a useful at-a-glance summary
- App is deployed and accessible at a public URL
- README explains the architecture and how to run it locally

---

## Phase 1

- **Chess.com & Lichess Import** — OAuth or username-based; batch import with deduplication
- **Better Recurring Mistake Detection** — across last 20 games; grouped by phase and colour; plain-English summary
- **Opening Awareness** — ECO tracking; accuracy and blunder rate by opening
- **Personal Dashboard v2** — improvement indicator (last 5 vs previous 5 games)
- **Study Recommendations** — surface recommended theme based on top recurring mistake tag
- **Improved Explanation Quality** — refined prompts with material counts, game phase, rating level
- **Responsive Layout** — tablet and mobile support; collapsible panels; touch-friendly navigation

---

## Phase 2

- **Pattern Clustering** — group similar positions via FEN fingerprinting or pawn structure similarity
- **Generated Training Puzzles** — from key moments with known best moves; track solve rate
- **Game Replay with Coach-Style Commentary** — full move-by-move LLM comments; expanded notes on key moments
- **Rating-Level Explanation Mode** — beginner / club / experienced adapts vocabulary and depth
- **Stronger Endgame & Opening Insights** — detect endgame types; track opening deviation move
- **Human Coach vs Engine Comparison** — side-by-side view; track disagreement patterns over time

---

## Stretch Ideas
- **Voice commentary** — text-to-speech narration of explanations for on-the-go review
- **Explain like I'm 800 / 1200 / 1600** — dynamic explanation depth tied to Elo bucket
- **Personal weakness score** — composite 0–100 score weighted by phase and frequency
- **Club review mode** — projector-friendly; hides evaluations; forces guess before reveal
- **Exportable annotated game reports** — PDF or PGN with all annotations; shareable with a coach
- **Coach dashboard** — separate view for a coach to review multiple students' games
- **AI-generated study plan** — 4-week structured plan from full game history and weakness profile

---

## What to Avoid Initially
- Chess.com / Lichess import (Phase 1)
- Puzzle generation (Phase 2)
- Voice commentary, club presentation mode (stretch)
- Opening database lookup (Phase 1 at earliest)
- Any social or multiplayer feature
- Rating-adaptive explanations (Phase 1)
