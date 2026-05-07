# Chess Coach Journal

Full-stack web app for **post-game chess analysis**: run engine-backed review, read **plain-English explanations** of your biggest mistakes, and **track recurring weaknesses** over time.

**Target user:** Amateur players (about 600–1400) who want structured improvement, not just engine numbers.

**Core story:** Paste a PGN, wait about a minute, and see the **three most important moments** explained in plain language with the best move alongside.

## What this is not

Not a Chess.com/Lichess-style platform: no live play, matchmaking, or social layer. Not an opening encyclopedia or generic training site. See [01-overview.md](./01-overview.md) for scope and positioning.

## Planned stack

| Layer | Technology |
|--------|------------|
| Frontend | Next.js (App Router) |
| API | Laravel |
| Database | PostgreSQL |
| Engine | Stockfish (analysis worker) |
| Explanations | OpenAI API (explanation worker) |

## Repository status

This repository currently holds **planning and design documentation**. Application code, local setup, and run commands will be added as implementation proceeds; [09-build-roadmap.md](./09-build-roadmap.md) describes the intended build order.

## Documentation

| Doc | Topic |
|-----|--------|
| [00-index.md](./00-index.md) | Index of all documents |
| [01-overview.md](./01-overview.md) | Product overview and value proposition |
| [02-scope.md](./02-scope.md) | MVP, phases, and boundaries |
| [03-architecture.md](./03-architecture.md) | System design and deployment |
| [04-data-model.md](./04-data-model.md) | Database schema |
| [05-analysis-pipeline.md](./05-analysis-pipeline.md) | Analysis pipeline |
| [06-explanation-system.md](./06-explanation-system.md) | LLM explanations and prompts |
| [07-mistake-taxonomy.md](./07-mistake-taxonomy.md) | Mistake tags and heuristics |
| [08-ui-structure.md](./08-ui-structure.md) | Screens and flows |
| [09-build-roadmap.md](./09-build-roadmap.md) | Milestones and build order |
| [AGENTS.md](./AGENTS.md) | Guidance for contributors and AI agents |

## Contributing

Read [AGENTS.md](./AGENTS.md) and the numbered docs before proposing architectural changes. Prefer small, documented changes that stay aligned with MVP scope.

## License

*To be determined.*
