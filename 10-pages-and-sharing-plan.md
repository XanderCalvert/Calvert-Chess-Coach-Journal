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

```text
Import Game
    ↓
Analyse Game
    ↓
Review Mistakes
    ↓
Identify Patterns
    ↓
Share Analysis
```

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

### Import Page (`/import`)

**Purpose**
- Paste PGN
- Upload PGN (later)
- Select colour played
- Submit game for analysis

**Inputs**
- PGN textarea
- Colour played
- Import source

### Games List (`/games`)

**Purpose**
- View imported games

**Columns**
- Opponent
- Result
- Opening
- Date
- Analysis status
- Mistake count

### Game Analysis Page (`/games/[id]`)

**Purpose**
- Main analysis experience (centrepiece page)

**Features**
- Interactive board
- Move list
- Evaluation graph
- Key mistakes
- Better move suggestions
- Human explanations
- Engine lines
- Accuracy summary

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

```text
Import PGN
    ↓
Parse moves
    ↓
Persist game
    ↓
Queue Stockfish analysis
    ↓
Store evaluations
    ↓
Identify mistakes
    ↓
Generate explanations
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

A polished chess improvement platform focused on:

- Understanding mistakes
- Recognising patterns
- Improving decision making
- Sharing analysis socially

Rather than simply displaying engine evaluations.
