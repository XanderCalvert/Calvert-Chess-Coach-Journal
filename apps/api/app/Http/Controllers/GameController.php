<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\GameResult;
use App\Enums\ImportSource;
use App\Enums\PlayerColour;
use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use App\Models\Move;
use App\Support\ShareCodeGenerator;
use Database\Seeders\DevUserSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GameController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Game::where('user_id', DevUserSeeder::UUID)
            ->orderByDesc('played_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Game $g) => [
                'id'               => $g->id,
                'white_player'     => $g->white_player,
                'black_player'     => $g->black_player,
                'result'           => $g->result->value,
                'played_at'        => $g->played_at?->toDateString(),
                'eco_code'         => $g->eco_code,
                'opening_name'     => $g->opening_name,
                'move_count'       => $g->move_count,
                'analysis_status'  => $g->analysis_status->value,
                'accuracy_pct'     => $g->accuracy_pct,
                'blunder_count'    => $g->blunder_count,
                'mistake_count'    => $g->mistake_count,
                'inaccuracy_count' => $g->inaccuracy_count,
                'share_code'       => $g->share_code,
            ]);

        return response()->json($games);
    }

    public function show(string $id): JsonResponse
    {
        $game = Game::with(['moves' => fn ($q) => $q->orderBy('move_number')])->findOrFail($id);

        return response()->json($this->formatGameResponse($game));
    }

    public function showByShareCode(string $code): JsonResponse
    {
        $game = Game::with(['moves' => fn ($q) => $q->orderBy('move_number')])
            ->where('share_code', $code)
            ->firstOrFail();

        return response()->json($this->formatGameResponse($game));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pgn_raw'      => ['required', 'string'],
            'white_player' => ['required', 'string', 'max:255'],
            'black_player' => ['required', 'string', 'max:255'],
            'result'       => ['required', Rule::enum(GameResult::class)],
            'played_at'    => ['nullable', 'date'],
            'eco_code'     => ['nullable', 'string', 'max:10'],
            'opening_name' => ['nullable', 'string', 'max:255'],
            'move_count'   => ['nullable', 'integer', 'min:1'],
            'moves'        => ['required', 'array', 'min:1'],
            'moves.*.move_number' => ['required', 'integer', 'min:1'],
            'moves.*.colour'      => ['required', Rule::enum(PlayerColour::class)],
            'moves.*.san'         => ['required', 'string', 'max:10'],
            'moves.*.uci'         => ['required', 'string', 'max:5', 'regex:/^[a-h][1-8][a-h][1-8][qrbn]?$/'],
            'moves.*.fen_before'  => ['required', 'string'],
            'moves.*.fen_after'   => ['required', 'string'],
        ]);

        $game = DB::transaction(function () use ($data): Game {
            $game = Game::create([
                'user_id'         => DevUserSeeder::UUID,
                'pgn_raw'         => $data['pgn_raw'],
                'white_player'    => $data['white_player'],
                'black_player'    => $data['black_player'],
                'result'          => $data['result'],
                'user_colour'     => PlayerColour::White,
                'played_at'       => $data['played_at'] ?? now(),
                'eco_code'        => $data['eco_code'] ?? '',
                'opening_name'    => $data['opening_name'] ?? 'Unknown',
                'move_count'      => $data['move_count'] ?? count($data['moves']),
                'analysis_status' => AnalysisStatus::Pending,
                'imported_from'   => ImportSource::Paste,
                'share_code'      => ShareCodeGenerator::generate(),
            ]);

            foreach ($data['moves'] as $moveData) {
                Move::create([
                    'game_id'     => $game->id,
                    'move_number' => $moveData['move_number'],
                    'colour'      => $moveData['colour'],
                    'san'         => $moveData['san'],
                    'uci'         => $moveData['uci'],
                    'fen_before'  => $moveData['fen_before'],
                    'fen_after'   => $moveData['fen_after'],
                ]);
            }

            return $game;
        });

        AnalyseGameJob::dispatch($game->id)->afterCommit();

        return response()->json([
            'game_id'    => $game->id,
            'move_count' => $game->move_count,
        ], 201);
    }

    private function formatGameResponse(Game $game): array
    {
        return [
            'id'               => $game->id,
            'white_player'     => $game->white_player,
            'black_player'     => $game->black_player,
            'result'           => $game->result->value,
            'played_at'        => $game->played_at?->toDateString(),
            'eco_code'         => $game->eco_code,
            'opening_name'     => $game->opening_name,
            'move_count'       => $game->move_count,
            'analysis_status'  => $game->analysis_status->value,
            'accuracy_pct'     => $game->accuracy_pct,
            'blunder_count'    => $game->blunder_count,
            'mistake_count'    => $game->mistake_count,
            'inaccuracy_count' => $game->inaccuracy_count,
            'user_colour'      => $game->user_colour?->value,
            'share_code'       => $game->share_code,
            'source_url'       => $this->extractExternalGameUrl($game),
            'moves'            => $game->moves->map(fn (Move $m) => [
                'id'             => $m->id,
                'move_number'    => $m->move_number,
                'colour'         => $m->colour->value,
                'san'            => $m->san,
                'uci'            => $m->uci,
                'fen_before'     => $m->fen_before,
                'fen_after'      => $m->fen_after,
                'cp_score'       => $m->cp_score,
                'cp_loss'        => $m->cp_loss,
                'classification' => $m->classification?->value,
            ]),
        ];
    }

    private function extractExternalGameUrl(Game $game): ?string
    {
        if ($game->imported_from !== ImportSource::ChessCom) {
            return null;
        }

        // Prefer [Link "…"] — Chess.com PGNs also include [Site "Chess.com"], which is not a URL.
        if (preg_match('/\[Link\s+"([^"]+)"\]/i', $game->pgn_raw, $matches)) {
            $url = trim($matches[1]);
            if (str_contains(strtolower($url), 'chess.com')) {
                return $url;
            }
        }

        if (preg_match('/\[Site\s+"([^"]+)"\]/i', $game->pgn_raw, $matches)) {
            $url = trim($matches[1]);
            $lower = strtolower($url);
            if (str_starts_with($lower, 'http') && str_contains($lower, 'chess.com')) {
                return $url;
            }
        }

        return null;
    }
}
