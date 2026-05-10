# Chess Coach Journal — Pages & Sharing Plan

## Core Product Vision

A chess improvement platform focused on:

- Importing games
- Analysing mistakes
- Explaining why moves are mistakes
- Identifying recurring patterns
- Sharing analysis publicly

The emphasis is educational feedback rather than raw engine lines.

---

## Core User Flow

The product loop is staged — sync is cheap and runs over the whole archive, analysis is selective and run for a small recent subset automatically (MVP: 5 most-recent games) plus anything the user explicitly chooses, and coaching depth grows progressively as the analysed pool grows:

```text
Register / login
    ↓
Connect chess identity (Chess.com / Lichess)
    ↓
Sync games (metadata only — fast)
    ↓
Recent subset auto-analysed
    ↓
User reviews key games
    ↓
User analyses more games intentionally
    ↓
Coaching / trend quality improves over time
    ↓
User keeps syncing regularly
```

Manual PGN paste remains as a **secondary** "Import PGN manually" path (OTB games, club games, training positions, manually collected PGNs, games from unsupported sources).

---

## MVP Pages

### Homepage (`/`)

**Purpose**
- Explain the product
- Show example analysis
- Drive users to import a game

**Potential sections**
- Hero section
- Example mistake explanation
- Features
- Shareable analysis examples
- Import CTA

### Manual PGN Import Page (`/import`) — secondary path

**Purpose**
- One-off PGN paste for OTB / club / training / unsupported-source games
- Reframed as **"Import PGN manually"** / **"Add one-off PGN"** — explicitly *not* the primary entry point

**Inputs**
- PGN textarea
- Colour played
- Import source

**Behaviour**
- Default action: "Add to my games" — creates the game with `analysis_status = pending`, no `AnalyseGameJob` queued
- Secondary action: "Add and analyse now" — same plus dispatches analysis
- After save → redirect to game detail page (which works pre-analysis)

### Games List (`/games`) — primary surface

**Purpose**
- The main entry point post-onboarding
- Browse synced games and **choose a game to analyse**

**Top section — Your chess accounts**
```text
Chess.com: XanderCalvert   [Sync]
Lichess:   username        [Sync]
```
Per-account: last synced timestamp, sync status. Side links: "Add chess account", "Import PGN manually".

**Recent games**
- Filter: source (All / Chess.com / Lichess), colour, opening, date range, result, **analysis status**
- Sort: by date, accuracy, blunder count

**Per-row content**
- Date, opponent, result, opening, time control
- Analysis status badge: `Pending` / `Queued` / `Analysing` / `Analysed` / `Failed`
- For `analysed` rows: accuracy, mistake/blunder counts
- For `pending` / `failed` rows: inline **"Analyse this game"** button → `POST /api/games/{id}/analyse`

The primary interaction on this page is **"select a game to analyse"**, not "import a PGN".

### Game Analysis Page (`/games/[id]`)

**Purpose**
- Main analysis experience (centrepiece page)
- Always available once a game is imported, even before analysis

**Behaviour by `analysis_status`**

`pending` (imported, not analysed):
- Interactive board with full replay
- Opening / result / time control metadata visible
- Move list (plain SAN, no severity colouring)
- Prominent **"Analyse this game"** CTA
- Coaching / evaluation / key-moment panels locked with a "Analyse this game to unlock evaluations, key moments, and coaching." placeholder

`queued` / `analysing`:
- Same as pending plus a progress indicator on the locked panels (Queued → Running engine → Generating coaching)
- Polls and auto-refreshes when status flips to `analysed`

`analysed`:
- Stockfish evaluations
- Move classifications
- Key moments
- Deterministic coaching
- Trend aggregation contributions
- Played-vs-best, engine line, plain-English explanations, accuracy summary

`failed`:
- Banner explaining the failure plus a **"Retry analysis"** button
- Replay still works as in `pending`

The principle: **imported PGNs already have value before engine analysis runs.** The page should be useful in every state, not blank until analysis completes.

### Review / Training Mode (`/games/[id]/review`)

**Purpose**
- Guided training experience

**Features**
- Step through mistakes
- "What would you play here?"
- Reveal better move
- Explanation after answer

### Pattern Tracking (`/patterns`)

**Purpose**
- Identify recurring weaknesses

**Potential pattern categories**
- Hanging pieces
- Missed tactics
- Poor king safety
- Opening issues
- Time pressure blunders
- Endgame inaccuracies

### Profile (`/profile`)

**Purpose**
- User preferences and account setup

**Potential settings**
- Explanation depth
- Preferred colour
- Theme
- Chess.com username
- Lichess username

### About / Build Page (`/about`)

**Purpose**
- Explain the project
- Highlight portfolio / CV value
- Show technical architecture
- Describe product philosophy

---

## Sharing System

### Goal

Allow users to share public analysis pages.

Examples:
- `/share/morphy-vs-brunswick-f8k29apqmx`
- `/share/kasparov-vs-deep-blue-x7a2p91l`
- `/share/carlsen-vs-nepomniachtchi-q9dk4m2w`

### URL Structure

#### Internal IDs

Use UUID/ULID for internal records. Never expose these publicly.

