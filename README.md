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

This repository now includes active implementation in both `apps/api` (Laravel) and `apps/web` (Next.js), alongside the planning docs. Core foundations in place include game import, schema/relationship constraints, and parser/import test coverage; [09-build-roadmap.md](./09-build-roadmap.md) remains the high-level build order.

## Local structure

```text
apps/
  api/        # Laravel API, queues, Stockfish integration for MVP
  web/        # Next.js frontend
  engine/     # Future dedicated Stockfish worker
infra/
  docker/     # Dockerfiles for local services
  postgres/   # Optional PostgreSQL init scripts
```

## Local containers

The local setup is Herd-first for Laravel and Docker-backed for supporting services. The starter Compose setup uses unique names and ports so it can run alongside other local projects:

| Service | Container | Local URL / port |
|---------|-----------|------------------|
| Web | `chess-coach-web` | `http://web.calvertchess.test` (preferred), or `http://localhost:3000` with local npm / `http://localhost:3001` with Docker |
| API | `chess-coach-api` | `http://api.calvertchess.test` |
| PostgreSQL | `chess-coach-db` | `localhost:5433` |
| Redis | `chess-coach-redis` | `localhost:6380` |

The intended analysis flow is:

```text
web -> api -> queue/job -> engine
```

The web app should never call Stockfish directly. Laravel owns game import, persistence, and queueing. The optional `engine` service is behind the `engine` Compose profile; it currently proves Stockfish can run, then idles until queue integration is added.

Link the Laravel API with Herd:

```sh
cd apps/api
herd link api.calvertchess
```

Run the normal Docker stack for frontend, PostgreSQL, and Redis:

```sh
docker compose up
```

Run with the future engine worker:

```sh
docker compose --profile engine up
```

Run the API container instead of Herd, if needed:

```sh
docker compose --profile docker-api up
```

For the API `.test` domain, point this hostname at your local machine through Herd or your hosts file:

```text
127.0.0.1 web.calvertchess.test
127.0.0.1 api.calvertchess.test
```

If using a local PostgreSQL server managed through pgAdmin instead of the Docker `db` service, Laravel expects:

```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=calvert_chess_coach_journal
DB_USERNAME=postgres
DB_PASSWORD=<your local Postgres password>
```

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
| [apps/api/tests/Tests.md](./apps/api/tests/Tests.md) | API test suite behavior reference |
| [ADMIN-GUIDE.md](./ADMIN-GUIDE.md) | Operator commands (sync, analysis, queues) |
| [AGENTS.md](./AGENTS.md) | Guidance for contributors and AI agents |

## Testing

### API tests (Laravel)

Run all API tests:

```sh
cd apps/api
composer test
```

Alternative direct command:

```sh
cd apps/api
php artisan test
```

### Web tests (Next.js)

Run web test suite:

```sh
cd apps/web
npm test
```

## Contributing

Read [AGENTS.md](./AGENTS.md) and the numbered docs before proposing architectural changes. Prefer small, documented changes that stay aligned with MVP scope.

## License

*To be determined.*
