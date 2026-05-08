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
- Imports parse PGN and create games; analysis may be queued separately depending on your pipeline configuration.
- HTTP uses a simple User-Agent (`CalvertChessCoach/1.0`); respect Chess.com’s public API usage.

---

## Game analysis (Stockfish)

### `chess:analyse`

Run analysis for a **single** game by UUID (useful for local debugging).

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

---

## Related docs

| Doc | Purpose |
|-----|---------|
| [apps/api/tests/Tests.md](./apps/api/tests/Tests.md) | API test suite and behaviour reference |
| [README.md](./README.md) | Local setup, stack, URLs |
| [AGENTS.md](./AGENTS.md) | Contributor / agent expectations |
