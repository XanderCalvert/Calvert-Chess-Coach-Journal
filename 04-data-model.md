# Chess Coach Journal — Data Model

All tables use UUID primary keys. All data is scoped to the authenticated user.

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
| `user_id` | FK → Users | |
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
| `analysis_status` | enum | `pending` \| `running` \| `complete` \| `failed` |
| `imported_from` | enum | `paste` \| `chesscom` \| `lichess` |
| `external_id` | string, nullable | For deduplication on import |
| `created_at` | timestamp | |

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

---

## Engine Analyses

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID PK | |
| `move_id` | FK → Moves | |
| `best_move_uci` | string | Engine's best move |
| `best_move_san` | string | In algebraic notation |
| `best_line` | JSON | Array of SAN moves |
| `depth` | integer | Stockfish search depth |
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
| `completed_at` | timestamp, nullable | |
