# Chess Coach Journal — Analysis Pipeline

The pipeline is **staged**, not single-shot. It is split into three independent stages so the expensive engine work only runs when it is actually needed:

```text
Sync metadata         (cheap, default for every imported game)
    ↓
Analyse selectively   (auto for a small recent subset; on demand otherwise)
    ↓
Generate coaching    (key moments, explanations, summary, trends — derived from analysed games)
```

The legacy "import → immediately analyse every game with Stockfish" flow is deprecated. Analysing hundreds of historical games most users will never review wastes compute and slows the games list. Instead, sync should always feel instant, and analysis should feel like an intentional coaching action.

**Status (May 2026):**

- PGN parse → persist moves → `AnalyseGameJob` (Stockfish, CP loss, classifications, game counters, `analysis_status`) is **live**.
- **Key-moment selection + persistence is live** (commit 33dd5bd): `AnalyseGameJob` picks top non-adjacent inaccuracy/mistake/blunder plies (cap 3, ranked), assigns phase + mistake tag, writes `KeyMoment` rows with `explanation_status = not_requested`; surfaced via API and rendered in `KeyMomentsPanel` on `/g/{share_code}`.
- **Sync vs analysis split is live on the backend** (commit 748bc51): `ImportExternalGameJob` no longer auto-dispatches `AnalyseGameJob`; `SyncChessComAccountJob` defers a `QueueRecentAnalysisJob` (60s delay) which dispatches analysis for the most-recent N pending games per account (default `5`, configurable via `CHESS_AUTO_ANALYSE_ON_SYNC`). New `POST /api/v1/games/{id}/analyse` endpoint plus the BFF route at `/api/games/{id}/analyse` powers on-demand analysis from the games list.
- **Still outstanding** vs the doc below: `analysis_status` enum rename (`queued` / `analysing` / `analysed`), `analysis_requested_at` column, `pending`-aware game detail page, manual PGN import opt-in analyse toggle, LLM explanation generation, and post-game summary / trend-update jobs. See [09-build-roadmap.md](./09-build-roadmap.md) Phase 3.5.

---

## Stage A — Sync / Import (metadata only)

The cheap layer. Every imported game flows through here regardless of whether it will ever be analysed.

### A.1 Connected-account sync (primary path)

- Triggered by `POST /api/v1/connected-accounts/{id}/sync` or scheduled refresh
- `SyncChessComAccountJob` pulls archives + ratings
- For each new game (deduped by `connected_account_id` + Chess.com game UUID), enqueue `ImportExternalGameJob`
- `ImportExternalGameJob` persists:
  - PGN raw + parsed moves
  - result, opening (ECO + name), date, time control
  - rated flag, user rating before/after, opponent username + rating
  - `analysis_status = pending`
- `SyncChessComAccountJob` then selects the **recent auto-analyse subset** (MVP: the **5 most-recent newly-imported games** for that sync run) and dispatches `AnalyseGameJob` for those only.
- All other newly imported games stay `pending` until the user explicitly analyses them.

### A.2 Manual PGN import (secondary path)

- `POST /api/v1/games` with a pasted PGN
- Used for OTB / club / training positions / unsupported sources
- Imported game is created with `analysis_status = pending` and **no analysis is auto-queued**; the user clicks "Analyse this game" on the resulting game page (or the games list)
- Optional UX: keep an "Analyse on submit" toggle on the import form for users who want the legacy behaviour for a one-off paste

After Stage A, the user can browse and replay every synced game immediately — the engine has done no work yet for the bulk of the archive.

---

## Stage B — Selective Stockfish Analysis (engine, on demand)

The expensive layer. Triggered either by the recent-subset rule in Stage A or by an explicit user action.

### B.1 Trigger

- `POST /api/v1/games/{id}/analyse`
  - Validates ownership
  - Sets `analysis_status = queued`, `analysis_requested_at = now()`
  - Dispatches `AnalyseGameJob`
- Auto-trigger from `SyncChessComAccountJob` for the recent subset (same code path)

### B.2 Stockfish run

The engine work below is unchanged from the previous design — what's new is **when** it runs.

## Step 1: PGN Parsing (already done in Stage A)

For manual PGN imports the parse happens at import time. For synced games it happens inside `ImportExternalGameJob`. Either way, by the time `AnalyseGameJob` runs the `Games` row + `Moves` rows already exist.

---

## Step 2: Stockfish Evaluation

- Worker picks up the job, sets `analysis_status = analysing`
- For each move in order, construct the FEN position and pass it to Stockfish
- Stockfish runs server-side as a child process (`child_process.spawn` or `proc_open`)
- Each position analysed to a fixed depth (default: **18** for MVP, configurable)
- Store best move, best line (3–4 moves), and centipawn score in `Engine Analyses`
- Centipawn loss = `abs(score_before - score_after)` from the moving player's perspective
- Classify moves by cp loss:
  - **Blunder** — > 150 cp
  - **Mistake** — > 50 cp
  - **Inaccuracy** — > 20 cp
  - **Good / Best** — otherwise
