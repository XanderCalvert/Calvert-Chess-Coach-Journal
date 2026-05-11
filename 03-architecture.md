# Chess Coach Journal — Architecture

**As implemented (May 2026):** Next.js App Router + Laravel API + PostgreSQL; Laravel queues + Stockfish; Next **BFF** routes under `apps/web/app/api/...` proxying **`/api/v1/...`** on Laravel. Auth not wired. Trend **charts** on profile use a small Sparkline component (not necessarily Recharts).

## Stack

### Frontend
- **Next.js** (App Router) — file-based routing and server components
- **react-chessboard** — interactive board UI
- **chess.js** — client-side move validation and PGN rendering
- **TailwindCSS** — utility-first styling
- **Recharts or Chart.js** — trend visualisations
- **Zustand or React Context** — local UI state
- **SWR or React Query** — server state and polling for analysis progress

### Backend API
- **Laravel (PHP)** — REST API, queue management, auth scaffolding (recommended)
- Alternative: Node.js + Express + BullMQ for a JavaScript monorepo
- Key endpoints:
  - `POST /games` — import
  - `GET /games/:id` — analysis
  - `GET /trends`
  - `PATCH /key-moments/:id/notes`
- Auth: JWT or session-based (Laravel Sanctum recommended)

### Database
- **PostgreSQL** (production) — SQLite acceptable for local dev
- Migrations via Laravel Migrations or Knex
- JSON columns for engine lines and trend summary blobs
- Indexes on `game_id`, `user_id`, `created_at`

### Stockfish Worker
- Stockfish binary as a persistent child process on the server
- Communication via stdin/stdout using UCI protocol
- Simple wrapper class manages process lifecycle, sends position commands, parses `bestmove` responses
- MVP: single instance, one game at a time
- Phase 1: process pool or queue with concurrency limit

### AI Explanation Worker
- Separate queue consumer — reads pending key moments, calls LLM API
- **OpenAI GPT-4o-mini** recommended for MVP (cheap, fast, adequate for chess)
- Alternative: local Ollama + Llama 3 for zero API cost
- Retries with exponential backoff on rate limit or timeout

### Queue & Background Jobs
- **Laravel Horizon** (Redis-backed) or **BullMQ**
- Redis for queue storage and job state

| Job | Description |
|-----|-------------|
| `AnalyseGameJob` | Main job, enqueued on import. Runs Stockfish, selects key moments, tags mistakes. |
| `GenerateExplanationsJob` | Enqueued by AnalyseGameJob. Calls LLM for each key moment. |
| `GenerateSummaryJob` | Enqueued after all explanations complete. Writes game summary. |
| `UpdateTrendsJob` | Enqueued after summary. Recomputes TrendSummary. |
| `RetryExplanationJob` | Enqueued on LLM failure. Retries up to 3 times. |

---

## Deployment Options

| Option | Trade-offs |
|--------|------------|
| Fully local (Docker Compose) | Free to run. Full control. No data privacy concerns. Harder to share. Stockfish runs fast locally. |
| Hosted (Railway, Fly.io, Render) | Shareable URL for portfolio. ~£5–15/month. Stockfish needs Docker container. Recommended for portfolio demo. |
| Hybrid: local dev + hosted demo | Best of both. Local for daily use. Hosted with sanitised data for CV/portfolio purposes. |

---

## Key Technical Decisions

- **Delegate all chess logic to chess.js** — do not re-implement FEN parsing, en passant, castling, fifty-move rule
- **Stockfish depth: 18** for MVP (configurable); cap to keep analysis under 60 seconds
- **LLM temperature: 0.3 or below** to reduce creative divergence in explanations
- **Explanation cost control** — use GPT-4o-mini; limit to 3 key moments per game; cache explanations; never regenerate without user action

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Engine performance on shared hosting | Queue with controlled concurrency; cap depth at 18; pre-warm process; fall back to local if too slow |
| LLM hallucination | Pass deterministic position data in every prompt; low temperature; validate moves mentioned match input; add disclaimer in UI |
| LLM API cost | GPT-4o-mini (~£0.15/million input tokens); 3 explanations per game; cache everything |
| Overbuilding | Strict MVP definition; nothing from Phase 1+ started until MVP definition of done is met |
| Explanation accuracy | Chess is genuinely complex — explanations will sometimes be wrong; make this clear in UI; encourage user notes to override |
| PGN copyright | Scope MVP to the user's own games only |
