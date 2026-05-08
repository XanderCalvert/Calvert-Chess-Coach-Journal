<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Enums\GameResult;
use App\Enums\ImportSource;
use App\Enums\Platform;
use App\Enums\PlayerColour;
use App\Exceptions\PgnParseException;
use App\Models\ConnectedAccount;
use App\Models\Game;
use App\Models\Move;
use App\Services\PgnParserService;
use App\Support\ShareCodeGenerator;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportExternalGameJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [60];

    /** @param array<string, mixed> $rawGame Chess.com game object */
    public function __construct(
        public readonly string $connectedAccountId,
        public readonly array $rawGame,
    ) {}

    public function handle(PgnParserService $parser): void
    {
        $account = ConnectedAccount::findOrFail($this->connectedAccountId);

        // Dedup: skip if already imported
        if (Game::where('connected_account_id', $this->connectedAccountId)
            ->where('external_id', $this->rawGame['uuid'])
            ->exists()) {
            return;
        }

        try {
            $parsed = $parser->parse($this->rawGame['pgn']);
        } catch (PgnParseException $e) {
            Log::warning('ImportExternalGameJob: PGN parse failed', [
                'connected_account_id' => $this->connectedAccountId,
                'chess_com_uuid'       => $this->rawGame['uuid'],
                'error'                => $e->getMessage(),
            ]);
            return;
        }

        $white = $this->rawGame['white'];
        $black = $this->rawGame['black'];

        $userColour = strtolower($white['username']) === $account->normalised_username
            ? PlayerColour::White
            : PlayerColour::Black;

        [$userRating, $opponentUsername, $opponentRating] = $userColour === PlayerColour::White
            ? [$white['rating'] ?? null, $black['username'], $black['rating'] ?? null]
            : [$black['rating'] ?? null, $white['username'], $white['rating'] ?? null];

        $result = $this->mapResult($white['result'] ?? '', $black['result'] ?? '');

        $headers = $parsed['headers'];

        $game = DB::transaction(function () use ($parsed, $headers, $account, $userColour, $userRating, $opponentUsername, $opponentRating, $result): Game {
            $game = Game::create([
                'connected_account_id' => $account->id,
                'pgn_raw'              => $this->rawGame['pgn'],
                'white_player'         => $this->rawGame['white']['username'],
                'black_player'         => $this->rawGame['black']['username'],
                'result'               => $result,
                'user_colour'          => $userColour,
                'played_at'            => isset($this->rawGame['end_time'])
                    ? Carbon::createFromTimestamp($this->rawGame['end_time'])
                    : now(),
                'eco_code'             => $headers['eco_code'] ?? '',
                'opening_name'         => $headers['opening_name'] ?? 'Unknown',
                'move_count'           => count($parsed['moves']),
                'analysis_status'      => AnalysisStatus::Pending,
                'imported_from'        => ImportSource::ChessCom,
                'external_id'          => $this->rawGame['uuid'],
                'platform'             => Platform::Chesscom->value,
                'time_control'         => $this->rawGame['time_control'] ?? null,
                'rated'                => $this->rawGame['rated'] ?? null,
                'user_rating_before'   => $userRating,
                'opponent_username'    => $opponentUsername,
                'opponent_rating'      => $opponentRating,
                'share_code'           => ShareCodeGenerator::generate(),
            ]);

            foreach ($parsed['moves'] as $moveData) {
                Move::create([
                    'game_id'     => $game->id,
                    'move_number' => $moveData['move_number'],
                    'colour'      => PlayerColour::from($moveData['colour']),
                    'san'         => $moveData['san'],
                    'uci'         => $moveData['uci'],
                    'fen_before'  => $moveData['fen_before'],
                    'fen_after'   => $moveData['fen_after'],
                ]);
            }

            return $game;
        });

        AnalyseGameJob::dispatch($game->id)->afterCommit();
    }

    public function failed(Throwable $e): void
    {
        Log::error('ImportExternalGameJob failed', [
            'connected_account_id' => $this->connectedAccountId,
            'chess_com_uuid'       => $this->rawGame['uuid'] ?? 'unknown',
            'error'                => $e->getMessage(),
        ]);
    }

    private function mapResult(string $whiteResult, string $blackResult): GameResult
    {
        if ($whiteResult === 'win') {
            return GameResult::White;
        }

        if ($blackResult === 'win') {
            return GameResult::Black;
        }

        $drawResults = ['agreed', 'repetition', 'stalemate', 'insufficient', '50move', 'timevsinsufficient'];

        if (in_array($whiteResult, $drawResults, true) || in_array($blackResult, $drawResults, true)) {
            return GameResult::Draw;
        }

        return GameResult::Unknown;
    }
}
