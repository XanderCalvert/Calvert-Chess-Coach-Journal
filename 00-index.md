# Chess Coach Journal — Documentation Index

Full-stack web application for post-game chess analysis, AI explanations, and improvement tracking.

Portfolio framing for these docs:

> Built as a full-stack case study in asynchronous analysis systems, deterministic coaching signals, and product-grade UX for explainable chess improvement.

> **Stack:** Next.js · Laravel · PostgreSQL · Stockfish · OpenAI API

---

## Documents

| File | Contents |
|------|----------|
| [01-overview.md](./01-overview.md) | Product overview, value proposition, target user, what the app is not |
| [02-scope.md](./02-scope.md) | MVP features, Phase 1, Phase 2, stretch ideas, what to avoid |
| [03-architecture.md](./03-architecture.md) | Tech stack, deployment options, key decisions, risks |
| [04-data-model.md](./04-data-model.md) | Full schema — all tables, fields, types, and relationships |
| [05-analysis-pipeline.md](./05-analysis-pipeline.md) | 7-step async pipeline: PGN parse → Stockfish → key moments → tagging → LLM → summary → trends |
| [06-explanation-system.md](./06-explanation-system.md) | LLM prompt design, hallucination reduction, depth variants, validation checklist |
| [07-mistake-taxonomy.md](./07-mistake-taxonomy.md) | All 12 mistake tags with slugs, descriptions, and heuristic detection rules |
| [08-ui-structure.md](./08-ui-structure.md) | All screens, components, empty states, and user flows |
| [09-build-roadmap.md](./09-build-roadmap.md) | MVP milestones, Phase 1 milestones, recommended build order, practical advice |
| [10-pages-and-sharing-plan.md](./10-pages-and-sharing-plan.md) | Route-by-route page plan, public sharing URL design, and implementation direction |
| [11-profile-plan.md](./11-profile-plan.md) | Profiles, Chess.com sync, trends, and follow-on phases |
| [12-live-move-coach-sprints.md](./12-live-move-coach-sprints.md) | Engine-first sprint plan for live what-if move coaching with AI enhancement path |
| [13-pre-ai-checklist.md](./13-pre-ai-checklist.md) | Gaps and nice-to-haves before the LLM / narration layer |
| [ADMIN-GUIDE.md](./ADMIN-GUIDE.md) | Operator commands (bulk sync, analysis, queues) |

---

## Implementation status (May 2026)

Use [09-build-roadmap.md](./09-build-roadmap.md) for detailed checkboxes. Summary:

**Done (working in repo)**

- [x] Next.js + Laravel + PostgreSQL + Compose/Herd-style local dev
- [x] PGN import (web + API), `chess.js` parsing, game + moves persistence
- [x] Stockfish via queued `AnalyseGameJob`; per-move CP loss; blunder / mistake / inaccuracy counts
- [x] Interactive analysis UI: board, move list, keyboard nav, move detail, `?ply=N` deep links
- [x] Public game URLs: `/g/{share_code}` (8-char codes); BFF routes under `/api/...`
- [x] Games list and import flow (`/games`, `/import`); games list now includes per-row analysis status badges, account filter, and inline "Analyse" CTA for `pending` / `failed`
- [x] **Chess.com–linked profiles:** `connected_accounts`, `/u/{username}`, sync (recent window in UI), stats + sparklines, game-type filter, deduped imports
- [x] **CLI:** `chess:sync-connected-account` for full-archive or recent-window sync ([ADMIN-GUIDE.md](./ADMIN-GUIDE.md))
- [x] **Auth + onboarding:** Sanctum personal access tokens + `chess_token` httpOnly cookie; `/register`, `/login`, `/onboarding`, `/settings`; onboarding gate (must connect a chess account before dashboard); ownership-scoped `Game` / `ConnectedAccount` queries; `ClaimDevData` artisan command
- [x] **Key moments end-to-end:** `AnalyseGameJob` selects top non-adjacent inaccuracy/mistake/blunder plies (cap 3, ranked, with phase + heuristic `mistake_tag_id` + `explanation_status = not_requested`); persisted to `key_moments`; surfaced via `GameController` and rendered in `KeyMomentsPanel` (copy uses deterministic `risk_note` when `explanation_text` is null)
- [x] **Staged sync / analyse pipeline (commit 748bc51 + follow-ups):**
  - `ImportExternalGameJob` does not auto-dispatch `AnalyseGameJob` for every imported game — new imports start `pending`
  - `QueueRecentAnalysisJob` after sync dispatches `AnalyseGameJob` only for the most-recent N pending games per account (default `5`, `CHESS_AUTO_ANALYSE_ON_SYNC`)
  - `POST /api/v1/games/{id}/analyse` (ownership-gated); BFF `/api/games/{id}/analyse`; `analysis_requested_at` set when a run is requested
  - `games.analysis_status` values: `pending` / `queued` / `analysing` / `analysed` / `failed` (`App\Enums\AnalysisStatus` + DB migration)
  - `AnalyseGameJob` sets `analysing` at start and `analysed` or `failed` on completion
  - Games list shows per-row analysis state with optimistic "Analyse this game" for `pending` / `failed`; **polling** while games leave `queued` / `analysing`
  - Authenticated **`/games/{id}/analysis`**: board + move replay for imported games before analysis; coaching panels locked until `analysed`; banners + **retry** for `failed`
  - Manual PGN repositioned in copy as the side-door ("Or import a PGN manually (for over-the-board games)")
  - Connected-account `store` returns 409 on cross-user uniqueness collision
