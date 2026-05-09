# Chess Coach Journal — Interactive Analysis + Player Profiles Roadmap

## Purpose

This feature branch is moving the app from a one-off PGN analysis tool into the foundation of a player improvement platform.

The current app can import games, analyse moves with Stockfish, classify mistakes, and replay a game on an interactive board. The next product step is to help a player understand their own habits over time: rating trends, blunder patterns, opening weaknesses, and recurring coaching themes.

The long-term direction is:

> Connect a chess account → sync games → analyse automatically → track trends → recommend what to study next.

---

## Product Principles

### 1. UUIDs are internal only

Users should not normally see URLs like:

```text
/games/019e069f-0ede-72cd-a7b6-054bba9a782a/analysis
```

Public and user-facing game URLs should use short share codes:

```text
/g/k7m2xq9r
/g/k7m2xq9r?ply=8
```

UUID routes can remain available for internal/debug use, but normal UI navigation should prefer `/g/{share_code}`.

### 2. A game is useful, but a player profile is valuable

Single game analysis answers:

> What happened in this game?

A profile should answer:

> What kind of player am I?
> What mistakes do I keep making?
> Am I improving?
> What should I study next?

### 3. Stockfish provides evaluation; the app provides interpretation

The product should not simply expose engine data. It should turn analysis into understandable patterns, trends, and suggested focus areas.

### 4. Keep the MVP practical

Avoid early distractions:

* social features
* comments
* live play
* advanced opening explorer
* full AI chat
* complex multi-platform sync

Prioritise:

* reliable imports
* board replay
* trend tracking
* mistake summaries
* useful coaching direction

---

## Current Completed Direction

### API versioning

Laravel API routes live under:

```text
/api/v1
```

Examples:

```text
/api/v1/games
/api/v1/games/{id}
/api/v1/games/by-share-code/{code}
```

The Next.js BFF routes proxy through as user-facing internal frontend routes:

```text
/api/games
/api/games/by-share-code/{code}
/api/import-pgn
/api/parse-pgn
```

### Interactive game analysis

The analysis interface supports:

* visual chess board
* move replay
* clickable move list
* previous/next/start/end controls
* keyboard left/right navigation
* active move highlighting
* last-move square highlighting
* board orientation based on player colour
* move detail panel
* public share links
* current-position links via `?ply=N`

### Public game URLs

Lowercase, ambiguity-safe 8-character share codes.

Alphabet:

```text
abcdefghjkmnpqrstuvwxyz23456789
```

Excluded:

```text
i, l, o, 0, 1
```

Example:

```text
/g/k7m2xq9r
```

Capacity:

```text
31^8 = 852,891,037,441 possible codes
```

### Ply links

Deep links to a specific position:

```text
/g/k7m2xq9r?ply=8
```

Mapping:

```text
ply=0 => starting position
ply=1 => after White's first move
ply=2 => after Black's first move
ply=8 => after Black's fourth move
```

The UI updates the URL with `router.replace()` as the user navigates, without spamming browser history.

---

## Phase 1 — Finish and Harden Interactive Game Analysis ✅ COMPLETE

### Goal

Make the individual game analysis page feel polished, shareable, and reliable.

### Features

* `/g/{share_code}` public game page ✅
* `/g/{share_code}?ply=N` current position support ✅
* copy share link button ✅
* copy current position button ✅
* UUID route hidden from normal UI navigation ✅
* move detail panel ✅
* board orientation ✅
* active move highlighting ✅
* keyboard navigation ✅
* mobile-friendly layout ✅

### Acceptance Criteria

* `/g/k7m2xq9r` opens the game at the start position ✅
* `/g/k7m2xq9r?ply=8` opens after the 8th ply ✅
* clicking moves updates the board ✅
* arrow keys update the board and URL ✅
* refresh preserves selected ply ✅
* copy share link copies `/g/{share_code}` ✅
* copy current position copies `/g/{share_code}?ply=N` ✅
* normal UI links do not expose UUIDs ✅
* `/games/{uuid}/analysis` still works as internal/debug route ✅

### Relevant files

