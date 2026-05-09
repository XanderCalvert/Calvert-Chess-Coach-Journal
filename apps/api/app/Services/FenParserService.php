<?php

namespace App\Services;

/**
 * Lightweight FEN parsing. Exposes board state only — no analysis logic.
 */
class FenParserService
{
    private array $board = [];   // square => piece char (uppercase=white, lowercase=black)
    private string $sideToMove = 'w';
    private string $castling = '-';
    private string $enPassant = '-';
    private int $halfMove = 0;
    private int $fullMove = 1;

    public function parse(string $fen): static
    {
        $parts = explode(' ', trim($fen));

        $this->board      = $this->parsePlacement($parts[0] ?? '');
        $this->sideToMove = $parts[1] ?? 'w';
        $this->castling   = $parts[2] ?? '-';
        $this->enPassant  = $parts[3] ?? '-';
        $this->halfMove   = (int) ($parts[4] ?? 0);
        $this->fullMove   = (int) ($parts[5] ?? 1);

        return $this;
    }

    public function getPieceAt(string $square): ?string
    {
        return $this->board[$square] ?? null;
    }

    /** ['P'=>n, 'N'=>n, ...] for white (uppercase) and black (lowercase). */
    public function getPieceCount(): array
    {
        $counts = [];
        foreach ($this->board as $piece) {
            $counts[$piece] = ($counts[$piece] ?? 0) + 1;
        }
        return $counts;
    }

    /** Files (a–h) that have no pawns of either colour. */
    public function getOpenFiles(): array
    {
        $filesWithPawns = [];
        foreach ($this->board as $square => $piece) {
            if (strtolower($piece) === 'p') {
                $filesWithPawns[$square[0]] = true;
            }
        }
        $open = [];
        foreach (range('a', 'h') as $file) {
            if (!isset($filesWithPawns[$file])) {
                $open[] = $file;
            }
        }
        return $open;
    }

    /** Returns the square of the king for the given side ('w' or 'b'). */
    public function getKingSquare(string $side): ?string
    {
        $king = $side === 'w' ? 'K' : 'k';
        foreach ($this->board as $square => $piece) {
            if ($piece === $king) {
                return $square;
            }
        }
        return null;
    }

    public function getSideToMove(): string
    {
        return $this->sideToMove;
    }

    public function getCastlingRights(): string
    {
        return $this->castling;
    }

    public function getEnPassantSquare(): string
    {
        return $this->enPassant;
    }

    public function getBoard(): array
    {
        return $this->board;
    }

    // -------------------------------------------------------------------------

    private function parsePlacement(string $placement): array
    {
        $board = [];
        $rank = 8;
        foreach (explode('/', $placement) as $rankStr) {
            $file = 0;
            foreach (str_split($rankStr) as $ch) {
                if (is_numeric($ch)) {
                    $file += (int) $ch;
                } else {
                    $square = chr(ord('a') + $file) . $rank;
                    $board[$square] = $ch;
                    $file++;
                }
            }
            $rank--;
        }
        return $board;
    }
}
