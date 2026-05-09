<?php

namespace App\Services;

/**
 * Conservative threat detection using engine data + lightweight board heuristics.
 * Uses soft/hedged flag names to avoid false confidence.
 */
class ThreatDetectorService
{
    public function __construct(
        private FenParserService $fenParser,
        private BoardAnalysisService $boardAnalysis,
    ) {}

    /**
     * @param  array $engineBefore  { best_move: string, cp: int|null, mate: int|null, best_line: string[] }
     * @param  array $engineAfter   { best_move: string, cp: int|null, mate: int|null, best_line: string[] }
     * @return array { tactical_flags: string[], threat_awareness: array }
     */
    public function analyse(
        string $fenBefore,
        string $fenAfter,
        string $playedUci,
        array  $engineBefore,
        array  $engineAfter,
    ): array {
        $side = $this->fenParser->parse($fenBefore)->getSideToMove();

        $flagsBefore = $this->detectFlags($fenBefore, $engineBefore, $side);
        $flagsAfter  = $this->detectFlags($fenAfter, $engineAfter, $side === 'w' ? 'b' : 'w');

        $response   = $this->classifyResponse($flagsBefore, $flagsAfter);
        $confidence = $this->scoreConfidence($flagsBefore, $flagsAfter, $engineBefore, $engineAfter);

        return [
            'tactical_flags'  => $flagsBefore,
            'threat_awareness' => [
                'threats_before' => $flagsBefore,
                'threats_after'  => $flagsAfter,
                'response'       => $response,
                'confidence'     => $confidence,
            ],
        ];
    }

    // -------------------------------------------------------------------------

    private function detectFlags(string $fen, array $engine, string $activeSide): array
    {
        $flags = [];

        // forced_mate_present — high confidence, direct from engine
        if (!empty($engine['mate'])) {
            $flags[] = 'forced_mate_present';
        }

        // engine_prefers_capture — high confidence, engine best_line[0] is a capture
        if (!empty($engine['best_line'][0]) && $this->isCaptureMove($engine['best_line'][0], $fen)) {
            $flags[] = 'engine_prefers_capture';
        }

        // hanging_piece — medium confidence: eval swing + best line starts with capture
        if ($this->isHangingPieceSignal($engine)) {
            $flags[] = 'hanging_piece';
        }

        // possible_fork — medium confidence: knight move with ≥2 valuable targets
        if (!empty($engine['best_line'][0]) && $this->isPossibleFork($engine['best_line'][0], $fen, $activeSide)) {
            $flags[] = 'possible_fork';
        }

        // possible_pin / possible_skewer — low confidence: ray heuristic
        if (!empty($engine['best_line'][0]) && $this->isPossiblePin($engine['best_line'][0], $fen, $activeSide)) {
            $flags[] = 'possible_pin';
        }

        return array_values(array_unique($flags));
    }

    private function isCaptureMove(string $uci, string $fen): bool
    {
        $to = substr($uci, 2, 2);
        $piece = $this->fenParser->parse($fen)->getPieceAt($to);
        return $piece !== null;
    }

    private function isHangingPieceSignal(array $engine): bool
    {
        // Consider "hanging" when: no mate signal, eval is strongly in favour of one side,
        // and the engine's top move is a capture. We avoid exact cp thresholds since this
        // is already covered by classification; this flag is about the *type* of threat.
        return empty($engine['mate'])
            && !empty($engine['best_line'][0])
            && str_contains($engine['best_line'][0] ?? '', 'x') === false  // UCI has no 'x'
            && ($engine['cp'] !== null && abs($engine['cp']) >= 150);
    }

