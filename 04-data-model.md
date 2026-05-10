# Chess Coach Journal — Data Model

All tables use UUID primary keys. **Target:** data scoped to the authenticated user; **today** Chess.com–imported games may have `user_id` null while `connected_account_id` is set.

---

## Game lifecycle states

A game's lifecycle is split into three independent concepts. They are surfaced via `games.analysis_status` (engine state) plus the existence of related rows (key moments, summary, trend deltas) for the coaching state. They should not be conflated in code or UI:

```text
Imported   — Games row exists with PGN + Moves rows; analysis_status = pending
Analysed   — Stockfish run complete; analysis_status = analysed; engine_analyses + classifications populated
Coached    — Key moments, explanations, summary, and trend deltas generated
```

`analysis_status` enum:

| State | Meaning |
|-------|---------|
| `pending` | Imported but no analysis run. Game page renders board + metadata; "Analyse this game" CTA shown. |
| `queued` | `AnalyseGameJob` enqueued but not yet picked up by a worker. |
| `analysing` | Worker actively running Stockfish for this game. |
| `analysed` | Engine analysis + per-move classifications complete. Coaching surfaces unlock from here. |
| `failed` | Job errored. User can retry via `POST /games/{id}/analyse`. |

Note: previous values (`running`, `complete`) are superseded by `analysing` and `analysed` respectively. Treat the migration as a rename, not a data change.

---

## Users

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `email` | string, unique | Used for auth |
| `password_hash` | string | bcrypt or Argon2 |
| `display_name` | string, nullable | Shown in UI |
| `rating_estimate` | integer, nullable | For explanation depth |
| `explanation_depth` | enum | `beginner` \| `club` \| `experienced` |
| `created_at` | timestamp | |

---

