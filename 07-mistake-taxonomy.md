# Chess Coach Journal — Mistake Taxonomy

## Tag Reference

| Tag | Slug | Phase Hint | Description |
|-----|------|------------|-------------|
| Hanging Piece | `hanging-piece` | any | A piece is left on a square attacked more times than defended |
| Missed Tactic | `missed-tactic` | any | A tactical sequence (fork, pin, skewer, discovered attack) was available but not taken |
| Missed Capture | `missed-capture` | any | An opponent piece was en prise and was not captured |
| King Safety | `king-safety` | middlegame | The castled king's pawn shelter is broken, or the king was left on an open file/rank |
| Poor Development | `poor-development` | opening | A piece moved twice when all pieces have not yet left their starting squares |
| Bad Trade | `bad-trade` | any | A piece of higher value was exchanged for lower value with no positional compensation |
| Pawn Weakness | `pawn-weakness` | any | A pawn push created an isolated, doubled, or backward pawn without adequate compensation |
| Opening Principle Issue | `opening-principle` | opening | Centre control ceded, a bishop/knight undeveloped, or king uncastled past move 15 |
| Endgame Technique | `endgame-technique` | endgame | King not activated, passed pawn not advanced, or basic theoretical position mishandled |
| Overlooked Opponent Threat | `overlooked-threat` | any | Opponent's previous move had a clear threat (check, capture, promotion) that was not addressed |
| Time Pressure Blunder | `time-pressure` | any | Flagged manually by user or inferred from clock data in PGN |
| Positional Mistake | `positional-mistake` | any | Catch-all for positional errors (bad piece placement, wrong plan) not fitting another category |

---

## MVP Heuristic Detection

Only these five tags are auto-detected in MVP. All others are assigned by the LLM explanation prompt or manually by the user.

### Hanging Piece
- After the played move, check each piece for the moving side
- If attackers > defenders for any piece that can be captured immediately → tag as `hanging-piece`

### Missed Capture
- Engine best move is a capture (UCI move captures an occupied square)
- Played move was not a capture
- → tag as `missed-capture`

### King Safety
- Check whether pawns in front of the castled king were moved in the last 3 moves
- Or: a rook that was defending the castled king was traded off
- → tag as `king-safety`

### Poor Development
- Move number ≤ 15 (opening phase)
- The same piece moved twice, AND there is still at least one piece on its starting square for the moving side
- → tag as `poor-development`

### Overlooked Opponent Threat
- On the opponent's previous move, they created a check, a direct capture opportunity, or a promotion threat
- The played move does not address this threat
- → tag as `overlooked-threat`

### Default
- All other key moments → `missed-tactic`
- LLM explanation prompt should attempt to identify a more specific sub-type and note it in the explanation text

---

## Phase 1 Additions

- `bad-trade`, `pawn-weakness`, and `opening-principle` to be detected heuristically or via improved LLM prompting
- LLM-assigned tags to be surfaced in the UI for user review and override
