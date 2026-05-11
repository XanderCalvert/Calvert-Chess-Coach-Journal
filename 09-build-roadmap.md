# Chess Coach Journal — Build Roadmap

Tick `- [ ]` → `- [x]` as you complete items. In GitHub or many editors, checkboxes are clickable.

> **Repo snapshot (May 2026):** Full **PGN → analyse → review** loop works: import (UI + API), Stockfish via queues, CP loss + classifications, interactive `/g/{share_code}` with `?ply=N`, games list. Deterministic coaching metadata at move level (`themes`, tactical flags, threat awareness, risk notes) **plus** normalized `move_tactical_events` / `move_threat_events` for aggregation. **Chess.com profiles:** `connected_accounts`, `/u/{username}`, web sync (20-game window), query-time stats + sparklines, game-type filter, dedup; **weakness profiles** (`weakness_profiles`, `ComputeWeaknessProfileJob`, public weakness API + profile UI). **Operators:** `chess:sync-connected-account`, `chess:compute-weakness-profiles`, `chess:backfill-move-events` ([ADMIN-GUIDE.md](./ADMIN-GUIDE.md) — extend as commands stabilize). **Auth + onboarding:** Sanctum, `chess_token` cookie, onboarding gate, `/settings` (includes **Re-analyse completed games** → `POST /api/v1/games/reanalyse-completed`). **Key moments:** selection + persistence + ranked UI; key-moment cards show deterministic copy (`risk_note`) until LLM `explanation_text` exists. **Staged pipeline:** `analysis_status` uses `pending` / `queued` / `analysing` / `analysed` / `failed` + `analysis_requested_at`; on-demand analyse + list/detail **polling**; authenticated `/games/{id}/analysis` supports `pending` / `failed` UX. **Free tier:** monthly analysis quota on user. Still outstanding: manual PGN opt-in analyse only, LLM explanations, tag override UI, dedicated `/patterns` dashboard routes, club notes, production deploy.

---

## Recommended Next Build Sequence (Now)

The highest-value order from current state:

1. **Recruiter no-auth demo flow and case-study surface**
   - Homepage CTA: **"View sample analysis"**.
   - Add `/demo` route with 2-3 pre-analysed seeded games and representative coaching/trend outputs.
   - Add planned engineering route (`/engineering` or `/case-study`) summarising architecture, trade-offs, pipeline, screenshots/diagrams, and roadmap decisions.
   - Preserve no-auth, zero-friction exploration for portfolio evaluation.
2. **Split sync from analysis (staged pipeline)** — **done** except manual-PGN opt-in only
   - Sync should import metadata only (PGN, moves, result, opening, date, time control, ratings, opponent).
   - Stop auto-queuing `AnalyseGameJob` for every imported/synced game.
   - Auto-analyse only the **5 most-recent newly imported** games per sync run.
   - `analysis_status` enum is `pending` / `queued` / `analysing` / `analysed` / `failed` + `analysis_requested_at`.
   - `POST /api/v1/games/{id}/analyse` for on-demand analysis; quota for free tier.
   - **Remaining:** `POST /api/v1/games` (manual PGN) should default pending and only analyse when client opts in.
3. **Game list + game page work for `pending` games** — **done** on authenticated `/games/{id}/analysis` + list polling
   - Games list shows analysis state per row + inline "Analyse this game" for `pending` / `failed`.
   - Game detail page renders board replay + metadata before analysis; coaching panels lock until `analysed`.
   - Reframe `/import` as "Import PGN manually" — secondary path; primary entry remains the games list.
4. **Key moments + plain-English explanations end-to-end**
   - Persist/select top key moments reliably per analysed game.
   - Generate cached low-temperature explanations from deterministic board context.
   - Render key-moment cards in `/g/{share_code}` with jump-to-position support.
5. **Heuristic mistake tags (MVP subset)**
   - Start with conservative rules for 3–5 tags. *(move-level deterministic themes are in place)*
   - Store and display tags on key moments; user override can follow.
6. **Journal UX basics**
   - Manual notes + coach agreement path.
   - Lightweight summary/recommendation surfaces.
7. **Then scale breadth**
   - Dedicated trends/dashboard routes, Lichess import, polish/deploy.
   - Progressive coaching unlocks tied to count of analysed games (e.g. "Analyse 5 games to unlock your coaching report").

This keeps the core promise strong while aligning the engine pipeline with the long-term self-coaching, scalable, premium-ready direction.

Recruiter-facing objective for this phase:

```text
Homepage → View Demo → Sample analysis → Coaching/trends signal → Engineering case study
```

Prioritise visible product/system depth over growth/onboarding mechanics until this flow is strong.

---

## Single-Source Master Plan (Consolidated)