## Games

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `user_id` | FK → Users, nullable | Null for pre-auth external imports |
| `connected_account_id` | FK → Connected Accounts, nullable | Source account for synced imports |
| `pgn_raw` | text | Original PGN string |
| `white_player` | string | From PGN headers |
| `black_player` | string | From PGN headers |
| `played_at` | datetime | From PGN or import |
| `result` | enum | `white` \| `black` \| `draw` \| `unknown` |
| `user_colour` | enum | `white` \| `black` |
| `opening_name` | string | From ECO code or header |
| `eco_code` | string | e.g. `B20` |
| `move_count` | integer | Total half-moves |
| `accuracy_pct` | decimal | 0–100, computed |
| `blunder_count` | integer | |
| `mistake_count` | integer | |
| `inaccuracy_count` | integer | |
| `summary_text` | text | LLM-generated summary |
| `analysis_status` | enum | `pending` \| `queued` \| `analysing` \| `analysed` \| `failed` (was: `pending` \| `running` \| `complete` \| `failed`) |
| `analysis_requested_at` | timestamp, nullable | When the most recent analysis was triggered (auto-subset rule or explicit `POST /games/{id}/analyse`) |
| `imported_from` | enum | `paste` \| `chesscom` \| `lichess` |
| `external_id` | string, nullable | For deduplication on import |
| `share_code` | string(8), nullable, unique | Public `/g/{share_code}` URL key |
| `platform` | string, nullable | Import source platform for profile flows |
| `time_control` | string, nullable | e.g. `600+0`, `180+2` |
| `rated` | boolean, nullable | Rated/casual when provided by source |
| `user_rating_before` | smallint, nullable | Rating before game |
| `user_rating_after` | smallint, nullable | Rating after game |
| `opponent_username` | string, nullable | Opponent display/handle |
| `opponent_rating` | smallint, nullable | Opponent rating at game time |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## Moves

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `game_id` | FK → Games | |
| `move_number` | integer | Half-move index (1 = White's first) |
| `san` | string | Standard algebraic notation |
| `uci` | string | e.g. `e2e4` |
| `fen_before` | string | FEN before this move |
| `fen_after` | string | FEN after this move |
| `colour` | enum | `white` \| `black` |
| `cp_score` | integer | Centipawn evaluation after move |
| `cp_loss` | integer | Difference vs best move |
| `classification` | enum | `best` \| `excellent` \| `good` \| `inaccuracy` \| `mistake` \| `blunder` |
| `themes` | JSON, nullable | Deterministic coaching themes |
| `tactical_flags` | JSON, nullable | Tactical pattern flags |
| `threat_awareness` | JSON, nullable | Threat-response summary object |
| `risk_note` | text, nullable | Deterministic coach note |
| `consecutive_miss_count` | smallint, nullable | Repeated threat-miss count |
| `coaching_version` | smallint, nullable | Coaching schema/version marker |
| `game_phase` | enum, nullable | `opening` \| `middlegame` \| `endgame` |
| `complexity_score` | smallint, nullable | Position complexity hint |
| `ai_explanation` | text, nullable | Cached AI move explanation |
| `ai_explanation_status` | enum, nullable | `pending` \| `complete` \| `failed` |
| `ai_explanation_model` | string(64), nullable | Model used for cached explanation |

---

## Engine Analyses

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `move_id` | FK → Moves | |
| `engine_name` | string | Engine identifier (default `stockfish`) |
| `best_move_uci` | string | Engine's best move |
| `best_move_san` | string, nullable | In algebraic notation when available |
| `best_line` | JSON | Array of SAN moves |
| `depth` | integer | Stockfish search depth |
| `depth_requested` | integer | Target depth requested by caller |
| `depth_reached` | integer | Actual depth reached by engine |
| `cp_evaluation` | integer | Raw centipawn score |
| `analysed_at` | timestamp | |

---

## Key Moments

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `game_id` | FK → Games | |
| `move_id` | FK → Moves | |
| `rank` | integer | 1, 2, or 3 within the game |
| `mistake_tag_id` | FK → Mistake Tags | |
| `cp_loss` | integer | Centipawn loss at this moment |
| `explanation_text` | text | LLM-generated explanation |
| `explanation_status` | enum | `pending` \| `complete` \| `failed` |
| `game_phase` | enum | `opening` \| `middlegame` \| `endgame` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## Mistake Tags

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `slug` | string | e.g. `hanging-piece`, `king-safety` |
| `label` | string | e.g. `Hanging Piece` |
| `description` | string | One-sentence explanation of the category |
| `phase_hint` | enum | `any` \| `opening` \| `middlegame` \| `endgame` |

---

## Trend Summaries

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `user_id` | FK → Users | |
| `computed_at` | timestamp | Rebuilt after each new analysis |
| `games_analysed` | integer | |
| `avg_accuracy` | decimal | |
| `blunders_per_game` | decimal | |
| `top_mistake_tag_id` | FK → Mistake Tags | |
| `opening_weakness` | string | ECO code with worst record |
| `phase_weakness` | enum | `opening` \| `middlegame` \| `endgame` |
| `summary_json` | JSON | Full breakdown for trends page |

---

## Manual Notes

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `user_id` | FK → Users | |
| `game_id` | FK → Games, nullable | |
| `key_moment_id` | FK → Key Moments, nullable | |
| `note_text` | text | Freetext |
| `coach_agreement` | enum | `agreed` \| `disagreed` \| `not_set` |
| `created_at` | timestamp | |

---

## Study Recommendations

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `user_id` | FK → Users | |
| `mistake_tag_id` | FK → Mistake Tags | The topic |
| `reason_text` | text | Why this was recommended |
| `description_text` | text | How to study it |
| `status` | enum | `active` \| `in_progress` \| `done` \| `dismissed` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `completed_at` | timestamp, nullable | |

---

## Connected accounts (implemented)

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `user_id` | FK → Users, nullable | Optional link when auth exists |
| `platform` | string | e.g. `chesscom` |
| `username` | string | Display username |
| `normalised_username` | string | Lowercase; unique with `platform` |
| `external_id` | string, nullable | Reserved |
| `profile_url` | string, nullable | |
| `rapid_rating` | smallint, nullable | From Chess.com stats API |
| `blitz_rating` | smallint, nullable | |
| `bullet_rating` | smallint, nullable | |
| `daily_rating` | smallint, nullable | |
| `last_synced_at` | timestamp, nullable | |
| `sync_status` | string | `never_synced` \| `syncing` \| `synced` \| `failed` |

## Implementation status (May 2026)

- [x] Schema + models for games, moves, engine analyses, key moments (table), mistake tags (seeded), manual notes, trend summaries (table)
- [x] Connected accounts + Chess.com import metadata on games
- [x] **Personal access tokens** (Sanctum) — `personal_access_tokens` migration + Sanctum config wired (commit c57641d)
- [x] **Key moments populated** during analysis: `AnalyseGameJob` selects top non-adjacent inaccuracy/mistake/blunder plies (cap 3, ranked, with phase + tag) and writes `KeyMoment` rows with `explanation_status = not_requested`; `key_moments.mistake_tag_id` is now nullable (commit 33dd5bd)
- [x] **Sync no longer auto-queues analysis for every imported game** — `ImportExternalGameJob` writes `analysis_status = pending` only; `QueueRecentAnalysisJob` runs the recent-subset rule (commit 748bc51)
- [ ] `analysis_status` enum migrated to `pending` / `queued` / `analysing` / `analysed` / `failed` (still `pending` / `running` / `complete` / `failed` in code)
- [ ] `analysis_requested_at` column added
- [ ] Key-moment **explanation text** populated end-to-end in UI (LLM step still outstanding; selection + persistence + render are done)
- [ ] Trend summary rows driven by product “trends” page (profile uses **query-time** stats instead)