- Accuracy formula: `accuracy = 103.1668 × exp(−0.04354 × avg_cp_loss) − 3.1669`
- On completion, set `analysis_status = analysed` and enqueue the coaching jobs (Stage C)
- On failure, set `analysis_status = failed` so the UI can surface a retry control

---

---

## Stage C — Coaching Generation (derived from analysed games)

Steps 3–7 below run only after Stage B for a given game. The richness of the coaching layer grows naturally as more games are analysed — this is the basis for progressive unlocks ("Analyse 5 games to unlock your coaching report"; "Analyse 20 to unlock recurring mistake trends").

## Step 3: Key Moment Selection

- Query all `Moves` for the game ordered by `cp_loss` descending
- Select top 3 by `cp_loss`
- Apply clustering filter: if two consecutive moves both qualify, prefer the higher-loss one
- Assign `game_phase` by move number (heuristic):
  - Moves 1–15 → `opening`
  - Moves 16–35 → `middlegame`
  - Moves 36+ → `endgame`
- Create `Key Moments` records with rank 1, 2, 3

---

## Step 4: Heuristic Mistake Tagging

Rule-based detection for MVP tags only. All others assigned by LLM or manually by the user.

| Tag | Heuristic |
|-----|-----------|
| **Hanging Piece** | Played move leaves a piece attacked more times than defended; opponent can capture next move |
| **Missed Capture** | Opponent had a capturable piece; engine best move is a capture but played move was not |
| **King Safety** | Castled king's pawn shelter broken, or rook defending king was traded; check pawns in front of castled king in last 3 moves |
| **Poor Development** | Opening (moves 1–15); a piece moved twice when another unplaced piece remains on the back rank |
| **Overlooked Opponent Threat** | Opponent's previous move created a clear threat (check, capture, or promotion) that was not addressed |
| **Default** | All other moments tagged `Missed Tactic`; refined by LLM explanation or manually |

---

## Step 5: LLM Explanation Generation

- For each key moment, build a structured prompt (see `06-explanation-system.md`)
- Send to LLM API; receive a 2–4 sentence explanation
- Store in `key_moments.explanation_text` and set `explanation_status = complete`
- On API error: set status to `failed`, retry up to 3 times with exponential backoff

---

## Step 6: Game Summary Generation

- After all key moment explanations are complete, generate a game-level summary
- Prompt includes: opening name, accuracy, blunder/mistake/inaccuracy counts, top mistake tag, one-line summary of each key moment
- Store in `games.summary_text`

---

## Step 7: Trend Update

- After game summary is stored, recompute the user's `Trend Summary`
- Aggregate across all analysed games: avg accuracy, blunders per game, top mistake tag, phase weakness
- Upsert the `trend_summaries` record
- Optionally enqueue a recommendation update job

---

## Job Summary

| Job | Trigger | Description |
|-----|---------|-------------|
| `SyncChessComAccountJob` | User clicks Sync, scheduled refresh, or CLI | Fetches archives + ratings; enqueues `ImportExternalGameJob` per new game; selects the recent auto-analyse subset (MVP: 5 most-recent newly imported) and dispatches `AnalyseGameJob` for those only |
| `ImportExternalGameJob` | From sync | Persists PGN + moves + metadata; sets `analysis_status = pending`; **does not** auto-queue analysis |
| `AnalyseGameJob` | Recent-subset rule, or `POST /games/{id}/analyse` | Sets `analysis_status = analysing`; runs Stockfish; sets `analysis_status = analysed`; enqueues `GenerateExplanationsJob` |
| `GenerateExplanationsJob` | After `AnalyseGameJob` | Calls LLM for each key moment |
| `GenerateSummaryJob` | After all explanations complete | Writes game summary |
| `UpdateTrendsJob` | After summary | Recomputes TrendSummary; coaching depth scales with the number of analysed games |
| `RetryExplanationJob` | On LLM failure | Retries up to 3 times with backoff |

---

## Recommended MVP analysis strategy

- **Auto-analyse a small recent subset after sync.** MVP target: the **5 most-recent newly-imported games** per sync run. This number should be configurable per user/tier later.
- **Everything else stays `pending` until explicitly analysed.** Surfaced as an "Analyse this game" CTA on both the games list (for `pending` rows) and the game detail page.
- **Game pages must be useful before analysis.** Board replay, opening/result metadata, and the analysis CTA are visible for `pending` games; coaching/evaluation panels are locked until `analysed`.
- **Surface `analysis_status` clearly** in the games list and game header: `Pending` / `Queued` / `Analysing` / `Analysed` / `Failed`.

This gives onboarding instant value (a few games already analysed when the user first opens the games list) without burning compute on hundreds of historical games no one will open.
