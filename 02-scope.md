# Chess Coach Journal — Scope & Roadmap

## MVP (6–10 weeks)
> *The smallest version that makes a game feel analysed, explained, and remembered.*

## Product Direction

### Vision

Build a deterministic chess intelligence platform with AI as an interpretation and coaching layer.

The product must deliver core value without AI:
- PGN ingestion
- Engine analysis
- Tactical classification
- Trend aggregation
- Behaviour tracking
- Visualisation

AI enhances the foundation with:
- explanations
- summaries
- conversational coaching
- natural language interaction

### Core Insight

Most chess tools stop at accuracy percentages, blunder counts, and eval graphs. This product should identify recurring human decision-making patterns and answer:

> Why do you repeatedly lose in this specific way?

### Core Product Framing

The app is a **quantified-self platform for chess improvement**, not a generic Stockfish frontend. The central product value the user should feel is:

> "This understands my chess."

Every architectural and UX decision should be measured against that principle.

---

### Pipeline Direction — Sync vs Analysis vs Coaching

The product treats sync, analysis, and coaching as **distinct states** that should never be conflated:

```text
Imported   — game exists locally with PGN + metadata
Analysed   — Stockfish evaluation completed
Coached    — coaching/key-moment/trend data generated
```

The legacy "sync account → import all games → immediately analyse every game with Stockfish" model is replaced by a staged pipeline:

```text
Sync metadata
    ↓
Analyse selectively (auto for a small recent subset, on demand otherwise)
    ↓
Generate coaching insights from analysed games
```

This split aligns with the long-term self-coaching direction and significantly improves scalability, cost control, and UX. See [05-analysis-pipeline.md](./05-analysis-pipeline.md) and [03-architecture.md](./03-architecture.md) for the technical shape.

Key implications for scope:

- Sync is a **cheap, fast metadata import** (PGN, moves, result, opening, date, time control, ratings, opponent).
- Stockfish analysis is **the expensive layer** — auto-run only for a small recent subset (MVP target: most-recent 5 games per sync), on demand for everything else.
- Coaching depth grows progressively as more games are analysed; the coaching layer reads analysed games, so unlocking richer coaching is a natural reward for analysing more.
- Premium tier value should focus on **long-term personalised improvement insights**, not on simply paying for Stockfish access.

---

### Features
1. **Connected Account Sync (primary import path)** — link a Chess.com / Lichess identity; app pulls PGN, moves, result, opening, date, time control, ratings, and opponent for each game; metadata import is the cheap, default operation
2. **Manual PGN Import (secondary)** — paste a PGN for one-off / OTB / club / training positions / unsupported sources; reframed as "Import PGN manually" rather than the primary entry point
3. **Selective Stockfish Analysis** — server-side evaluation per position; centipawn scores, best move, 3–4 move engine line stored; auto-run for a small recent subset after sync (MVP: most-recent 5 games), on demand via "Analyse this game" for everything else; analysis state surfaced in UI (`pending` / `queued` / `analysing` / `analysed` / `failed`)
4. **Key Moment Identification** — top 3 moves by centipawn loss; classified as blunder (>150cp), mistake (>50cp), inaccuracy (>20cp); one per game phase preferred
5. **Plain-English Explanations** — LLM-generated, 2–4 sentences per key moment; factually grounded in position data
6. **Played Move vs Best Move** — side-by-side board view with engine line below
7. **Mistake Tagging** — one primary tag per key moment; heuristic detection for MVP tags; user can override
8. **Game Summary** — LLM-generated paragraph; opening, accuracy, key moment count, top theme
9. **Basic Trend Tracking** — per-game summary row stored; trends page with table and line chart; coaching depth grows progressively as more games are analysed
10. **Manual Notes / Club Feedback** — freetext notes on games and key moments; coach agreed/disagreed toggle

