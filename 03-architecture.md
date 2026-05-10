# Chess Coach Journal — Architecture

**As implemented (May 2026):** Next.js App Router + Laravel API + PostgreSQL; Laravel queues + Stockfish; Next **BFF** routes under `apps/web/app/api/...` proxying **`/api/v1/...`** on Laravel. Auth wired via Sanctum + onboarding gate. Trend **charts** on profile use a small Sparkline component (not necessarily Recharts).

---

## Pipeline Direction — Imported / Analysed / Coached

The system treats these as three **distinct states** that should not be conflated:

```text
Imported   — game exists locally with PGN + metadata
Analysed   — Stockfish evaluation completed
Coached    — coaching/key-moment/trend data generated
```

Sync, analysis, and coaching are now staged rather than chained:

```text
Sync metadata           → fast, cheap, always for every synced game
Analyse selectively     → auto for a small recent subset (MVP: 5 most recent), on demand otherwise
Generate coaching data  → derived from analysed games; depth grows as more games are analysed
```

Why this matters architecturally:

- **Stockfish is the expensive layer.** Importing 1,000 games is cheap; analysing 1,000 games is expensive. Most synced games are never opened.
- The games list is available **immediately** after sync — the user never waits for a queue to drain before browsing.
- Analysis becomes part of the **coaching workflow**, not background infrastructure.
- Coaching depth grows progressively: more analysed games → richer trends. This naturally enables progressive unlocks ("Analyse 5 games to unlock your coaching report"; "Analyse 20 to unlock recurring mistake trends") and a clean free/premium split.

Per-game `analysis_status` should be a first-class enum exposed in the API and UI:

```text
pending     — imported, no analysis run
queued      — job enqueued
analysing   — in-flight Stockfish run
analysed    — engine + classifications complete
failed      — job errored; user can retry
```

See [05-analysis-pipeline.md](./05-analysis-pipeline.md) for the staged pipeline detail.

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
- Key endpoints (versioned under `/api/v1/...`; BFF proxied as `/api/...`):
  - `POST /games` — manual PGN import (secondary path)
  - `GET /games/:id` — game with metadata + analysis state (works pre-analysis)
  - `POST /games/:id/analyse` — **on-demand** analysis trigger (queues `AnalyseGameJob`)
  - `GET /games?status=pending|analysed|...` — filterable game list
  - `POST /connected-accounts` — link a Chess.com / Lichess identity
  - `POST /connected-accounts/:id/sync` — sync metadata; auto-queues analysis only for the recent subset
  - `GET /trends`
  - `PATCH /key-moments/:id/notes`
- Auth: JWT or session-based (Laravel Sanctum implemented)

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
| `SyncChessComAccountJob` | Pulls metadata + PGNs for a connected account. **Does not** auto-queue analysis for every imported game. |
| `ImportExternalGameJob` | Per-game metadata import (PGN, moves, result, opening, ratings, opponent). Enqueues `AnalyseGameJob` **only** if the game falls within the recent auto-analyse subset (MVP: 5 most recent per sync). |
| `AnalyseGameJob` | Runs Stockfish, computes CP loss + classifications, persists key moments, sets `analysis_status = analysed`. Triggered by the recent-subset rule above or by `POST /games/:id/analyse`. |
| `GenerateExplanationsJob` | Enqueued by `AnalyseGameJob`. Calls LLM for each key moment. |
| `GenerateSummaryJob` | Enqueued after all explanations complete. Writes game summary. |
| `UpdateTrendsJob` | Enqueued after summary. Recomputes TrendSummary. Coaching depth grows as more games reach `analysed`. |
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
- **Sync ≠ analyse.** Sync imports metadata only. Stockfish only auto-runs for a small recent subset (MVP target: 5 most-recent games per sync). All other games stay `analysis_status = pending` until the user clicks "Analyse this game".
- **A game is useful before it is analysed.** Game pages must render board replay, opening/result metadata, and an "Analyse this game" CTA for `pending` games. Coaching/evaluation panels are unlocked once analysis completes.
- **Stockfish depth: 18** for MVP (configurable); cap to keep analysis under 60 seconds
- **LLM temperature: 0.3 or below** to reduce creative divergence in explanations
- **Explanation cost control** — use GPT-4o-mini; limit to 3 key moments per game; cache explanations; never regenerate without user action

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Engine performance on shared hosting | Sync imports metadata only; Stockfish runs only for the recent auto-subset and explicit on-demand requests; queue with controlled concurrency; cap depth at 18; pre-warm process; fall back to local if too slow |
| Wasted compute on never-reviewed games | Most synced games are never opened. Auto-analysing the entire archive burns CPU and queue depth for no user value. Staged pipeline auto-analyses only the recent subset; everything else is on-demand. |
| LLM hallucination | Pass deterministic position data in every prompt; low temperature; validate moves mentioned match input; add disclaimer in UI |
| LLM API cost | GPT-4o-mini (~£0.15/million input tokens); 3 explanations per game; cache everything |
| Overbuilding | Strict MVP definition; nothing from Phase 1+ started until MVP definition of done is met |
| Explanation accuracy | Chess is genuinely complex — explanations will sometimes be wrong; make this clear in UI; encourage user notes to override |
| PGN copyright | Scope MVP to the user's own games only |
