<?php

namespace App\Services;

/**
 * Board-level queries built on top of FenParserService.
 * No move generation — heuristics only.
 */
class BoardAnalysisService
{
    // Piece values used for material delta and fork detection.
    private const PIECE_VALUES = [
        'P' => 1, 'N' => 3, 'B' => 3, 'R' => 5, 'Q' => 9, 'K' => 0,
        'p' => 1, 'n' => 3, 'b' => 3, 'r' => 5, 'q' => 9, 'k' => 0,
    ];

    public function __construct(private FenParserService $parser) {}

    /**
     * Returns material delta for white from fen_before to fen_after.
     * Positive = white gained material, negative = black gained.
     */
    public function getMaterialDelta(string $fenBefore, string $fenAfter): int
    {
        $before = (new FenParserService())->parse($fenBefore)->getPieceCount();
        $after  = (new FenParserService())->parse($fenAfter)->getPieceCount();

        $delta = 0;
        foreach (self::PIECE_VALUES as $piece => $value) {
            $diff = ($after[$piece] ?? 0) - ($before[$piece] ?? 0);
            $delta += $diff * ($piece === strtoupper($piece) ? $value : -$value);
        }
        return $delta;
    }

    /**
     * Returns the piece char that moved, based on UCI and fen_before.
     */
    public function getPieceMoved(string $uci, string $fenBefore): ?string
    {
        $from = substr($uci, 0, 2);
        return (new FenParserService())->parse($fenBefore)->getPieceAt($from);
    }

    /**
     * Target square of a UCI move (e.g. "e2e4" → "e4").
     */
    public function getTargetSquare(string $uci): string
    {
        return substr($uci, 2, 2);
    }

    /**
     * Source square of a UCI move (e.g. "e2e4" → "e2").
     */
    public function getSourceSquare(string $uci): string
    {
        return substr($uci, 0, 2);
    }

    /**
     * Returns an array of squares attacked by knight moves from $square.
     */
    public function getKnightAttacks(string $square): array
    {
        $file = ord($square[0]) - ord('a');  // 0–7
        $rank = (int) $square[1] - 1;        // 0–7

        $offsets = [[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]];
        $squares = [];
        foreach ($offsets as [$df, $dr]) {
            $f = $file + $df;
            $r = $rank + $dr;
            if ($f >= 0 && $f <= 7 && $r >= 0 && $r <= 7) {
                $squares[] = chr(ord('a') + $f) . ($r + 1);
            }
        }
        return $squares;
    }

    /**
     * Squares attacked by a bishop/queen/rook from $square along a ray direction.
     * Returns all squares up to (and including) the first occupied square.
     */
    public function getRaySquares(string $square, int $df, int $dr, array $board): array
    {
        $file = ord($square[0]) - ord('a');
        $rank = (int) $square[1] - 1;

        $squares = [];
        $f = $file + $df;
        $r = $rank + $dr;
        while ($f >= 0 && $f <= 7 && $r >= 0 && $r <= 7) {
            $sq = chr(ord('a') + $f) . ($r + 1);
            $squares[] = $sq;
            if (isset($board[$sq])) {
                break; // blocked
            }
            $f += $df;
            $r += $dr;
        }
        return $squares;
    }

    /**
     * Returns the value of a piece char (0 for kings/unknown).
     */
    public function pieceValue(string $piece): int
    {
        return self::PIECE_VALUES[$piece] ?? 0;
    }

    /**
     * Returns all squares occupied by pieces of the given side ('w' or 'b').
     */
    public function getPieceSquares(string $side, array $board): array
    {
        $squares = [];
        foreach ($board as $sq => $piece) {
            $isWhite = $piece === strtoupper($piece);
            if (($side === 'w' && $isWhite) || ($side === 'b' && !$isWhite)) {
                $squares[$sq] = $piece;
            }
        }
        return $squares;
    }
}