Use this section as the canonical sequence. It consolidates planning from:
- `10-pages-and-sharing-plan.md` (pipeline + pages)
- `11-profile-plan.md` (profile/trends phases)
- `12-live-move-coach-sprints.md` (deterministic coaching + AI narration plan)
- `04-data-model.md` (schema/status constraints)

### Phase 0 — Foundation and Core Loop (Done)

- [x] PGN import and parse
- [x] Queue-based Stockfish analysis
- [x] Move-level evaluations + classifications
- [x] Game review UI (`/g/{share_code}` + `?ply=N`)
- [x] Games list + API wiring

### Phase 1 — Share + Profile Baseline (Done)

- [x] Public share URLs and deep-linking
- [x] Chess.com connected accounts and sync jobs (web + CLI)
- [x] Profile page (`/u/{username}`) with analysed games list
- [x] Query-time profile aggregates + sparklines + game-type filter

### Phase 2 — Deterministic Coaching Layer (Mostly Done)

- [x] Deterministic teaching metadata on moves:
  - [x] `themes`
  - [x] `tactical_flags`
  - [x] `threat_awareness`
  - [x] `risk_note`
- [x] Move-detail coaching surfaces in UI (guided/full modes)
- [x] Backfill command for coaching columns
- [x] Tests for extraction + payload shape + API exposure
- [x] Key-moment-level tagging flow (separate from move-level coaching) completed end-to-end

### Phase 3 — Key Moments + Explanation Loop (Next Priority)

- [x] Persist/select top key moments per analysed game reliably
- [x] Show key-moment cards in `/g/{share_code}` with played-vs-best context
- [x] Key-moment view renders **deterministic** coaching copy (`risk_note` / structured fields); **`explanation_text` LLM layer still open**
- [x] Ensure jump-to-position flow is smooth from each key moment

### Phase 3.5 — Staged Sync / Analyse / Coach Pipeline (Mostly done)

The product is moving from "sync → analyse everything" to a staged pipeline of `Imported` → `Analysed` → `Coached` (see [03-architecture.md](./03-architecture.md), [05-analysis-pipeline.md](./05-analysis-pipeline.md)). This is the gating change for scalability, on-demand analysis UX, and future free/premium tiering. Commit 748bc51 landed the backend split + games-list UX; status enum + polling + game-detail pending UX + tests landed in follow-up work.

Backend
- [x] Migrate `games.analysis_status` to `pending` / `queued` / `analysing` / `analysed` / `failed`; add `analysis_requested_at` (`App\Enums\AnalysisStatus` + migration `2026_05_11_000001_...`)
- [x] Update `ImportExternalGameJob` so it **does not** auto-dispatch `AnalyseGameJob` for every imported game (commit 748bc51)
- [x] Update `SyncChessComAccountJob` to select the recent auto-analyse subset and dispatch `AnalyseGameJob` only for those — implemented as `QueueRecentAnalysisJob` (60s delay, default 5 most-recent pending per account, env `CHESS_AUTO_ANALYSE_ON_SYNC`)
- [ ] Manual PGN import (`POST /api/v1/games`) defaults to `analysis_status = pending`; an opt-in flag triggers immediate analysis (currently still auto-dispatches)
- [x] New endpoint `POST /api/v1/games/{id}/analyse` — ownership-gated; dispatches `AnalyseGameJob` (commit 748bc51); BFF route at `/api/games/{id}/analyse`
- [x] `AnalyseGameJob` writes `analysing` on start and `analysed` (or `failed` in `failed()`) on completion
- [x] `POST /api/v1/games/reanalyse-completed` — bulk force re-analysis for `analysed` games (quota-aware); BFF from Settings
- [x] Monthly **analysis quota** for non-premium users (increment on successful queue paths)

Frontend
- [x] Games list: per-row analysis state badge + inline "Analyse this game" button for `pending` / `failed` with optimistic update (commit 748bc51)
- [x] Games list: account filter pills; "Your accounts" header section with per-account Sync controls + last-synced + status (commit 748bc51)
- [x] Authenticated **`/games/{id}/analysis`**: `pending` shows board + move list + Analyse CTA; coaching panels locked until `analysed`; `failed` shows retry
- [x] Polling / refresh while a game is `pending` / `queued` / `analysing` (game detail page + games list rows)
- [x] Manual PGN repositioned as side-door in copy ("Or import a PGN manually (for over-the-board games)") — full CTA/page-title rename on `/import` still to land