- [x] **Weakness profile (aggregate coaching):** `weakness_profiles`, `ComputeWeaknessProfileJob` (queued after each analysed linked-account game), public `GET /api/v1/connected-accounts/by-username/{platform}/{username}/weakness-profile`, surfaced on `/u/{username}`; operator `chess:compute-weakness-profiles`
- [x] **Normalized move events for aggregation:** `move_tactical_events` and `move_threat_events` populated in `AnalyseGameJob`; `chess:backfill-move-events` for legacy rows
- [x] **Bulk re-analysis:** `POST /api/v1/games/reanalyse-completed` (force re-run for already-`analysed` games) + Settings UI + BFF; respects monthly analysis quota for non-premium users
- [x] **Free-tier analysis quota:** `subscription_tier`, `analysis_quota_used`, `quota_period_start` on users; enforced on on-demand analyse, re-analyse-completed, and auto-queue paths (see `AnalysisQuotaTest`)

**Built in repo but not called out in older roadmap bullets**

These landed alongside profile/coaching work and extend the deterministic layer; they are not all spelled out in the original milestone tables.

- Weakness profile JSON (phase / opening / motif summaries) as a **stored** aggregate per connected account revision
- Monthly **analysis quota** for free accounts (premium bypass in code paths)
- **Heuristic mistake tags on key moments** (small slug set: missed tactic, hanging piece, overlooked threat, positional fall-back) — **no** user override UI yet
- Feature tests for staged pipeline, analyse endpoint, re-analyse-completed, weakness job/command, quota (`apps/api/tests/Feature/`)

**Not done yet (MVP gaps)**

- [ ] Manual PGN import **opt-in** analyse toggle (`POST /api/v1/games` still auto-dispatches `AnalyseGameJob` after create)
- [ ] LLM plain-English **`explanation_text`** on key moments; caching and request UX
- [ ] User **override** of mistake tags in the product UI
- [ ] Dedicated trends `/patterns` or dashboard as in [08-ui-structure.md](./08-ui-structure.md) (profile still has aggregate trends + weakness card, not full dashboard routes)
- [ ] Club notes / coach agreement in UI
- [ ] Production deploy + public demo URL as a milestone

---

## What We Should Build Next

Current priority order (short version):

1. **Recruiter-ready no-auth demo path** — homepage "View sample analysis" CTA, `/demo` with seeded analysed games, visible coaching/trend outputs, link to engineering/case-study surface
2. **Finish the staged-pipeline product gaps** — manual PGN import **opt-in analyse** only (`POST /api/v1/games` should not always queue analysis); any polish on public `/g/{share_code}` for not-yet-analysed shares if product wants parity with authenticated game page
3. **Key moments + LLM explanations** in `/g/{share_code}` (selection + persistence + deterministic UI done; LLM `explanation_text` + caching next)
4. **Heuristic mistake tags** (small conservative subset first)
5. **Notes + coaching/journal loop**
6. **Dedicated dashboard/trends pages**, then Lichess and polish

See details in [09-build-roadmap.md](./09-build-roadmap.md) (Phase 3.5), [05-analysis-pipeline.md](./05-analysis-pipeline.md) (staged pipeline), [03-architecture.md](./03-architecture.md), and [11-profile-plan.md](./11-profile-plan.md).

---

## Core User Story

> *As a chess player, I can paste a PGN, wait a minute, and see the three most important mistakes from my game explained in plain English, with the best move shown side by side.*

## MVP Definition of Done

- [x] User can paste a PGN and receive a fully analysed game within ~60 seconds (engine path; explanations still outstanding)
- [x] Three key moments identified with engine-backed context (plain-English LLM layer still outstanding)
- [ ] Each mistake has a tag the user can override *(heuristic tag on key moments exists server-side; override UI not built)*
- [ ] Club notes and coach agreement toggle work
- [ ] Trends page shows accuracy and mistake frequency *(profile trends for linked Chess.com accounts are partially there)*
- [ ] Dashboard provides a useful at-a-glance summary
- [ ] App is deployed at a public URL
- [x] README explains the architecture and how to run it locally
