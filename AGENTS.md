# Agent Guide

## Project Context

Chess Coach Journal is a full-stack app for analysing a user's own chess games, explaining the most important mistakes in plain English, and tracking recurring weaknesses over time.

Planned stack:
- Next.js App Router frontend
- Laravel API backend
- PostgreSQL database
- Stockfish analysis worker
- OpenAI-powered explanation worker

## Working Principles

- Read the existing planning docs before making architectural decisions.
- Keep the MVP focused on the core loop: paste PGN, analyse, explain the 3 key moments, review notes and trends.
- Do not build Chess.com, Lichess, social, opening database, or training-platform features unless explicitly requested.
- Prefer existing project decisions over introducing new frameworks or patterns.
- Delegate chess rules and PGN/FEN handling to proven libraries such as `chess.js`; do not reimplement chess logic.
- Treat explanation quality as a first-class feature, not a polish task.

## Implementation Priorities

- Prove Stockfish and PGN parsing end-to-end before UI polish.
- Keep background analysis asynchronous and observable.
- Cache LLM explanations and avoid regenerating them without a user action.
- Use low-temperature prompts with deterministic position data to reduce hallucinations.
- Preserve the target MVP performance goal: full analysis in about 60 seconds.

## Code Expectations

- Keep changes small, direct, and aligned with the documentation.
- Add tests around shared logic, parsing, analysis selection, tagging, and API contracts.
- When adding or changing API tests, also update `apps/api/tests/Tests.md` so the suite reference stays current.
- Use clear names and simple data flow over broad abstractions.
- Document setup, run commands, and non-obvious decisions as the codebase grows.
