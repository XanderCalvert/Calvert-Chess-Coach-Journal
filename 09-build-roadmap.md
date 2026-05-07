# Chess Coach Journal — Build Roadmap

## Before Writing Any Feature Code

Get these working first, in order:

1. Project scaffolding: Next.js app, Laravel API, PostgreSQL schema, Docker Compose
2. chess.js integration — confirm PGN parsing works end-to-end
3. Stockfish integration — spawn process, send a FEN, receive a best move
4. Basic auth — register, login, JWT session
5. Confirm the full pipeline works manually **before any UI polish**

---

## Recommended Build Order (from the design doc)

1. Get Stockfish running server-side and analyse a single FEN — this is the hardest infrastructure step
2. Parse a real PGN and evaluate every position; store the results
3. Identify the 3 worst moves by centipawn loss
4. Write a prompt that produces a good explanation for one of those moments — iterate on it with real game data
5. Build the minimum UI to display the board position and explanation side by side
6. **Only then** add auth, the database, the queue, trends, and the rest

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

---

## Practical Advice

- **Use your own real games from the start.** Do not develop against synthetic test data — chess positions are too varied and explanations will feel wrong.
- **Spend disproportionate time on explanation quality.** A mediocre explanation that gets the idea right is worth ten technically correct but unhelpful engine lines.
- **Resist adding features** until the core loop (import → analyse → explain → review) is smooth and fast.
- **Treat every analysed game as a product test** — did you learn something useful? If not, fix the explanation before adding a new feature.
- **Keep the code public on GitHub from day one.** Commit regularly with meaningful messages — this is part of the portfolio.
