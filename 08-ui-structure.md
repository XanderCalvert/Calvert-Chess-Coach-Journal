# Chess Coach Journal — UI Structure

## As built (May 2026)

Rough mapping to this doc:

| Doc screen | In app today |
|------------|----------------|
| Registration | `/register` — name, email, password; redirects to `/onboarding` on first register |
| Login | `/login` — email/password; redirects to `/onboarding` if no accounts linked, else `/games` |
| Onboarding | `/onboarding` — required step: connect Chess.com or Lichess account before dashboard |
| Manual PGN Import | `/import` — paste flow; **secondary** path. Should be reframed as "Import PGN manually" once the staged pipeline lands |
| Game List | `/games` — primary post-onboarding surface. Should grow into "Your chess accounts" + "Recent games" with per-row analysis state and inline "Analyse this game" CTA |
| Game Detail / Replay | `/g/{share_code}` (primary); `/games/{id}/analysis` still available. Currently assumes analysis exists — needs a `pending`-aware variant once sync stops auto-analysing every game |
| Settings | `/settings` — profile (name, email) + "Your chess accounts" (add/remove linked accounts) |
| Dashboard | Not a dedicated page yet |
| Trends | Not `/patterns`; **profile** `/u/{username}` shows aggregate trends for a linked Chess.com account |
| Key moment cards / explanations | Board + move detail; LLM explanation cards not wired |

Layout: **Nav** component drives top/side navigation (not necessarily a 220px left sidebar as specified below). Settings link is in Nav.

---

## Layout Principles

- Desktop-first; responsive for tablet/mobile from Phase 1
- Persistent **left sidebar** (220px) with navigation on all screens
- Fluid main content area
- Right panels for contextual detail on analysis and game detail screens

---

## Screens

### Dashboard
**Purpose:** Quick overview of recent activity, current performance, and the most important next action.

Components:
- Stat cards: games analysed, average accuracy (last 10), most common mistake type, active study task
- Recent games list: last 5 games with date, result, accuracy badge, blunder count
- Trend sparkline: small accuracy chart for last 10 games
- Top recommendation card with CTA button

Empty state: Prompt to import the first game with a short explanation of what happens after analysis.

---

### Manual PGN Import Page
**Purpose:** Accept a one-off PGN and create the game record. Analysis is **opt-in**, not automatic.

Reframing note: this page is the **secondary** import path. The primary import is connected-account sync. Use cases for manual PGN paste:

- OTB games
- club games
- training positions
- manually collected PGNs
- games from unsupported sources

Rename the page CTA from a generic "Import game" to **"Import PGN manually"** or **"Add one-off PGN"** so users understand it's the side door, not the front door.

Components:
- Large text area for PGN paste
- PGN preview card once parsed: player names, date, opening, result, move count
- Primary action: "Add to my games" — creates the game in `pending` state without queuing analysis
- Secondary action: "Add and analyse now" — same as above plus dispatches `AnalyseGameJob`
- Error message area for invalid PGN

Empty state: Large text area with placeholder showing an example PGN snippet, plus a link reminding the user that the main flow is to sync their Chess.com / Lichess account on `/games`.

---

### Game List Page (`/games`) — primary surface
**Purpose:** The main entry point post-onboarding. Browse synced games, choose what to analyse, and reach individual game pages. The selection action is **"select a game to analyse"**, not "import a PGN".

Top section — **Your chess accounts**:
- One row per linked account (e.g. `Chess.com: XanderCalvert [Sync]`, `Lichess: username [Sync]`)
- Last synced timestamp + sync status per account
- "Add chess account" link → `/settings`
- Secondary link: **"Import PGN manually"** → manual PGN page

Middle section — **Recent games**:
- Filter bar: source (All / Chess.com / Lichess), colour, opening, date range, result, **analysis status** (All / Pending / Analysed / Failed)
- Sort controls: by date, accuracy, blunder count

Per-row content:
- Date, players, opening, result, time control
- **Analysis status badge** — `Pending` / `Queued` / `Analysing` / `Analysed` / `Failed`
- For `analysed` rows: accuracy, blunder count
- For `pending` / `failed` rows: an inline **"Analyse this game"** button that hits `POST /api/games/{id}/analyse`

Empty state (no synced games yet): Prompt to sync the user's connected account, with manual PGN import as a side-door secondary action.

---

### Game Detail / Replay Page
**Purpose:** The core screen. A game page **always exists once imported**, even before analysis. The page adapts to `analysis_status`.

Top header (always visible):
- Game summary: players, date, opening, result, time control
- **Analysis state badge** with last-updated timestamp
- For `pending` / `failed` games: prominent **"Analyse this game"** CTA

