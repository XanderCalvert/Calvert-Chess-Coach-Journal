# Chess Coach Journal — Build Roadmap

Tick `- [ ]` → `- [x]` as you complete items. In GitHub or many editors, checkboxes are clickable.

> **Repo snapshot (May 2026):** Full import-to-analysis pipeline is working end-to-end. PGN import (UI + API), Stockfish analysis via queued jobs, per-move centipawn evaluation, blunder/mistake/inaccuracy classification, interactive board viewer with move navigation and keyboard shortcuts, games list, public share links (`/g/{code}`) with deep-link ply support (`?ply=N`). Auth, LLM explanations, and trend tracking are still outstanding.

---

## Before Writing Any Feature Code

Get these working first, in order:

- [x] **1.** Project scaffolding: Next.js app, Laravel API, PostgreSQL schema, Docker Compose
- [x] **2.** chess.js integration — confirm PGN parsing works end-to-end
- [x] **3.** Stockfish integration — spawn process, send a FEN, receive a best move
- [ ] **4.** Basic auth — register, login, JWT session
- [x] **5.** Confirm the full pipeline works manually **before any UI polish**

---

## Recommended Build Order (from the design doc)

- [x] **1.** Get Stockfish running server-side and analyse a single FEN — this is the hardest infrastructure step
- [x] **2.** Parse a real PGN and evaluate every position; store the results
- [x] **3.** Identify the 3 worst moves by centipawn loss
- [ ] **4.** Write a prompt that produces a good explanation for one of those moments — iterate on it with real game data
- [x] **5.** Build the minimum UI to display the board position and explanation side by side
- [ ] **6.** **Only then** add auth, the database, the queue, trends, and the rest

---

## Detailed Build Order

### 1. Scaffold the project

- [x] Create the Next.js frontend, Laravel API, PostgreSQL database, and Docker Compose setup.
- [x] Add basic environment files, local run commands, and a health-check endpoint.
- [x] Exit criteria: both frontend and API run locally, and the API can connect to the database.

### 2. Prove PGN parsing

- [x] Add a minimal PGN paste/input flow.
- [x] Use `chess.js` to parse one real game and produce move-by-move FEN positions.
- [x] Exit criteria: a real PGN can be parsed without manual cleanup, and each move position can be inspected.

### 3. Prove Stockfish integration

- [x] Add a server-side Stockfish wrapper using UCI stdin/stdout.
- [x] Analyse one FEN and return best move plus evaluation.
- [x] Exit criteria: the API can submit a FEN and receive a reliable Stockfish result.

### 4. Analyse a full game

- [x] Run Stockfish across every position from the parsed PGN.
- [x] Calculate evaluation changes and centipawn loss per move.
- [x] Exit criteria: one full game produces stored move evaluations.

### 5. Select key moments

- [x] Identify the most important mistakes from the analysed game (blunders, mistakes, inaccuracies).
- [x] Store played move, best move, FEN, evaluation swing, and classification.
- [x] Exit criteria: the app can consistently produce classified move evaluations from a real game.

### 6. Add mistake tags

- [ ] Implement the first MVP heuristic tags from the taxonomy.
- [ ] Allow tags to be manually corrected later.
- [ ] Exit criteria: each key moment has an initial tag and enough data to explain why it was chosen.

### 7. Generate explanations

- [ ] Create the LLM prompt using deterministic board data, engine line, played move, best move, and tag.
- [ ] Keep temperature low and cache results.
- [ ] Exit criteria: each key moment gets a plain-English explanation that is useful to a club player.

### 8. Build the analysis UI

- [x] Add the minimum page for reviewing a game: board, move list, move navigation (click, keyboard, buttons).
- [x] Include loading, failed-analysis, and pending/running states.
- [x] Public share links (`/g/{share_code}`) and deep-link to specific position (`?ply=N`).
- [ ] Key moment cards with played move, best move, and explanation side by side.
- [ ] Exit criteria: a user can paste a PGN and review the key moments without developer help.

### 9. Add accounts and persistence

- [ ] Add authentication, user-owned games, saved key moments, notes, and coach agreement toggle.
- [ ] Exit criteria: a returning user can see previous analysed games and update their notes.

### 10. Add trends and dashboard

