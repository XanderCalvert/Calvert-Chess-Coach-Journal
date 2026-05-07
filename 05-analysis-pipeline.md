# Chess Coach Journal — Analysis Pipeline

Analysis is an asynchronous pipeline triggered when a user submits a game for import. It runs as a series of background jobs to avoid blocking the HTTP request.

---

## Step 1: PGN Parsing

- Receive raw PGN string from the API endpoint
- Parse using chess.js (Node) or a PHP chess library on the backend
- Extract headers: White, Black, Date, Result, ECO, Event
- Validate that the move sequence is legal
- Create the `Games` record with status `pending`
- Create one `Moves` record per half-move, storing FEN before and SAN
- Enqueue the Stockfish analysis job

---

## Step 2: Stockfish Evaluation

- Worker picks up the job, receiving the `game_id`
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
- On completion, set game status to `engine_complete` and enqueue explanation job

---

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
| `AnalyseGameJob` | On import | Runs Stockfish, selects key moments, tags mistakes |
| `GenerateExplanationsJob` | After AnalyseGameJob | Calls LLM for each key moment |
| `GenerateSummaryJob` | After all explanations complete | Writes game summary |
| `UpdateTrendsJob` | After summary | Recomputes TrendSummary |
| `RetryExplanationJob` | On LLM failure | Retries up to 3 times with backoff |
