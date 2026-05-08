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

---

## Core User Story

> *As a chess player, I can paste a PGN, wait a minute, and see the three most important mistakes from my game explained in plain English, with the best move shown side by side.*

## MVP Definition of Done

- User can paste a PGN and receive a fully analysed game within 60 seconds
- Three key moments identified with plain-English explanations
- Each mistake has a tag the user can override
- Club notes and coach agreement toggle work
- Trends page shows accuracy and mistake frequency
- Dashboard provides a useful at-a-glance summary
- App is deployed at a public URL
- README explains the architecture and how to run it locally