- [ ] Compute mistake frequency, accuracy trend, recent games, and simple study recommendation.
- [ ] Keep charts minimal until the core analysis loop feels strong.
- [ ] Exit criteria: the dashboard gives a useful next action after several analysed games.

### 11. Polish and deploy

- [ ] Improve responsive layout, error handling, README setup, and deployment configuration.
- [ ] Deploy a public demo with safe sample data.
- [ ] Exit criteria: the app is usable from a public URL and documented well enough to run locally.

---

## MVP Milestones

| # | Milestone | Deliverable |
|---|-----------|-------------|
| M1 | Foundation | Project scaffold, auth, database schema, PGN parse to Moves table |
| M2 | Engine | Stockfish worker, centipawn evaluation, classification, Key Moments selection |
| M3 | Heuristic Tags | Rule-based mistake tagging for 5 MVP tags |
| M4 | Explanations | LLM API integration, prompt template, explanation stored and displayed |
| M5 | Game UI | Analysis page with board, key moment cards, played vs best move view |
| M6 | Summary + Notes | Game summary generation, manual notes, coach agreement toggle |
| M7 | Trends MVP | Trend summary computed, simple trends page with table and chart |
| M8 | Dashboard | Dashboard with stat cards, recent games list, study recommendation |
| M9 | Polish | Error states, empty states, loading indicators, responsive fixes |
| M10 | Launch | Deploy to Fly.io / Railway, write README, document architecture |

**Progress (tick as you go):**

- [ ] **M1 — Foundation:** Project scaffold, auth, database schema, PGN parse to Moves table
- [x] **M2 — Engine:** Stockfish worker, centipawn evaluation, classification, move-level key moments
- [ ] **M3 — Heuristic Tags:** Rule-based mistake tagging for 5 MVP tags
- [ ] **M4 — Explanations:** LLM API integration, prompt template, explanation stored and displayed
- [ ] **M5 — Game UI:** Analysis page with board, key moment cards, played vs best move view *(board + move list done; key moment cards and explanations outstanding)*
- [ ] **M6 — Summary + Notes:** Game summary generation, manual notes, coach agreement toggle
- [ ] **M7 — Trends MVP:** Trend summary computed, simple trends page with table and chart
- [ ] **M8 — Dashboard:** Dashboard with stat cards, recent games list, study recommendation
- [ ] **M9 — Polish:** Error states, empty states, loading indicators, responsive fixes
- [ ] **M10 — Launch:** Deploy to Fly.io / Railway, write README, document architecture

---

## Phase 1 Milestones

| # | Milestone | Deliverable |
|---|-----------|-------------|
| P1-M1 | Import | Chess.com and Lichess API import with deduplication |
| P1-M2 | Recurring Mistakes | Detect recurring mistake tags across 20+ games |
| P1-M3 | Opening Awareness | ECO tracking, accuracy by opening |
| P1-M4 | Dashboard v2 | Trend comparison (last 5 vs previous 5 games) |
| P1-M5 | Explanation Depth | Rating preference, improved prompts |
| P1-M6 | Responsive Layout | Tablet and mobile support |

**Progress (tick as you go):**

- [ ] **P1-M1 — Import:** Chess.com and Lichess API import with deduplication
- [ ] **P1-M2 — Recurring Mistakes:** Detect recurring mistake tags across 20+ games
- [ ] **P1-M3 — Opening Awareness:** ECO tracking, accuracy by opening
- [ ] **P1-M4 — Dashboard v2:** Trend comparison (last 5 vs previous 5 games)
- [ ] **P1-M5 — Explanation Depth:** Rating preference, improved prompts
- [ ] **P1-M6 — Responsive Layout:** Tablet and mobile support

---

## Practical Advice

- **Use your own real games from the start.** Do not develop against synthetic test data — chess positions are too varied and explanations will feel wrong.
- **Spend disproportionate time on explanation quality.** A mediocre explanation that gets the idea right is worth ten technically correct but unhelpful engine lines.
- **Resist adding features** until the core loop (import → analyse → explain → review) is smooth and fast.
- **Treat every analysed game as a product test** — did you learn something useful? If not, fix the explanation before adding a new feature.
- **Keep the code public on GitHub from day one.** Commit regularly with meaningful messages — this is part of the portfolio.
