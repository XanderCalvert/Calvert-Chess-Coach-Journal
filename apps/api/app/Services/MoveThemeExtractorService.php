<?php

namespace App\Services;

/**
 * Deterministic theme labeling from FEN + move data.
 * All themes in Sprint 2 are provable from board state, not inferred.
 */
class MoveThemeExtractorService
{
    public function __construct(
        private FenParserService $fenParser,
        private BoardAnalysisService $boardAnalysis,
    ) {}

    /**
     * @return string[] Theme labels for this move.
     */
    public function extract(
        string $fenBefore,
        string $fenAfter,
        string $uci,
        int    $moveNumber,
        string $side,        // 'white' | 'black'
    ): array {
        $themes = [];

        $piece  = $this->boardAnalysis->getPieceMoved($uci, $fenBefore);
        $from   = $this->boardAnalysis->getSourceSquare($uci);
        $to     = $this->boardAnalysis->getTargetSquare($uci);

        $parserBefore = (new FenParserService())->parse($fenBefore);
        $parserAfter  = (new FenParserService())->parse($fenAfter);

        // development — N or B leaves back rank, early game
        if ($piece !== null && in_array(strtolower($piece), ['n', 'b'], true) && $moveNumber <= 20) {
            $backRank = $side === 'white' ? '1' : '8';
            if ($from[1] === $backRank) {
                $themes[] = 'development';
            }
        }

        // center_control — target is a central square or promotion square not relevant
        if (in_array($to, ['d4', 'd5', 'e4', 'e5'], true)) {
            $themes[] = 'center_control';
        }

        // material — piece count changed (capture occurred, including en passant)
        $countBefore = array_sum($parserBefore->getPieceCount());
        $countAfter  = array_sum($parserAfter->getPieceCount());
        if ($countAfter < $countBefore) {
            $themes[] = 'material';
        }

        // king_safety — castling rights changed (player just castled)
        if ($parserBefore->getCastlingRights() !== $parserAfter->getCastlingRights()) {
            $castledNow = $this->detectCastling($uci, $piece, $side);
            if ($castledNow) {
                $themes[] = 'king_safety';
            }
        }

        // activity — R or Q moves to an open file
        if ($piece !== null && in_array(strtolower($piece), ['r', 'q'], true)) {
            $openFiles = $parserBefore->getOpenFiles();
            if (in_array($to[0], $openFiles, true)) {
                $themes[] = 'activity';
            }
        }

        return array_values(array_unique($themes));
    }

    /**
     * Detect if this move was a castling move.
     * King moves ≥2 squares sideways = castling.
     */
    private function detectCastling(string $uci, ?string $piece, string $side): bool
    {
        if ($piece === null || strtolower($piece) !== 'k') {
            return false;
        }

        $fromFile = ord($uci[0]) - ord('a');
        $toFile   = ord($uci[2]) - ord('a');

        return abs($fromFile - $toFile) === 2;
    }
}
