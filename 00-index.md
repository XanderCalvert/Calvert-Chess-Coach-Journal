# Chess Coach Journal — Documentation Index

Full-stack web application for post-game chess analysis, AI explanations, and improvement tracking.

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
- [x] Games list and import flow (`/games`, `/import`)
- [x] **Chess.com–linked profiles:** `connected_accounts`, `/u/{username}`, sync (recent window in UI), stats + sparklines, game-type filter, deduped imports
- [x] **CLI:** `chess:sync-connected-account` for full-archive or recent-window sync ([ADMIN-GUIDE.md](./ADMIN-GUIDE.md))

**Not done yet (MVP gaps)**

- [ ] Auth (register/login, user-owned games)
- [ ] LLM plain-English explanations on key moments; caching
- [ ] Heuristic mistake tags + user override in product UI
- [ ] Dedicated trends `/patterns` or dashboard as in [08-ui-structure.md](./08-ui-structure.md) (profile has **aggregate** trends only)
- [ ] Club notes / coach agreement in UI
- [ ] Production deploy + public demo URL as a milestone

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
