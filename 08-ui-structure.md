# Chess Coach Journal — UI Structure

## As built (May 2026)

Rough mapping to this doc:

| Doc screen | In app today |
|------------|----------------|
| Registration | `/register` — name, email, password; redirects to `/onboarding` on first register |
| Login | `/login` — email/password; redirects to `/onboarding` if no accounts linked, else `/games` |
| Onboarding | `/onboarding` — required step: connect Chess.com or Lichess account before dashboard |
| Game Import | `/import` — PGN paste, submit; gated: requires auth + at least one connected account |
| Game List | `/games` — list with status; gated: requires auth + at least one connected account |
| Game Detail / Replay | `/g/{share_code}` (primary); `/games/{id}/analysis` still available |
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

### Game Import Page
**Purpose:** Accept a PGN and initiate analysis.

Components:
- Large text area for PGN paste
- PGN preview card once parsed: player names, date, opening, result, move count
- "Analyse" button — disabled until PGN is valid
- Error message area for invalid PGN
- Progress indicator after analysis is triggered (stages: *Parsing moves… Running engine… Generating explanations…*)

Empty state: Large text area with placeholder showing an example PGN snippet.

---

### Game List Page
**Purpose:** Browse all previously imported and analysed games.

Components:
- Table or card list: date, players, opening, result, accuracy, blunder count, status badge (Analysed / Pending / Failed)
- Filter bar: colour, opening, date range, result
- Sort controls: by date, accuracy, blunder count

---

### Game Detail / Replay Page
**Purpose:** The core screen — display a full game analysis.

Components:
- Game summary panel (top): players, date, opening, result, accuracy, blunder/mistake/inaccuracy counts, one-sentence summary
- Interactive chess board (centred, prominent)
- Move list panel (right): scrollable, colour-coded by severity
- Key moment cards (below board or right panel): top 3 moments with move number, tag badge, explanation preview
- Engine line below board: e.g. `Best: Rf1+ Kh7 Qg6#`
- Navigation controls: previous/next move, jump to key moment

Empty state (analysis pending): Spinner and progress message.

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

### First-Time User
1. Arrive at home page — clear headline explaining the product
2. Register with email and password
3. **Connect chess account** — onboarding step, not optional settings: "Connect your Chess.com or Lichess account to start analysing your games." User enters their username; app imports recent games automatically.
4. After first sync → redirected to dashboard/games list
5. Prompted to open first analysed game

### Paste PGN and Analyse
1. Navigate to Import page
2. Paste PGN string
3. App validates and shows preview (players, date, opening, move count)
4. Click "Analyse"
5. Progress indicator shows stages
6. On completion → redirected to game detail page

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
