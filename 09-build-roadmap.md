# Chess Coach Journal — Build Roadmap

Tick `- [ ]` → `- [x]` as you complete items. In GitHub or many editors, checkboxes are clickable.

> **Repo snapshot (May 2026):** Full **PGN → analyse → review** loop works: import (UI + API), Stockfish via queues, CP loss + classifications, interactive `/g/{share_code}` with `?ply=N`, games list. Deterministic coaching metadata is now generated and rendered at move level (`themes`, tactical flags, threat awareness, risk notes). **Chess.com profiles:** `connected_accounts`, `/u/{username}`, web sync (20-game window), query-time stats + sparklines, game-type filter, `ImportExternalGameJob` dedup. **Operators:** `chess:sync-connected-account` for full-archive pulls ([ADMIN-GUIDE.md](./ADMIN-GUIDE.md)). **Auth + onboarding:** Sanctum registration/login, `chess_token` cookie, onboarding gate (connect account required before dashboard), `/settings` page (add/remove chess accounts), full user-scoped queries, sync ownership enforced. Still outstanding: LLM-generated explanations, dedicated dashboard/trends pages, club notes, production deploy.

---

## Recommended Next Build Sequence (Now)

The highest-value order from current state:

1. **Key moments + plain-English explanations end-to-end**
   - Persist/select top key moments reliably per analysed game.
   - Generate cached low-temperature explanations from deterministic board context.
   - Render key-moment cards in `/g/{share_code}` with jump-to-position support.
2. **Heuristic mistake tags (MVP subset)**
   - Start with conservative rules for 3–5 tags. *(move-level deterministic themes are in place)*
   - Store and display tags on key moments; user override can follow.
3. **Auth + user-owned persistence**
   - Add login/session and connect imported/manual games to user accounts.
4. **Journal UX basics**
   - Manual notes + coach agreement path.
   - Lightweight summary/recommendation surfaces.
5. **Then scale breadth**
   - Dedicated trends/dashboard routes, Lichess import, polish/deploy.

This keeps the core promise strong before expanding scope.

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
- [x] Render explanation content in key-moment view
- [x] Ensure jump-to-position flow is smooth from each key moment

### Phase 4 — AI Narration Layer (After deterministic loop is complete)

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

- [ ] Promote profile aggregates into dedicated `/patterns`/dashboard-style routes
- [ ] Add recurring-mistake cards (most common category, phase, opening/structure context)
- [ ] Introduce stored trend summaries if query-time aggregation becomes insufficient
- [ ] Surface clear next study action from trend outputs

### Phase 7 — UX and Surface Completion

- [ ] `/games/[id]/review` training mode page
- [ ] `/profile` settings surfaces (explanation depth, linked account management)
- [ ] Homepage/example analysis polish
- [ ] `/about` build page
- [ ] Improve responsive behavior and empty/error states

### Phase 8 — Import Breadth and Launch

- [ ] Lichess import after ownership/auth is stable
- [ ] Production deploy + runbook + architecture docs
- [ ] Public demo environment with safe sample data

---

## Canonical Pre-AI Player Analysis Pipeline

This is the deterministic pipeline that should be complete before depending on AI prose:

1. Import/sync games
2. Parse + persist moves
3. Run Stockfish analysis per move
4. Compute cp loss + move classification
5. Generate deterministic coaching columns (`themes`, tactical flags, threat response, risk note)
6. Select and tag key moments
7. Aggregate by player (time windows, phase, opening/theme, recurring categories)
8. Expose structured outputs to UI and (later) AI narration

AI should narrate this structured output, not replace it.

---

## Legacy Build Notes (Condensed)

These legacy checkpoints are now represented in the consolidated phase plan above:

- [x] Core technical proof complete: scaffold, PGN parsing, Stockfish integration, full-game evaluation, move classifications.
- [x] Baseline game analysis UI complete: board, move list/navigation, share links, status states, stats legend.
- [x] Auth/ownership complete (Phase 5).
- [ ] Still open: key-moment cards + LLM explanations, dedicated trends/dashboard routes, deployment polish.

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
- [x] **M3 — Heuristic Tags (partial):** Rule-based deterministic move themes/tactical tags are live; key-moment-level tagging + manual correction still open
- [ ] **M4 — Explanations:** LLM API integration, prompt template, explanation stored and displayed
- [ ] **M5 — Game UI:** Analysis page with board, key moment cards, played vs best move view *(board + move list done; key moment cards and explanations outstanding)*
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
- [ ] **P1-M2 — Recurring Mistakes:** Detect recurring mistake tags across 20+ games
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
