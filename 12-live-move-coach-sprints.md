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

## Sprint 2 — Deterministic Coaching Layer (No AI) ✓

Outcome: coaching value appears even with no LLM integration.

### Backend

- [x] Add classification rules from `delta_from_best` thresholds.
- [x] Add deterministic teaching metadata:
  - [x] `themes[]` (development, king safety, center control, material, activity)
  - [x] `tactical_flags[]` (soft flags: `forced_mate_present`, `engine_prefers_capture`, `hanging_piece`, `possible_fork`, `possible_pin`)
  - [x] `threat_awareness` (threats before/after move, response classification, confidence score)
  - [x] `risk_note` template output
- [x] `consecutive_miss_count` column — populated by post-loop pass (Sprint 3 aggregation)
- [x] `game_phase`, `complexity_score` columns added for context
- [x] `ai_explanation`, `ai_explanation_status`, `ai_explanation_model` columns added as Sprint 5 slots
- [x] `coaching_version` column for targeted backfills
- [x] `BackfillCoachingData` artisan command (`coaching:backfill`) — no Stockfish calls

### Frontend

- [x] Teaching labels and confidence hints on candidate cards (Strong / Solid / Risky)
- [x] Theme tags and "Threat check" callout in move detail panel
- [x] `risk_note` template output in full analysis mode
- [x] Hint mode toggle: Training / Guided / Full Analysis
  - Training: hide evals, show ranked SAN only
  - Guided: show evals, labels, themes, threat callout (medium/high confidence)
  - Full Analysis: everything including risk note

### Tests

- [x] Unit tests for classification thresholds (all boundaries + promotions)
- [x] Unit tests for deterministic theme extraction (12 cases including en passant, promotion, stalemate)
- [x] Contract tests for coaching payload shape and allowed flag set
- [x] Feature test: job writes coaching columns with correct shape
- [x] Feature test: game API response includes coaching fields on moves

### Done Definition

- [x] Users get useful move guidance without any generative text.
- [x] The same position produces consistent labels and coaching flags.

---

## Sprint 3 — AI Narration Layer

Outcome: AI reads the story the structured data is already telling — narrating patterns, not re-analysing positions.

### Design Principle

The AI is a narrator, not an analyst. It receives the fully-populated coaching columns as a structured object and writes plain-English coaching from that. It never receives raw FEN or engine output alone. The deterministic layer always remains complete and functional without AI.

### Backend

- Introduce `CoachNarrationService` behind a feature flag.
- Input contract: serialised coaching columns only (`themes`, `tactical_flags`, `threat_awareness`, `consecutive_miss_count`, `game_phase`, `complexity_score`, `risk_note`, `classification`). Never raw board state alone.
- Persist output to `moves.ai_explanation` — generated once, re-served from column until `ai_explanation_model` changes or `coaching_version` bumps.
- Cache key: `fen` + `coaching_version` + `ai_explanation_model` + `prompt_version` — avoids re-generation on unrelated changes.
- Implement post-loop consecutive miss aggregation in `AnalyseGameJob`: walk move sequence, count runs where `threat_awareness.response = 'not_addressed'`, write `consecutive_miss_count`.
- Add fallback: if `ai_explanation` is null or `ai_explanation_status = 'failed'`, surface `risk_note` instead. No empty states.

### Prompt Strategy

- Pass structured coaching object as JSON in the prompt — not prose context.
- Use `consecutive_miss_count` to surface patterns: "you've walked into the same threat three times in this game."
- Use `game_phase` and `complexity_score` to modulate tone: blunder in a simple endgame ≠ blunder in a complex middlegame.
- Keep temperature low (≤ 0.3).
- Keep explanation short and concrete — one coaching insight, not a lecture.
- Include one practical "before-you-move" cue where relevant.
- Require engine ranking compliance — AI must not imply a lower-ranked move is better.

### Frontend

- Render `ai_explanation` in the placeholder slot in `MoveDetailPanel` (`{/* AI coaching explanation will render here — Phase 5 */}`).
- Show `risk_note` (deterministic fallback) immediately; replace inline when `ai_explanation` loads.
- Add regenerate button (manual only — no auto-retry loops, no polling).
- No loading spinners that block the panel — fallback text is always visible.

### Tests

- Contract tests for narration input/output payload shape.
- Integration test: same coaching data + same model produces cached result on second request.
- Integration test: `ai_explanation_status = 'failed'` surfaces `risk_note` fallback.
- Guardrail test: AI output does not reorder or contradict engine ranking.

### Done Definition

- AI explanation enhances the deterministic coaching without replacing it.
- If AI is disabled or fails, users still have complete deterministic coaching — no degraded state.
- `consecutive_miss_count` patterns are surfaced in AI prose where present.

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

