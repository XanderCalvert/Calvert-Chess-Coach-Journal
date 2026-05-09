<?php

namespace App\Http\Controllers;

use App\Services\StockfishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PositionController extends Controller
{
    public function analyse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fen'      => ['required', 'string'],
            'multipv'  => ['nullable', 'integer', 'min:1', 'max:5'],
            'time_ms'  => ['nullable', 'integer', 'min:100', 'max:10000'],
        ]);

        $fen     = $data['fen'];
        $multipv = (int) ($data['multipv'] ?? 3);
        $timeMs  = (int) ($data['time_ms'] ?? 2000);

        if (! $this->validateFen($fen)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['fen' => ['The FEN string is not a valid chess position.']],
            ], 422);
        }

        $engineVersion = config('services.stockfish.version', 'stockfish');
        $cacheKey = 'position_analysis:' . hash('sha256', $fen) . ":{$multipv}:{$timeMs}:{$engineVersion}";

        $result = Cache::remember($cacheKey, now()->addHours(24), function () use ($fen, $multipv, $timeMs) {
            $service    = new StockfishService();
            $candidates = $service->analysePosition($fen, $multipv, $timeMs);

            // Determine side-to-move from FEN field 2
            $parts      = explode(' ', $fen);
            $sideToMove = $parts[1] ?? 'w';

            // Normalise cp to White-positive
            foreach ($candidates as &$candidate) {
                if ($candidate['cp'] !== null && $sideToMove === 'b') {
                    $candidate['cp'] = -$candidate['cp'];
                }
            }
            unset($candidate);

            return [
                'fen'            => $fen,
                'side_to_move'   => $sideToMove,
                'engine_version' => config('services.stockfish.version', 'stockfish'),
                'candidates'     => $candidates,
            ];
        });

        return response()->json($result);
    }

    private function validateFen(string $fen): bool
    {
        $parts = explode(' ', trim($fen));

        if (count($parts) !== 6) {
            return false;
        }

        [$placement, $activeColour, $castling, $enPassant, $halfmove, $fullmove] = $parts;

        // Active colour
        if (! in_array($activeColour, ['w', 'b'], true)) {
            return false;
        }

        // Castling
        if (! preg_match('/^(-|[KQkq]+)$/', $castling)) {
            return false;
        }

        // En passant
        if ($enPassant !== '-' && ! preg_match('/^[a-h][36]$/', $enPassant)) {
            return false;
        }

        // Halfmove and fullmove clocks
        if (! ctype_digit($halfmove) || ! ctype_digit($fullmove) || (int) $fullmove < 1) {
            return false;
        }

        // Piece placement: 8 ranks separated by /
        $ranks = explode('/', $placement);
        if (count($ranks) !== 8) {
            return false;
        }

        $whiteKings = 0;
        $blackKings = 0;

        foreach ($ranks as $rank) {
            $squareCount = 0;
            foreach (str_split($rank) as $char) {
                if (ctype_digit($char)) {
                    $squareCount += (int) $char;
                } elseif (in_array($char, ['p','n','b','r','q','k','P','N','B','R','Q','K'], true)) {
                    $squareCount++;
                    if ($char === 'K') $whiteKings++;
                    if ($char === 'k') $blackKings++;
                } else {
                    return false;
                }
            }
            if ($squareCount !== 8) {
                return false;
            }
        }

        if ($whiteKings !== 1 || $blackKings !== 1) {
            return false;
        }

        return true;
    }
}
