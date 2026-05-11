# Live Move Coach — Sprint Plan (Engine-First, AI-Enhanced)

This document lays out a practical build path for a "what-if" coaching feature in the move section.

Goal: ship a strong teaching product with Stockfish-first logic, then add LLM narration as an enhancement layer.

---

## Product Goal

Let a player stop at any position in a game and:

- See top candidate moves ranked by engine quality
- Explore likely continuations (principal variations)
- Understand practical tradeoffs ("what this move improves", "what this move allows")
- Practice "try your move" thinking in a fast feedback loop

Success criteria:

- Fast and reliable live candidate analysis from any position
- Clear teaching signal without requiring LLM on day one
- AI narration can be added later without rewriting data contracts

---

## Product Principles

- Engine is the source of truth for move quality.
- Deterministic coaching signals come before generative explanations.
- Low-latency interaction matters more than polished prose.
- Keep the MVP focused on learning from your own games, not social features.

---

## Feature Definition (MVP)

At any position (FEN) in the reviewed game:

- Show top 3-5 candidate moves (MultiPV)
- Show evaluation after each move
- Show delta vs best move
- Label each move (`Best`, `Playable`, `Inaccuracy`, `Mistake`, `Blunder`)
- Expand a move to show 3-5 ply principal variation
- Allow "Try your move" and instant re-evaluation

---

## Sprint 1 — Engine Position Explorer (No AI)

Outcome: fully working "what-if" explorer powered by Stockfish only.

### Backend

- [x] Add position analysis endpoint by FEN + settings (`multipv`, `time_ms`).
- [x] Return candidate moves with UCI, eval, mate, and PV (SAN resolved client-side via chess.js).
- [x] Cache responses by normalised key: `sha256(fen) + multipv + time_ms + engine_version` (24 h Laravel file cache).
- [x] Rate-limit endpoint: 20 req/min per IP.

### Frontend

- [x] Add a move explorer panel on game analysis page.
- [x] Render candidate move cards sorted by engine rank.
- [x] Show eval (White-positive) and delta on each card.
- [x] Expand card to view PV line.
- [x] Add "Try your move" interaction:
  - user drags a piece on board (explorer mode)
  - resulting FEN sent for analysis
  - candidate list refreshes
- [x] Add compact stats legend/explainer for analysis metrics:
  - Accuracy (overall and W/B split)
  - Elo estimate (single-game proxy, not rating-system Elo)
  - Blunders/Mistakes/Inaccuracies W/B counts

### Tests

- [x] API feature test for valid candidate response schema.
- [x] API test for invalid FEN handling.
- [x] API test that cache hit returns quickly and same payload shape.
- [x] Web tests for panel states: loading, loaded, error, empty (logic + fetch layer; DOM rendering tests require jsdom).

### Done Definition

- User can explore candidate moves from any game position in under ~2 seconds for initial result.
- Candidate list is stable and deterministic for same engine settings.

---

## Sprint 2 — Deterministic Coaching Layer (No AI)

Outcome: coaching value appears even with no LLM integration.

### Backend

- Add classification rules from `delta_from_best` thresholds.
- Add deterministic teaching metadata:
  - `themes[]` (development, king safety, center control, material, activity)
  - `tactical_flags[]` (hanging piece, fork risk, pin/skewer risk, mate threat)
  - `threat_awareness[]` (detected threats before move, whether player addressed or ignored each threat)
  - `risk_note` template output
- Store position coaching summaries for reused positions.

Threat-awareness pass (pre/post move):
- Before move: detect immediate tactical threats (mate threats, hanging pieces, forks, skewers, discovered attacks, overloaded defenders)
- After move: classify response (`addressed`, `ignored`, `worsened`)
- Aggregate consecutive misses to surface patterns like "ignored fork threat for 3 consecutive moves"

### Frontend

- Add teaching labels and confidence hints to candidate cards.
- Add "Why this move" and "Main risk" text from deterministic templates.
- Add "Threat check" callout:
  - What the opponent threatened
  - Whether the chosen move solved it
- Add hint mode toggle:
  - Level 1: hide evals, show ranked options
  - Level 2: show evals and labels
  - Level 3: show full PV and risk notes

### Tests

- Unit tests for classification thresholds.
- Unit tests for deterministic theme extraction logic.
- Snapshot/contract tests for coaching payload shape.

### Done Definition

- Users get useful move guidance without any generative text.
- The same position produces consistent labels and coaching flags.

---

## Sprint 3 — AI Narration Enhancement

Outcome: add plain-English coaching on top of trusted structured data.

### Backend

