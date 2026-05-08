# Chess Coach Journal — Product Overview

**Build status (May 2026):** The **evaluate + replay + share** loop and **Chess.com profile imports** are in the codebase; **explain** (LLM) and **club notes** pillars are still largely ahead. See [00-index.md](./00-index.md).

## App Name
Chess Coach Journal

## Target User
An amateur chess player (roughly 600–1400 Elo) who plays regularly — either online or at a local chess club — and wants to improve through structured self-study rather than raw repetition.

## Problem Being Solved
Most free chess analysis tools show engine lines and centipawn scores, but tell you very little about *why* a move was bad in terms a human can act on. After a game, a typical user sees a red blunder arrow and a best-move suggestion — they do not learn the underlying pattern, they do not know whether it is part of a recurring habit, and they have no guidance on what to study next.

**The gap is not engine power — it is explanation, curation, and memory.**

## Core Value Proposition
> *Analyse my chess games, explain my key mistakes in plain English, track my recurring weaknesses, and help me decide what to practise next.*

### Three Pillars
- **Explain, not just evaluate.** Engine evaluations are translated into plain-English explanations that describe the idea behind the position, not just the computer score.
- **Remember, not just report.** The app tracks mistakes across games so the user can see patterns — *"you keep missing backward rook moves"* — rather than re-discovering the same issue every session.
- **Integrate human feedback.** Notes and advice from a chess club coach can be recorded alongside engine analysis.

## What the App Is Not
- A Chess.com or Lichess competitor (no live games, matchmaking, or community)
- A full opening database or training platform
- A commercial SaaS product in the MVP phase
- A social platform (no followers, profiles, or ratings to broadcast)
- A replacement for a human coach — it is a structured memory aid and analysis layer

## Core User Story
> *As a chess player, I can paste a PGN, wait a minute, and see the three most important mistakes from my game explained in plain English, with the best move shown side by side.*

Everything else — trends, club feedback, dashboards, recommendations — is a layer on top of this core.
