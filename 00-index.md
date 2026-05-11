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
- [x] **Key moments end-to-end:** `AnalyseGameJob` now selects top non-adjacent inaccuracy/mistake/blunder plies (cap 3, ranked, with phase + tag + `explanation_status = not_requested`); persisted to `key_moments`; surfaced via `GameController` payload and rendered in `KeyMomentsPanel` on `/g/{share_code}`
- [x] **Staged sync / analyse pipeline (commit 748bc51):**
  - `ImportExternalGameJob` no longer auto-dispatches `AnalyseGameJob` — every imported game starts as `analysis_status = pending`
  - New `QueueRecentAnalysisJob` runs after `SyncChessComAccountJob` and dispatches `AnalyseGameJob` only for the most-recent N pending games per account (default `5`, configurable via `CHESS_AUTO_ANALYSE_ON_SYNC`)
  - New `POST /api/v1/games/{id}/analyse` endpoint (ownership-gated, dispatches `AnalyseGameJob`); BFF route at `/api/games/{id}/analyse`
  - Games list shows per-row analysis state with optimistic "Analyse this game" action for `pending` / `failed`
  - Manual PGN repositioned in copy as the side-door ("Or import a PGN manually (for over-the-board games)")
  - Connected-account `store` returns 409 on cross-user uniqueness collision

**Not done yet (MVP gaps)**

- [ ] `analysis_status` enum migration to `pending` / `queued` / `analysing` / `analysed` / `failed` (current code still uses `pending` / `running` / `complete` / `failed`)
- [ ] `analysis_requested_at` column
- [ ] Game detail page rendering for `pending` games (currently assumes analysis exists; needs locked coaching panels + retry on `failed`)
- [ ] Manual PGN import opt-in analyse toggle (currently still auto-dispatches `AnalyseGameJob` on submit)
- [ ] LLM plain-English explanations on key moments; caching
- [ ] Heuristic mistake tags + user override in product UI
- [ ] Dedicated trends `/patterns` or dashboard as in [08-ui-structure.md](./08-ui-structure.md) (profile has **aggregate** trends only)
- [ ] Club notes / coach agreement in UI
- [ ] Production deploy + public demo URL as a milestone

---

## What We Should Build Next

Current priority order (short version):

1. **Recruiter-ready no-auth demo path** — homepage "View sample analysis" CTA, `/demo` with seeded analysed games, visible coaching/trend outputs, link to engineering/case-study surface
2. **Finish the staged-pipeline UX** — game detail page must render for `pending` games (board + metadata + "Analyse this game" CTA, coaching panels locked, retry on `failed`); migrate `analysis_status` enum to `pending` / `queued` / `analysing` / `analysed` / `failed` and add `analysis_requested_at`; manual PGN import opt-in analyse toggle
3. **Key moments + LLM explanations** in `/g/{share_code}` (key-moment selection + persistence + UI is now done; explanations are next)
4. **Heuristic mistake tags** (small conservative subset first)
5. **Notes + coaching/journal loop**
6. **Dedicated dashboard/trends pages**, then Lichess and polish

See details in [09-build-roadmap.md](./09-build-roadmap.md) (Phase 3.5), [05-analysis-pipeline.md](./05-analysis-pipeline.md) (staged pipeline), [03-architecture.md](./03-architecture.md), and [11-profile-plan.md](./11-profile-plan.md).

---

## Core User Story

> *As a chess player, I can paste a PGN, wait a minute, and see the three most important mistakes from my game explained in plain English, with the best move shown side by side.*

## MVP Definition of Done

- [x] User can paste a PGN and receive a fully analysed game within ~60 seconds (engine path; explanations still outstanding)
- [ ] Three key moments identified with plain-English explanations
- [ ] Each mistake has a tag the user can override
- [ ] Club notes and coach agreement toggle work
- [ ] Trends page shows accuracy and mistake frequency *(profile trends for linked Chess.com accounts are partially there)*
- [ ] Dashboard provides a useful at-a-glance summary
- [ ] App is deployed at a public URL
- [x] README explains the architecture and how to run it locally