- Introduce `CoachNarrationService` behind a feature flag.
- Input contract: engine + deterministic coaching object (never raw board alone).
- Cache explanations by:
  - `fen`
  - `candidate_set_hash`
  - `rating_band`
  - `tone`
  - `prompt_version`
- Add fallback templates when LLM is disabled or errors.

### Prompt Strategy

- Require engine ranking compliance in prompt.
- Keep explanation short and concrete.
- Include one practical "before-you-move" checklist cue.
- Keep temperature low.

### Frontend

- Show AI explanation per selected candidate move.
- Add regenerate button (manual only, no auto-retry loops).
- Show deterministic fallback text instantly; replace with AI text when ready.

### Tests

- Contract tests for narration request/response payload.
- Integration test for cache reuse and fallback behavior.
- Guardrail tests ensuring AI output does not reorder engine ranking.

### Done Definition

- AI adds teaching clarity without slowing core move explorer.
- If AI fails, user still has complete deterministic coaching.

---

## Sprint 4 — Personalization and Training UX

Outcome: turn move explorer into a repeatable training loop.

### Features

- Personalized prompts by rating band and recurring weaknesses.
- "Guess the move" mini-quiz mode before revealing candidates.
- Session recap: common errors in explored branches.
- Save studied positions and revisit them.

### Done Definition

- User can run a focused training session from one game and get a short, actionable recap.

---

## API Contract (Proposed)

Endpoint idea:

- `POST /api/positions/analyze`

Request:

- `fen` (string, required)
- `depth` (int, optional)
- `multipv` (int, optional)
- `time_ms` (int, optional)
- `mode` (`fast` | `deep`, optional)

Response shape:

- `fen`
- `side_to_move`
- `engine` (`name`, `version`, `depth`)
- `candidates[]`
  - `rank`
  - `move_uci`
  - `move_san`
  - `eval_cp`
  - `mate`
  - `delta_from_best`
  - `pv[]`
  - `classification`
  - `themes[]`
  - `tactical_flags[]`
  - `threat_awareness[]`
  - `risk_note`
- `cache` (`hit`, `key`)
- `timing_ms`

---

## Data Model Additions (Proposed)

- `position_analyses`
  - `id`
  - `fen_hash`
  - `fen`
  - `engine_version`
  - `depth`
  - `multipv`
  - `payload_json`
  - `created_at`

- `position_coaching`
  - `id`
  - `position_analysis_id`
  - `candidate_rank`
  - `classification`
  - `themes_json`
  - `tactical_flags_json`
  - `risk_note`
  - `ai_explanation` (nullable)
  - `prompt_version` (nullable)
  - `created_at`

---

## Competitive Feature Ideas (Chess.com / Chessigma-Inspired)

Keep these as idea inputs, not immediate MVP commitments.

### High-value near-term ideas

- "Top 3 candidate moves" panel with clear quality labels
- Immediate eval swing feedback for played move vs best move
- Lightweight coaching tips tied to tactical themes
- Branch exploration from any move in game review

### Medium-term ideas

- "Why not this move?" for common tempting alternatives
- Position difficulty indicator (how easy it is to find best move)
- Time-pressure suggestions (practical move vs absolute best move)
- Opening context snippets (plan ideas, not opening encyclopedias)

### Longer-term ideas

- Adaptive lesson feed from recurring mistakes across games
- Puzzle generation from personal blunders
- Weekly training plan linked to your own game data

---

## Non-Goals (For Now)

- Building a full opening explorer/database
- Building social play or matchmaking features
- Replacing deterministic coaching with LLM-only logic
- Deep cloud-engine complexity before local flow is stable

---

## Risks and Mitigations

- Latency too high for "live" feeling
  - Use cache-first + shallow-first + deepen later.
- Inconsistent coaching quality
  - Keep deterministic baseline and clear rule thresholds.
- AI hallucinations
  - Force structured input, low-temp prompting, and engine-rank guardrails.
- Scope creep
  - Gate non-MVP ideas behind backlog tags.

---

## Backlog Parking Lot

Use this section to append ideas as they come up:

- [ ] Add "explain only if eval changes > X" filter
- [ ] Add "blunder alert" board overlays
- [ ] Add "best practical move" mode for rapid/blitz contexts
- [ ] Add "coach voice style" presets
- [ ] Add "compare my move to top 3" side-by-side line viewer
- [ ] Add persistent position-analysis cache table (`position_analyses`) for warm-start across server restarts
- [ ] Add queued preloader job to warm cache for game move positions (`fen_before`) after game analysis finishes
- [ ] Add progressive depth strategy: quick shallow response first, optional deepen/refine job for key moments
- [x] Add user-facing stats legend ("How these numbers are calculated")

