# Admin guide

Operational commands and workflows for **Chess Coach Journal** (operators, maintainers, local dev). All Artisan commands below are run from the API app:

```sh
cd apps/api
```

Keep a **queue worker** running whenever you rely on background jobs (imports, analysis):

```sh
php artisan queue:work
```

If `QUEUE_CONNECTION=sync` in `.env`, jobs run inline during the request/command and you do not need a worker.

---

## Connected accounts: Chess.com archive sync

The web **Sync** action uses a **recent window** (same cap as production `SyncChessComAccountJob` with `fullArchive = false`). For a **full history** pull from Chess.com’s public archives, use the CLI.

### `chess:sync-connected-account`

Fetches Chess.com player stats and game archives, then queues `ImportExternalGameJob` for each game (deduped by Chess.com game UUID).

| Argument | Description |
|----------|-------------|
| `platform` | Must be `chesscom`. (`lichess` is not implemented for this command.) |
| `username` | Chess.com username as shown on the site (display form is fine; storage uses a normalised key). |

| Option | Description |
|--------|-------------|
| `--create` | Create a `connected_accounts` row if none exists for that platform + username (`user_id` may be null). |
| `--recent` | Match **web sync** behaviour: only the **recent-game window** (up to 20 games across archive months). **Omit** this for **full archive** import. |
| `--sync` | Run `SyncChessComAccountJob` **synchronously in this process** (all HTTP archive fetches complete here). Import jobs are still **queued** unless the queue connection is `sync`. |
| *(none)* | Dispatch `SyncChessComAccountJob` to the queue only; a worker must process it. |

**Examples**

```sh
# Full history: create row if needed, enqueue sync job
php artisan chess:sync-connected-account chesscom YourName --create

# Full history: run archive HTTP work in this terminal (imports still via queue)
php artisan chess:sync-connected-account chesscom YourName --sync

# Same behaviour as the website Sync button (20-game cap)
php artisan chess:sync-connected-account chesscom YourName --recent
```

**Behaviour notes**

- Large accounts enqueue **many** import jobs; ensure workers and rate limits are acceptable for your environment.
- Imports parse PGN and create games as **metadata only** (`analysis_status = pending`). Under the staged sync/analyse/coach pipeline (see [05-analysis-pipeline.md](./05-analysis-pipeline.md), [03-architecture.md](./03-architecture.md)), `SyncChessComAccountJob` auto-queues `AnalyseGameJob` only for a small recent subset (MVP target: the **5 most-recent newly imported** games per sync run). All other games stay `pending` until analysed on demand via `POST /api/v1/games/{id}/analyse` or `chess:analyse {game_id}` below.
- HTTP uses a simple User-Agent (`CalvertChessCoach/1.0`); respect Chess.com’s public API usage.

---

## Game analysis (Stockfish)

In the staged pipeline, sync no longer auto-queues analysis for every imported game. The commands below are how operators run the **expensive layer** explicitly.

### `chess:analyse`

Run analysis for a **single** game by UUID (useful for local debugging or covering a `pending` game outside the recent auto-analyse subset).

```sh
php artisan chess:analyse {game_id} [--force]
```

### `chess:reanalyse`

Bulk re-analysis with force enabled.

```sh
php artisan chess:reanalyse --all
php artisan chess:reanalyse --game_id=<uuid> [--game_id=<uuid> ...]
```

You must pass either `--all` or at least one `--game_id`.

> **Cost note:** `--all` will analyse *every* imported game including ones the user has never opened. Under the staged pipeline this is the operator's intentional override of the on-demand model. Prefer per-game runs in production.

### `chess:compute-weakness-profiles`

Recomputes stored **weakness profile** rows (aggregate phase / opening / motif summaries) from analysed games and normalized move events. Dispatches `ComputeWeaknessProfileJob` per account or for a single `--account=` UUID. Use after bulk backfills or when testing profile scoring changes.

### `chess:backfill-move-events`

Populates `move_tactical_events` and `move_threat_events` from existing move JSON (`tactical_flags`, `threat_awareness`) for games that were analysed before normalized tables existed.

---

## Related docs

| Doc | Purpose |
|-----|---------|
| [apps/api/tests/Tests.md](./apps/api/tests/Tests.md) | API test suite and behaviour reference |
| [README.md](./README.md) | Local setup, stack, URLs |
| [AGENTS.md](./AGENTS.md) | Contributor / agent expectations |