    private function isPossibleFork(string $uci, string $fen, string $attackingSide): bool
    {
        $piece = $this->boardAnalysis->getPieceMoved($uci, $fen);
        if ($piece === null || strtolower($piece) !== 'n') {
            return false;
        }

        $to    = $this->boardAnalysis->getTargetSquare($uci);
        $board = $this->fenParser->parse($fen)->getBoard();
        $attacked = $this->boardAnalysis->getKnightAttacks($to);

        $valuableTargets = 0;
        $defendingSide = $attackingSide === 'w' ? 'b' : 'w';
        foreach ($attacked as $sq) {
            $target = $board[$sq] ?? null;
            if ($target === null) {
                continue;
            }
            $targetIsDefending = ($defendingSide === 'b' && $target === strtolower($target))
                || ($defendingSide === 'w' && $target === strtoupper($target));
            if ($targetIsDefending && $this->boardAnalysis->pieceValue($target) >= 3) {
                $valuableTargets++;
            }
        }

        return $valuableTargets >= 2;
    }

    private function isPossiblePin(string $uci, string $fen, string $attackingSide): bool
    {
        $piece = $this->boardAnalysis->getPieceMoved($uci, $fen);
        if ($piece === null) {
            return false;
        }

        // Only sliding pieces (B, R, Q) can create pins
        if (!in_array(strtolower($piece), ['b', 'r', 'q'], true)) {
            return false;
        }

        $to    = $this->boardAnalysis->getTargetSquare($uci);
        $board = $this->fenParser->parse($fen)->getBoard();
        $defendingSide = $attackingSide === 'w' ? 'b' : 'w';

        $directions = $this->getDirectionsForPiece(strtolower($piece));

        foreach ($directions as [$df, $dr]) {
            $ray = $this->boardAnalysis->getRaySquares($to, $df, $dr, $board);
            $piecesOnRay = array_filter($ray, fn($sq) => isset($board[$sq]));
            $piecesOnRay = array_values($piecesOnRay);

            if (count($piecesOnRay) >= 2) {
                $first  = $board[$piecesOnRay[0]];
                $second = $board[$piecesOnRay[1]];

                $firstIsDefending  = ($defendingSide === 'b' && $first === strtolower($first))
                    || ($defendingSide === 'w' && $first === strtoupper($first));
                $secondIsDefending = ($defendingSide === 'b' && $second === strtolower($second))
                    || ($defendingSide === 'w' && $second === strtoupper($second));

                if ($firstIsDefending && $secondIsDefending
                    && $this->boardAnalysis->pieceValue($second) > $this->boardAnalysis->pieceValue($first)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getDirectionsForPiece(string $piece): array
    {
        $diagonals   = [[-1,-1],[-1,1],[1,-1],[1,1]];
        $straights   = [[0,1],[0,-1],[1,0],[-1,0]];
        return match ($piece) {
            'b'     => $diagonals,
            'r'     => $straights,
            default => array_merge($diagonals, $straights),
        };
    }

    private function classifyResponse(array $flagsBefore, array $flagsAfter): string
    {
        if (empty($flagsBefore)) {
            return 'none';
        }

        $threatsBefore = array_intersect($flagsBefore, ['forced_mate_present', 'hanging_piece', 'engine_prefers_capture']);
        $threatsAfter  = array_intersect($flagsAfter, ['forced_mate_present', 'hanging_piece', 'engine_prefers_capture']);

        if (empty($threatsBefore)) {
            return 'none';
        }

        if (count($threatsAfter) < count($threatsBefore)) {
            return 'addressed';
        }

        return 'not_addressed';
    }

    private function scoreConfidence(array $flagsBefore, array $flagsAfter, array $engineBefore, array $engineAfter): string
    {
        // High confidence when only engine-derived flags are present
        $highConfidenceFlags = ['forced_mate_present', 'engine_prefers_capture'];
        $lowConfidenceFlags  = ['possible_pin', 'possible_skewer', 'possible_fork'];

        $hasHigh = !empty(array_intersect($flagsBefore, $highConfidenceFlags));
        $hasLow  = !empty(array_intersect($flagsBefore, $lowConfidenceFlags));

        if (empty($flagsBefore)) {
            return 'high';
        }
        if ($hasHigh && !$hasLow) {
            return 'high';
        }
        if ($hasLow && !$hasHigh) {
            return 'low';
        }
        return 'medium';
    }
}
