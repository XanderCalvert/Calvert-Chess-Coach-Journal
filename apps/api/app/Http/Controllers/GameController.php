<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\GameResult;
use App\Enums\ImportSource;
use App\Enums\PlayerColour;
use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use App\Models\Move;
use Database\Seeders\DevUserSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GameController extends Controller
{
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
}