Tests / observability
- [x] Sync / queue integration: `SyncQueuesAnalysisSubsetTest` exercises `QueueRecentAnalysisJob` fan-out vs limit
- [x] On-demand analyse endpoint: `AnalyseEndpointTest` (ownership, status transitions, `analysis_requested_at`, debug `force`)
- [x] `GameDetailPendingTest` — API returns coherent payloads for `pending` / `queued` / `analysing`
- [x] Re-analyse completed: `ReanalyseCompletedGamesEndpointTest`
- [x] Quota + weakness dispatch coverage: `AnalysisQuotaTest`, `AnalyseGameJobDispatchesWeaknessTest`, `ComputeWeaknessProfileJobTest`, etc. (see [apps/api/tests/Tests.md](./apps/api/tests/Tests.md))

### Phase 4 — AI Narration Layer (After deterministic loop is complete)

See **[13-pre-ai-checklist.md](./13-pre-ai-checklist.md)** for recommended prep (manual PGN parity, model-input contract, explanation status UX, cost controls, demo/deploy).

- [ ] Add narration service that consumes structured coaching columns (not raw engine dumps alone)
- [ ] Persist `ai_explanation` with status/model fields and stable cache keys
- [ ] Keep deterministic fallback (`risk_note`) visible when AI is unavailable/fails
- [ ] Add manual regenerate control (no auto-retry loops)
- [ ] Add guardrail tests (cache behavior, fallback behavior, no ranking contradictions)

### Phase 5 — Ownership/Auth + User Persistence

Connected accounts are the user's **chess identity**, not optional integrations. Onboarding should require linking at least one account before reaching the dashboard.

- [x] Add auth/session and ownership model — Sanctum tokens, `chess_token` httpOnly cookie, login/register/logout/me endpoints
- [x] Onboarding flow: register → connect Chess.com/Lichess account → auto-import → dashboard (account link is a required step, not a settings item)
- [x] `/settings` page: profile display + "Your chess accounts" add/remove with last-account → onboarding redirect
- [x] Move from dev-user assumptions to user-scoped queries — `sync()` ownership-gated, `destroy()` ownership-gated, `has_connected_accounts` on `/auth/me`
- [x] All coaching language, trends, and insights scoped to the owner's linked identities
- [x] Ownership verification (bio-code method) explicitly deferred — honour system for MVP *(still deferred)*
- [ ] Support profile/account claim and owned-game history (games imported before auth linked to a user)
- [ ] Persist notes and coach agreement under real users

### Phase 6 — Dedicated Trends and Pattern Views

- [ ] Promote profile aggregates into dedicated `/patterns`/dashboard-style routes *(partial today: `/u/{username}` stats + sparklines + **weakness profile** card backed by stored `weakness_profiles` rows)*
- [ ] Add recurring-mistake cards (most common category, phase, opening/structure context) *(weakness profile JSON already surfaces motif / phase / opening signals — product cards + copy polish still open)*
- [x] Introduce stored trend summaries if query-time aggregation becomes insufficient **(weakness_profiles per connected account)**
- [ ] Surface clear next study action from trend outputs
- [ ] Progressive coaching unlocks tied to count of analysed games (e.g. "Analyse 5 games to unlock your coaching report"; "Analyse 20 to unlock recurring mistake trends")
- [ ] Lay the groundwork for free/premium split: free tier = sync + browse + limited analysis quota + basic coaching; premium = unlimited analysis + advanced trends + AI explanations (see [02-scope.md](./02-scope.md))

### Phase 7 — UX and Surface Completion

- [ ] `/games/[id]/review` training mode page
- [ ] `/profile` settings surfaces (explanation depth, linked account management)
- [ ] Homepage/example analysis polish with "View sample analysis" CTA
- [ ] `/demo` route (no auth) with 2-3 pre-analysed seeded games and optional guided walkthrough mode
- [ ] `/engineering` (or `/case-study`/`/about/build`) page with architecture, trade-offs, diagrams, and build evolution notes
- [ ] Improve responsive behavior and empty/error states

### Phase 8 — Import Breadth and Launch

- [ ] Lichess import after ownership/auth is stable
- [ ] Production deploy + runbook + architecture docs
- [ ] Public demo environment with safe sample data

---

## Canonical Pre-AI Player Analysis Pipeline

This is the deterministic pipeline that should be complete before depending on AI prose. It is **staged** — sync, analyse, and coach are independent steps:

1. Sync / import games (metadata + moves only — cheap, fast, every game)
2. Trigger Stockfish analysis per game **selectively**: auto for the recent subset (MVP: 5 most-recent newly imported), on demand otherwise
3. Run Stockfish analysis per move
4. Compute cp loss + move classification
5. Generate deterministic coaching columns (`themes`, tactical flags, threat response, risk note)
6. Select and tag key moments
7. Aggregate by player (time windows, phase, opening/theme, recurring categories) — coaching depth scales with the count of analysed games
8. Expose structured outputs to UI and (later) AI narration

AI should narrate this structured output, not replace it.

---

## Legacy Build Notes (Condensed)

These legacy checkpoints are now represented in the consolidated phase plan above:

