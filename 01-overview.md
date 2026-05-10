# Chess Coach Journal — Product Overview

**Build status (May 2026):** The **evaluate + replay + share** loop and **Chess.com profile imports** are in the codebase. **Auth and onboarding** are complete — registration, login, onboarding gate (connect account before dashboard), settings page, and full user-scoped queries. **Key moments** are now selected, persisted, and rendered on `/g/{share_code}`. The **staged sync / analyse pipeline** is wired on the backend (sync imports metadata only; only the most-recent ~5 games per sync auto-analyse; on-demand `POST /api/v1/games/{id}/analyse` from the games list). **explain** (LLM), **club notes**, and the `pending`-aware game detail page are still ahead. See [00-index.md](./00-index.md).

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

## Connected Account Model

Each app account links one or more chess identities (Chess.com, Lichess usernames). These are **the user's own accounts** — not followed players or arbitrary third-party accounts. All imported games, coaching language, trends, and insights are personalised to the owner of those linked identities.

```
Matt Calvert
    ├── Chess.com: XanderCalvert
    └── Lichess: xandercalvert
```

The coaching voice is always second-person and owner-scoped:

> "You missed a tactic here."
> "You repeatedly ignore knight forks in rapid games."
> "Your London middlegames are improving."

Connected accounts operate on an **honour system** in MVP — users manually enter their usernames. No ownership verification is required initially. Verification via public-profile proof (e.g. adding a code to the Chess.com bio) is explicitly deferred to a later phase.

A "follow any player" or generic game-browser model is out of scope for current phases. This would introduce product ambiguity around coaching voice, ownership semantics, and visibility — and should only be considered after the self-coaching experience is mature.

## What the App Is Not
- A Chess.com or Lichess competitor (no live games, matchmaking, or community)
- A full opening database or training platform
- A commercial SaaS product in the MVP phase
- A social platform (no followers, profiles, or ratings to broadcast)
- A replacement for a human coach — it is a structured memory aid and analysis layer
- A generic game browser for watching arbitrary players

## Core User Story
> *As a chess player, I can paste a PGN, wait a minute, and see the three most important mistakes from my game explained in plain English, with the best move shown side by side.*

Everything else — trends, club feedback, dashboards, recommendations — is a layer on top of this core.