State: `pending` (imported but not analysed)
- Interactive chess board with full replay
- Move list panel (right): scrollable, plain SAN — no severity colouring yet
- Navigation controls: previous/next move
- Coaching / evaluation / key-moment panels are **locked** with an explanatory placeholder ("Analyse this game to unlock evaluations, key moments, and coaching.")

State: `queued` / `analysing`
- Same as `pending` plus a progress indicator on the locked panels (stages: *Queued… Running engine… Generating coaching…*)
- Polling refreshes the page once status flips to `analysed`

State: `analysed`
- Full experience: Stockfish evaluations, move classifications, key moments, deterministic coaching, played-vs-best, engine line
- Move list colour-coded by severity
- Key moment cards (below board or right panel): top 3 moments with move number, tag badge, explanation preview
- Trend aggregation contributes this game to the user's coaching surfaces

State: `failed`
- Banner explaining the failure plus a **"Retry analysis"** button
- Replay still works exactly as in `pending`

The principle: **imported PGNs already have value before engine analysis.** The page should never look broken when analysis hasn't run.

---

### Key Moment Detail Panel
**Purpose:** Deep-dive on a single mistake.

Components:
- Position board showing the played move
- Alternate board or arrow overlay showing the best move
- Explanation text (2–4 sentences)
- Mistake type badge
- Centipawn loss figure
- Engine line
- Notes section with club feedback toggle

---

### Trends Page
**Purpose:** Show improvement (or lack thereof) over time.

Components:
- Accuracy line chart over last N games
- Mistake type bar chart: frequency of each tag over the selected period
- Game phase breakdown: which phase contains most mistakes
- Filters: White / Black, last 10 / 20 / all games
- Plain-English summary paragraph generated from trend data

Empty state: Requires at least 3 analysed games to display meaningful trends.

---

### Notes / Club Feedback Page
**Purpose:** View and manage all human notes added across games.

Components:
- List of all notes with game reference, move number, date added
- Filter by agreed / disagreed with engine
- Ability to convert a note into a study task

---

### Settings Page
**Purpose:** Configure the app and account.

Components:
- User profile: name, email, approximate rating (optional)
- **Chess accounts:** list of linked Chess.com / Lichess identities with add/remove controls — framed as "Your chess accounts", not "Integrations"
- Explanation depth preference: Beginner / Club player / Experienced
- Stockfish depth setting
- API key management (if using self-hosted LLM or third-party)
- Account deletion and data export

---

## User Flows

### First-Time User (primary flow)
1. Arrive at home page — clear headline explaining the product
2. Register with email and password
3. **Connect chess account** — onboarding step, not optional settings: "Connect your Chess.com or Lichess account to start analysing your games." User enters their username; app imports recent games as **metadata only** (fast).
4. The most-recent small subset (MVP target: 5 games) is auto-queued for Stockfish analysis so coaching has immediate signal.
5. User lands on `/games` with all synced games already browsable (`pending` for the bulk, a few `analysing` / `analysed`).
6. User picks a meaningful game (a recent loss, a specific opening) and clicks **"Analyse this game"** if it's not already analysed.

### Habit loop (returning user)
1. Sync recent games from `/games` (one click per linked account).
2. New games appear immediately as `pending`; the recent subset auto-analyses.
3. User reviews key games intentionally — analysing more games on demand as they choose.
4. Coaching / trend quality improves over time as the analysed pool grows.

### Manual PGN paste (secondary path)
1. From `/games`, click "Import PGN manually".
2. Paste PGN string.
3. App validates and shows preview (players, date, opening, move count).
4. Click **"Add to my games"** (game stored as `pending`) or **"Add and analyse now"** (game stored + `AnalyseGameJob` queued).
5. Redirected to the game detail page, which works pre-analysis (board replay + metadata).

### Review Key Moments
1. Open previously analysed game
2. Game summary shown at top
3. Three key moment cards displayed with move number, type badge, one-line preview
4. Click to expand → full board view, explanation, engine line
5. Navigate to next / previous key moment

### Add Chess Club Feedback
1. On any key moment panel, click "Add Club Note"
2. Text area appears for freetext notes
3. Toggle: coach agreed / disagreed with engine
4. Save — note appears immediately below engine explanation
5. Included in trend data and visible from Trends page

### View Trends
1. Navigate to Trends page
2. Accuracy line chart for last 10 games
3. Mistake type breakdown (bar chart or table for MVP)
4. Filter by colour or time period
5. Plain-English summary paragraph shown

### Study Recommendation
1. Visit Recommendations section
2. Top recommendation shown based on most frequent recent mistake tag
3. Includes: theme, reason, how to study it, links
4. User can mark as "in progress" or "done"
5. Done recommendations cycle out after next analysis