- [x] Core technical proof complete: scaffold, PGN parsing, Stockfish integration, full-game evaluation, move classifications.
- [x] Baseline game analysis UI complete: board, move list/navigation, share links, status states, stats legend.
- [x] Auth/ownership complete (Phase 5).
- [x] Key-moment cards on share + private analysis views (deterministic copy; LLM prose still open).
- [ ] Still open: LLM key-moment explanations, dedicated trends/dashboard routes (beyond profile + weakness card), deployment polish.

If you need historical wording, reference git history for this file; avoid maintaining duplicate active checklists here.

---

## MVP Milestones

| # | Milestone | Deliverable |
|---|-----------|-------------|
| M1 | Foundation | Project scaffold, auth, database schema, PGN parse to Moves table |
| M2 | Engine | Stockfish worker, centipawn evaluation, classification, Key Moments selection |
| M3 | Heuristic Tags | Rule-based mistake tagging for 5 MVP tags |
| M4 | Explanations | LLM API integration, prompt template, explanation stored and displayed |
| M5 | Game UI | Analysis page with board, key moment cards, played vs best move view |
| M6 | Summary + Notes | Game summary generation, manual notes, coach agreement toggle |
| M7 | Trends MVP | Trend summary computed, simple trends page with table and chart |
| M8 | Dashboard | Dashboard with stat cards, recent games list, study recommendation |
| M9 | Polish | Error states, empty states, loading indicators, responsive fixes |
| M10 | Launch | Deploy to Fly.io / Railway, write README, document architecture |

**Progress (tick as you go):**

- [x] **M1 — Foundation:** Project scaffold, database schema, PGN parse to Moves table; Sanctum auth (register, login, logout, me, onboarding gate, settings)
- [x] **M2 — Engine:** Stockfish worker, centipawn evaluation, classification, move-level key moments
- [x] **M3 — Heuristic Tags (partial):** Rule-based deterministic move themes/tactical tags are live; **key-moment rows carry heuristic `mistake_tag_id`**; user override still open
- [ ] **M4 — Explanations:** LLM API integration, prompt template, explanation stored and displayed
- [x] **M5 — Game UI (partial):** Board + move list + key-moment cards + played vs best context; **LLM explanation text still outstanding**
- [ ] **M6 — Summary + Notes:** Game summary generation, manual notes, coach agreement toggle
- [ ] **M7 — Trends MVP:** Dedicated trends page + stored summaries still open; **partial:** `/u/{username}` profile stats + sparklines for analysed games on a connected Chess.com account
- [ ] **M8 — Dashboard:** Dashboard with stat cards, recent games list, study recommendation
- [ ] **M9 — Polish:** Error states, empty states, loading indicators, responsive fixes
- [ ] **M10 — Launch:** Deploy to Fly.io / Railway, write README, document architecture

---

## Phase 1 Milestones

| # | Milestone | Deliverable |
|---|-----------|-------------|
| P1-M1 | Import | Chess.com and Lichess API import with deduplication |
| P1-M2 | Recurring Mistakes | Detect recurring mistake tags across 20+ games |
| P1-M3 | Opening Awareness | ECO tracking, accuracy by opening |
| P1-M4 | Dashboard v2 | Trend comparison (last 5 vs previous 5 games) |
| P1-M5 | Explanation Depth | Rating preference, improved prompts |
| P1-M6 | Responsive Layout | Tablet and mobile support |

**Progress (tick as you go):**

- [x] **P1-M1 — Import:** Chess.com public archive import with deduplication; UI sync (recent window) + CLI full/history sync; Lichess **not** implemented yet
- [x] **P1-M2 — Recurring Mistakes (partial):** Stored **weakness profile** aggregates motifs / phases / openings across analysed games; dedicated recurring-mistake **dashboard** UX across 20+ games still open
- [ ] **P1-M3 — Opening Awareness:** ECO tracking, accuracy by opening
- [ ] **P1-M4 — Dashboard v2:** Trend comparison (last 5 vs previous 5 games)
- [ ] **P1-M5 — Explanation Depth:** Rating preference, improved prompts
- [ ] **P1-M6 — Responsive Layout:** Tablet and mobile support

---

## Practical Advice

- **Use your own real games from the start.** Do not develop against synthetic test data — chess positions are too varied and explanations will feel wrong.
- **Spend disproportionate time on explanation quality.** A mediocre explanation that gets the idea right is worth ten technically correct but unhelpful engine lines.
- **Resist adding features** until the core loop (import → analyse → explain → review) is smooth and fast.
- **Treat every analysed game as a product test** — did you learn something useful? If not, fix the explanation before adding a new feature.
- **Keep the code public on GitHub from day one.** Commit regularly with meaningful messages — this is part of the portfolio.