```text
apps/web/components/GameAnalysisView.tsx
apps/web/components/ChessBoardViewer.tsx
apps/web/components/MoveNavControls.tsx
apps/web/components/MoveDetailPanel.tsx
apps/web/app/g/[code]/page.tsx
apps/web/app/games/[id]/analysis/page.tsx
apps/api/app/Support/ShareCodeGenerator.php
apps/api/app/Http/Controllers/GameController.php
apps/api/routes/api.php
```

---

## Phase 2 — Player Profiles MVP ✅ Done (baseline)

### Goal

Introduce the concept of a player profile that can collect games under one player identity.

This does not need full authentication yet — the model should be designed so user accounts can be added later.

### Core User Story

As a chess player, I want to enter my Chess.com username so the app can import my games and show trends about my play.

### Proposed Profile Routes

Recommended MVP route:

```text
/u/{username}
```

Examples:

```text
/u/mattcalvert
/u/hikaru
```

### Initial Profile Features

A profile should show:

* platform username
* platform source (Chess.com)
* ratings by time control if available
* total games imported
* total games analysed
* last synced time
* recent games list
* basic trend cards

Example summary:

```text
Chess.com: mattcalvert
Rapid: 1187
Blitz: 1034
Games analysed: 142
Last synced: 2 hours ago
Average blunders/game: 1.8
Average CPL: 63
```

### Data Model

#### connected_accounts

Represents an external chess account.

Fields:

```text
id
user_id nullable (for now)
platform enum: chesscom, lichess
username
normalised_username (unique with platform)
external_id nullable
profile_url nullable
rapid_rating, blitz_rating, bullet_rating, daily_rating nullable (from Chess.com stats API)
last_synced_at nullable
sync_status enum: never_synced, syncing, synced, failed
created_at
updated_at
```

#### games (additions)

```text
connected_account_id nullable FK
platform enum nullable
external_id nullable
share_code nullable unique (8-char)
time_control nullable
rated boolean nullable
user_rating_before nullable
user_rating_after nullable
opponent_username nullable
opponent_rating nullable
```

### Acceptance Criteria

* A Chess.com username can be saved as a connected account
* A `/u/{username}` page displays stored games for that account
* Recent games are listed with result, opponent, time control, and analysis status
* Existing manually imported games still work
* Game analysis can be associated with a tracked player when relevant

### Build Steps

- [x] Migration: `create_connected_accounts_table`
- [x] Migration: add game fields (connected_account_id, platform, time_control, rated, ratings, opponent)
- [x] Model: `ConnectedAccount` with relationship to `Game`
- [x] API: `POST /api/v1/connected-accounts` — create/upsert by platform + username
- [x] API: `GET /api/v1/connected-accounts/by-username/{platform}/{username}/games` — paginated games for account
- [x] Frontend: username entry form (create connected account)
- [x] Frontend: `/u/[username]/page.tsx` — profile page with games list

---

## Phase 3 — Chess.com Game Sync ✅ Done

### Goal

Allow the app to fetch public Chess.com games for a username and import them automatically.

### MVP Sync Scope

Chess.com only. No OAuth — public username-based archive access.

### Sync Flow

```text
User enters Chess.com username
→ app creates/updates connected account
→ app fetches monthly game archives
→ app imports recent games (UI) or full history (CLI)
→ imported games are queued for analysis
→ profile stats update after analysis completes (query-time aggregation)
```

### Sync Controls

Profile page includes:

- [x] Sync now button
- [x] Last synced timestamp
- [x] Sync status
- [x] Basic error message if sync fails

### Sync Limits

- [x] **Web / API job (default):** latest **20** games across archive months (`SyncChessComAccountJob` with `fullArchive = false`)
- [x] **CLI:** full archive or same 20-game window — `chess:sync-connected-account` ([ADMIN-GUIDE.md](./ADMIN-GUIDE.md))

### Jobs

```text
SyncChessComAccountJob   (ratings + archives; optional fullArchive)
ImportExternalGameJob
AnalyseGameJob
```

Player stats are computed in `ConnectedAccountController::statsByUsername` (no separate refresh job).