Example:
- `019e0697-4a18-73a1-b431-8105691d21b3`

#### Public Share IDs

Use:
- Short random string
- URL-safe characters
- Globally unique values

Example:
- `f8k29apqmx`

#### Pretty Slugs

Generated from player names.

Example:
- `morphy-vs-brunswick`

Final URL:
- `/share/morphy-vs-brunswick-f8k29apqmx`

### Important Design Principle

Only the short ID is authoritative. The slug is cosmetic.

This means:
- `/share/morphy-vs-brunswick-f8k29apqmx`
- `/share/test-test-f8k29apqmx`

Both resolve to the same game.

Benefits:
- Slug changes
- Player name corrections
- SEO improvements
- Localisation
- Backwards-compatible URLs

### Database Structure (Games Table)

Potential fields:
- `id`
- `share_id`
- `share_slug`
- `is_public`
- `source`
- `source_game_id`
- `source_url`

### Import Sources

Potential values:
- `pgn`
- `manual`
- `chess_com`
- `lichess`

### Share Route Logic

Extract the final segment after the last dash:

```php
preg_match('/([a-z0-9]+)$/', $slug, $matches);
$shareId = $matches[1];
```

Then query:

```php
Game::where('share_id', $shareId)->firstOrFail();
```

---

## Future Features

### Public Analysis Pages

Allow:
- Shared game review
- Interactive replay
- Mistake explanations
- Engine insights

Potentially disable:
- Private notes
- Draft analysis
- Internal metadata

### Chess.com Integration

**Shipped (May 2026):** username-based connected profile (`/u/{username}`), archive sync (recent window in UI; full history via CLI), rating fields on account, deduped imports. See [11-profile-plan.md](./11-profile-plan.md), [ADMIN-GUIDE.md](./ADMIN-GUIDE.md).

Still potential:
- Import by single-game URL
- Scheduled / automatic sync

### Lichess Integration

Potential features:
- Public API import
- Automatic PGN parsing
- Puzzle generation

---

## Technical Direction

### Frontend
- Next.js
- Tailwind
- React Chessboard
- Zustand or TanStack Query

### Backend
- Laravel
- PostgreSQL
- Queue-based analysis pipeline
- Stockfish integration

---

## Planned Analysis Pipeline

The pipeline is **staged** — sync, analyse, and coach are independent stages. The expensive Stockfish work is gated behind either the recent-subset auto rule or an explicit user action. Full detail in [05-analysis-pipeline.md](./05-analysis-pipeline.md).

```text
Stage A — Sync / Import (cheap, every game)
    Sync connected account or paste PGN
        ↓
    Parse moves
        ↓
    Persist game (analysis_status = pending)
        ↓
    Recent subset (MVP: 5 newest) auto-queued for analysis

Stage B — Analyse (expensive, selective)
    Recent-subset rule OR POST /api/games/{id}/analyse
        ↓
    Stockfish per move
        ↓
    Store evaluations + classifications (analysis_status = analysed)

Stage C — Coach (derived from analysed games)
    Identify mistakes / select key moments
        ↓
    Generate explanations
        ↓
    Update trends / coaching surfaces
        ↓
    Expose to frontend
```

---

## Current progress (May 2026)

### Completed
- [x] Next.js ↔ Laravel (BFF under `apps/web/app/api/...`)
- [x] PostgreSQL + game/move persistence, queues
- [x] PGN import (`/import`, API `POST /api/v1/games`)
- [x] Stockfish analysis jobs; classifications; move-level data
- [x] Game analysis experience: `/g/{share_code}`, `?ply=N`, board + move list + move detail
- [x] Games list `/games`
- [x] **Public share path:** `/g/{code}` (short **share_code**); *this doc’s `/share/{slug}-{id}` pattern is not the implemented contract*
- [x] **Chess.com profile:** `/u/{username}` — connected account, sync, imported games, analysis trends, game-type filter
- [x] Operator CLI: `chess:sync-connected-account` (see [ADMIN-GUIDE.md](./ADMIN-GUIDE.md))

### Still open (vs this doc)
- [ ] Homepage polish as specified (hero, example analysis)
- [ ] `/games/[id]` as canonical ID route in nav (UUID route exists under `/games/.../analysis`)
- [ ] `/games/[id]/review` training mode
- [ ] `/patterns` pattern tracking page
- [ ] `/profile` settings (explanation depth, linked accounts UI beyond profile page)
- [ ] `/about` build page
- [ ] Lichess integration

---

## Long-Term Vision

A **quantified-self platform for chess improvement**, focused on:

- Understanding mistakes
- Recognising patterns
- Improving decision making
- Sharing analysis socially

Rather than simply being a generic Stockfish frontend. The central product value the user should feel is:

> "This understands my chess."

The staged sync / analyse / coach pipeline exists in service of that — sync is cheap and instant, analysis is intentional and reserved for games the user actually wants insight on, and coaching depth grows as the analysed pool grows. This also maps cleanly onto a future free/premium split where the cheap layer (sync + browse + limited analysis) is free and the expensive value layer (unlimited analysis, advanced coaching trends, AI explanations) is premium.
