# Chess Coach Journal — Explanation System

## Design Goals

- Explanations must be **factually grounded** in the position — no hallucinated moves or pieces
- Describe the **idea or principle**, not just restate the engine score
- Readable by a club-level player without jargon overload
- **2–4 sentences maximum** in MVP

---

## Context Passed to the LLM (Every Request)

Assembled from the database before any LLM call:

- FEN string of the position before the blunder
- The move played (SAN)
- The engine best move (SAN)
- The centipawn loss
- The classification (blunder, mistake, inaccuracy)
- The move number and colour to move
- The game phase (opening, middlegame, endgame)
- The mistake tag assigned heuristically
- Material counts for both sides (pieces remaining)
- Optional: user's stated rating range

---

## Hallucination Reduction

- Never ask the LLM to describe moves it cannot verify — provide all move data explicitly
- Instruct the LLM to refer only to the information provided and not to invent variations
- Validate output: check that the played move and best move mentioned match the input data
- Use **temperature 0.3 or below**
- System instruction: *"Do not mention any moves, pieces, or squares not present in the provided data"*

---

## Prompt Template (Key Moment)

```
System:
You are a chess coach explaining a mistake to a club-level player.
You will be given the exact position details.
Explain only what is provided. Do not invent moves or pieces.
Keep your explanation to 2–4 sentences.

User:
The player was White on move 24 in the middlegame.
They played: Nxd5
The engine best move was: Rf1
Centipawn loss: 187 (Blunder)
Mistake type: Hanging piece
Position: White has a rook on e1, a knight on f3, and a queen on d2.
           Black has a rook on f8 pointing at f2.

Explain in plain English why Nxd5 was a blunder and why Rf1 was better.
```

### Example Output

> Playing Nxd5 won a pawn but left the f2 square completely undefended. Black's rook on f8 could immediately capture on f2, forking your king and winning material back with interest. The engine suggests Rf1 first, shoring up f2 and removing the threat before going pawn-hunting. In chess, defending your king's weak squares usually takes priority over winning material.

---

## Explanation Depth Variants

| Depth | Approach |
|-------|----------|
| **Beginner** | Very plain English. Name the tactic explicitly ("this is called a fork"). Avoid all notation. Focus on the outcome ("you would lose your rook"). |
| **Club player** *(default)* | Light use of chess terms. Brief explanation of the idea. Show the key move in algebraic notation. |
| **Experienced** | Can use terms like "prophylaxis", "outpost", "zwischenzug". Shorter and more precise. Engine line may be included. |

---

## Validation Checklist (Post-Generation)

- [ ] Played move mentioned in explanation matches `san` from input
- [ ] Best move mentioned matches `best_move_san` from input
- [ ] No square or piece references that don't appear in the position data
- [ ] Explanation is 2–4 sentences
- [ ] No engine score mentioned verbatim (should use natural language instead)