### External Game Deduplication

Unique key:

```text
connected account + Chess.com game uuid (stored as external_id on games)
```

### Acceptance Criteria

- [x] User can enter a Chess.com username
- [x] App imports recent games without duplicate records
- [x] Imported games are queued for Stockfish analysis
- [x] Sync can be re-run safely
- [x] Profile page shows updated game count after sync

---

## Phase 4 — Player Trend Dashboard ✅ Mostly done (on profile)

### Goal

Turn analysed games into useful player-level trends.

### Core Metrics

* games analysed
* win/draw/loss record
* current rating by time control
* rating trend
* average centipawn loss trend
* blunders per game
* mistakes per game
* inaccuracies per game
* best/worst recent games

### Suggested Trend Cards

```text
Rating trend
Average CPL
Blunders per game
Mistakes per game
Accuracy estimate
Most common mistake type
Weakest phase
Best opening
Worst opening
```

### MVP Charts

* rating over time
* blunders per game over time
* average CPL over time

Use existing Sparkline component where possible.

### Aggregation Approach

Start with query-time aggregation. If performance becomes an issue, introduce cached aggregate tables later.

Possible future table `player_stat_snapshots`:

```text
connected_account_id
period_start
period_end
time_control
games_count
wins / losses / draws
avg_cp_loss
avg_blunders / avg_mistakes / avg_inaccuracies
rating_start / rating_end
```

### Acceptance Criteria

- [x] Profile dashboard displays analysed game trends (aggregates + sparklines for rating, avg CPL, blunders/game)
- [x] Stats update after games are imported and analysed (query-time; refresh after sync)
- [x] Game-type filter (bullet / blitz / rapid / daily) scopes stats and game list; dropdown hides empty types and defaults to most-played analysed bucket
- [x] User can quickly tell whether blunders/mistakes are improving (per-filter aggregates)
- [x] Recent analysed games link to `/g/{share_code}`
- [ ] Cached `player_stat_snapshots` table (optional optimisation — not required yet)
- [ ] “Most common mistake type” / phase / opening theme cards (needs tagging pipeline)

---

## Phase 5 — Mistake Categories and Key Themes

### Goal

Move beyond engine classifications and start identifying the type of mistakes a player makes repeatedly.

This phase should evolve toward blunder fingerprinting: not just "you blundered," but the recurring pattern and context behind the blunder.

### Initial Mistake Categories

```text
hanging_piece
missed_capture
missed_tactic
king_safety
back_rank
opening_principle
endgame_technique
time_trouble
poor_trade
missed_mate
allowed_mate
pawn_weakness
```

### How to Implement Initially

Start rule-based and conservative.

Examples:

* large negative eval swing after moving a defended piece away → may suggest hanging material
* missed mate if engine PV contains mate and played move does not
* opening principle flag if repeated early queen moves, undeveloped minor pieces, or king left central too long
* endgame flag based on low material count

Do not overclaim. Prefer:

```text
Possible theme: hanging piece
```

### Key Moment Model

A key moment should connect:

* game
* move
* classification
* cp loss
* theme/category
* short explanation
* optional AI explanation later

### Profile-Level Theme Summary

```text
Across your last 25 rapid games:
- 42% of your blunders involved undefended pieces
- your biggest eval swings happen in the middlegame
- you often lose advantage after queen trades
```

### Blunder Fingerprinting Dimensions (next expansion)

Track recurring mistakes across:
- blunder type
- board state / position type
- tactical motif
- game phase
- pressure/time context
- behavioural pattern (e.g. tunnel vision while attacking)

Example profile insight:

```text
62% of your blunders happen while attacking, and most come from missed counter-threats.
```

### Acceptance Criteria

* Key moments can be tagged with a mistake category
* Move detail panel can display category/theme
* Profile page can show most common themes
* The app can produce a simple weekly focus area from recent analysed games

---

## Phase 6 — Focus Areas and Coaching Summary

### Goal

Give the player a simple, actionable improvement plan.

### Example Output

```text
This week's focus:
1. Check for loose pieces before every move
2. Slow down in equal middlegames
3. Review your games in the Sicilian Defence as Black
```