### MVP Definition of Done
- User can connect a chess account and the app imports games quickly as **metadata-only** (no full Stockfish run-through of the entire archive)
- After sync, the most-recent small subset (target: 5 games) is auto-analysed so coaching widgets have immediate signal
- Any other imported game can be analysed on demand from the games list or game page in ~60 seconds
- Manual PGN import remains available as a secondary "Import PGN manually" path
- Three key moments per analysed game with explanations describing the *idea*, not just the score
- Each mistake has a tag that the user can override
- User can add a club note and mark coach agreement
- Trends page shows accuracy and mistake frequency across all analysed games (improves as more games are analysed)
- Dashboard provides a useful at-a-glance summary
- App is deployed and accessible at a public URL
- README explains the architecture and how to run it locally

---

### Free vs Premium Alignment

The staged pipeline maps cleanly to future monetisation. This is not an MVP feature, but architecture should not block it:

**Cheap layer (free tier candidate)**
- Connecting accounts
- Syncing games (metadata)
- Browsing / replaying games
- A limited analysis quota
- Basic coaching surfaces

**Expensive / value layer (premium tier candidate)**
- Unlimited analysis
- Priority queue
- Advanced coaching trends
- Opening reports
- Recurring mistake detection
- Longitudinal improvement tracking
- Future AI explanations and conversational coaching

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
- **Blunder fingerprinting dashboard** — frequency analysis across blunder type, phase, pressure level, and tactical motif
- **Opening confidence breakdown** — opening exit accuracy and decision collapse point by move range
- **Position-type performance map** — performance by open/closed/tactical/endgame structures
- **Attack vs defence personality profile** — identify aggressive/defensive decision tendencies over time
- **Club review mode** — projector-friendly; hides evaluations; forces guess before reveal
- **Exportable annotated game reports** — PDF or PGN with all annotations; shareable with a coach
- **Coach dashboard** — separate view for a coach to review multiple students' games
- **AI-generated study plan** — 4-week structured plan from full game history and weakness profile

---

## What to Avoid Initially
- **Lichess** import (Phase 1 — **Chess.com** username/archive import is now in scope and implemented)
- Puzzle generation (Phase 2)
- Voice commentary, club presentation mode (stretch)
- Opening database lookup (Phase 1 at earliest)
- Any social or multiplayer feature
- Rating-adaptive explanations (Phase 1)

---

## Immediate Priority From Current Build State

Recent shipped (commit 748bc51, May 2026):

- [x] **Sync split from analysis (backend):** `ImportExternalGameJob` no longer auto-dispatches `AnalyseGameJob`; `SyncChessComAccountJob` defers a `QueueRecentAnalysisJob` (delay 60s) that picks the most-recent N pending games per account (default `5`, `CHESS_AUTO_ANALYSE_ON_SYNC`).
- [x] **On-demand analysis endpoint:** `POST /api/v1/games/{id}/analyse` (ownership-gated, returns 202) with BFF route at `/api/games/{id}/analyse`.
- [x] **Games list "Analyse this game":** per-row analysis status badge plus an inline analyse button for `pending` / `failed` rows; optimistic update; account filter pills.
- [x] **Manual PGN copy reframed:** games list empty-state now positions PGN paste as a side-door for over-the-board games.

Focus next on:

1. **Game page works pre-analysis** — board replay, opening/result metadata, and "Analyse this game" CTA visible before engine analysis runs (`pending`); locked coaching panels with placeholder; retry control on `failed`; polling while `queued` / `analysing`.
2. **Migrate `analysis_status` enum** to `pending` / `queued` / `analysing` / `analysed` / `failed` and add `analysis_requested_at` (currently still `pending` / `running` / `complete` / `failed`).
3. **Manual PGN import opt-in analyse toggle** — `POST /api/v1/games` for paste should default to `analysis_status = pending`; current behaviour still auto-dispatches `AnalyseGameJob`.
4. **Plain-English explanations** on key moments (key-moment selection + UI is already done; explanations are the next layer).
5. **Heuristic mistake tagging** (MVP subset, conservative rules).
6. **Notes + coach agreement** to complete the journal loop.
7. **Dedicated trends/dashboard pages** after explanation/tagging quality is solid.