### MVP Approach

Generate from structured stats, not freeform AI first.

Inputs:

* most common mistake category
* highest average CPL opening
* phase with most blunders
* recent rating trend
* time control filter

### Later AI Layer

Once structured data is reliable, add an LLM layer that explains findings in friendlier language.

> The LLM should explain app-generated findings, not invent chess analysis from scratch.

### Explainability Rule

Prefer rationale-style coaching over engine-only statements.

Instead of:

```text
Best move was Nf3.
```

Prefer:

```text
Nf3 defended the fork square while developing a piece.
```

### Acceptance Criteria

* Profile page shows 1–3 focus areas
* Focus areas are based on recent analysed games
* Each focus area links to example games/moves

---

## Phase 7 — Habit Loop and Retention

### Goal

Make the app useful after every playing session.

### Features

* sync recent games
* show newly analysed games
* highlight worst move of the session
* show whether blunders/game improved
* show one focus area for next session

### Future Weekly Summary

```text
This week:
- You played 18 rapid games
- Your blunders per game dropped from 2.1 to 1.4
- Your best game was against Player123
- Your main issue remains hanging pieces in the middlegame
```

### Acceptance Criteria

* User has a reason to return after playing games
* Profile page shows recent progress, not just static stats
* App feels like a chess improvement journal

---

## Suggested Build Order

### Done

1. ✅ Public `/g/{share_code}` URLs
2. ✅ `?ply=N` deep linking
3. ✅ Hide UUIDs from normal UI
4. ✅ Game analysis page (keyboard nav, board orientation, move detail, share buttons)
5. ✅ `connected_accounts` + extended `games` metadata
6. ✅ `POST /api/v1/connected-accounts`, profile `/u/[username]`
7. ✅ `SyncChessComAccountJob`, `ImportExternalGameJob`, dedup, web + CLI sync
8. ✅ Profile stats API + UI (W/D/L, CPL, mistakes, sparklines, recent games, game-type filter)

### Next up

1. **Close the explain loop on game pages**
   - Key-moment cards in `/g/{share_code}` with played-vs-best and explanation text.
   - Cached deterministic LLM explanations per key moment.
2. **Add first heuristic tags**
   - Conservative rules for a small tag subset; show tag badges in analysis/profile surfaces.
3. **Add auth + ownership**
   - User login/session and profile claim/ownership model for connected accounts and games.
4. **Promote trends into dedicated views**
   - Move beyond profile-only aggregates to dashboard/`/patterns` style pages.
5. **Expand imports**
   - Lichess sync after Chess.com flow and ownership model are stable.
6. **Then coaching layers**
   - Focus areas + coaching summary (Phase 6) once tagging data is reliable.

---

## Open Questions

### Authentication

MVP: public username-based connected accounts (no login required).
Later: user accounts can claim/manage profiles.

### Chess.com vs Lichess

Chess.com public sync is **implemented** (`SyncChessComAccountJob`, profile + CLI). `connected_accounts.platform` remains ready for Lichess later.

### Analysis Volume

Web sync: newest **20** games. **Full archive:** `chess:sync-connected-account` without `--recent` ([ADMIN-GUIDE.md](./ADMIN-GUIDE.md)).

### Public vs Private Profiles

Local/dev only for now. Long term: allow private profiles once authentication is added.

### Accuracy Metric

Not initially. Use average CPL and mistake counts first. Add custom accuracy later if useful.

---

## Definition of Success

This feature branch is successful if the app can:

1. ✅ Import and analyse a chess game
2. ✅ Show it on a polished interactive board
3. ✅ Share the game via `/g/{share_code}`
4. ✅ Link to a specific position with `?ply=N`
5. ✅ Create a player profile from a Chess.com username (`/u/{username}`, `connected_accounts`)
6. ✅ Show that player's recent analysed games and sync more from Chess.com
7. ✅ Surface useful **aggregate** trends (stats + sparklines; game-type filter)

At that point, the project has moved from:

> I built a Stockfish wrapper.

To:

> I built a chess improvement platform that analyses player habits over time.
