<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\GameResult;
use App\Enums\Platform;
use App\Enums\PlayerColour;
use App\Enums\SyncStatus;
use App\Jobs\SyncChessComAccountJob;
use App\Models\ConnectedAccount;
use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConnectedAccountController extends Controller
{
    private const GAME_TYPE_ALL = 'all';
    private const GAME_TYPE_BULLET = 'bullet';
    private const GAME_TYPE_BLITZ = 'blitz';
    private const GAME_TYPE_RAPID = 'rapid';
    private const GAME_TYPE_DAILY = 'daily';

    public function index(): JsonResponse
    {
        $accounts = ConnectedAccount::query()
            ->where('user_id', auth()->id())
            ->orderBy('platform')
            ->orderBy('username')
            ->paginate(20);

        return response()->json([
            'data' => collect($accounts->items())->map(
                fn (ConnectedAccount $account) => $this->formatAccount($account)
            ),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page'    => $accounts->lastPage(),
                'total'        => $accounts->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::enum(Platform::class)],
            'username' => ['required', 'string', 'max:64'],
        ]);

        $normalised = strtolower($data['username']);

        $account = ConnectedAccount::updateOrCreate(
            ['user_id' => auth()->id(), 'platform' => $data['platform'], 'normalised_username' => $normalised],
            ['username' => $data['username']],
        );

        return response()->json($this->formatAccount($account), $account->wasRecentlyCreated ? 201 : 200);
    }

    // Intentionally unauthenticated — powers the public /u/[username] profile page.
    public function showByUsername(string $platform, string $username): JsonResponse
    {
        $account = ConnectedAccount::where('platform', $platform)
            ->where('normalised_username', strtolower($username))
            ->firstOrFail();

        return response()->json($this->formatAccount($account));
    }

    // Intentionally unauthenticated — powers the public /u/[username] profile page.
    public function gamesByUsername(Request $request, string $platform, string $username): JsonResponse
    {
        $account = ConnectedAccount::where('platform', $platform)
            ->where('normalised_username', strtolower($username))
            ->firstOrFail();

        $validated = $request->validate([
            'game_type' => ['nullable', Rule::in([
                self::GAME_TYPE_ALL,
                self::GAME_TYPE_BULLET,
                self::GAME_TYPE_BLITZ,
                self::GAME_TYPE_RAPID,
                self::GAME_TYPE_DAILY,
            ])],
        ]);
        $gameType = $validated['game_type'] ?? self::GAME_TYPE_ALL;

        $query = Game::where('connected_account_id', $account->id);
        $matchingIds = $this->matchingGameIdsForType($account->id, $gameType);
        if ($matchingIds !== null) {
            if ($matchingIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $matchingIds);
            }
        }

        $games = $query
            ->orderByDesc('played_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => collect($games->items())->map(fn (Game $g) => [
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
                'time_control'     => $g->time_control,
                'opponent_username' => $g->opponent_username,
                'opponent_rating'  => $g->opponent_rating,
                'user_rating_before' => $g->user_rating_before,
            ]),
            'meta' => [
                'current_page' => $games->currentPage(),
                'last_page'    => $games->lastPage(),
                'total'        => $games->total(),
            ],
        ]);
    }

    // Intentionally unauthenticated — powers the public /u/[username] profile page.
    public function statsByUsername(Request $request, string $platform, string $username): JsonResponse
    {
        $account = ConnectedAccount::where('platform', $platform)
            ->where('normalised_username', strtolower($username))
            ->firstOrFail();

        $validated = $request->validate([
            'game_type' => ['nullable', Rule::in([
                self::GAME_TYPE_ALL,
                self::GAME_TYPE_BULLET,
                self::GAME_TYPE_BLITZ,
                self::GAME_TYPE_RAPID,
                self::GAME_TYPE_DAILY,
            ])],
            'days' => ['nullable', 'integer', Rule::in([0, 30, 90, 180, 365])],
        ]);
        $gameType = $validated['game_type'] ?? self::GAME_TYPE_ALL;
        $days = (int) ($validated['days'] ?? 90);
        $cutoff = $days > 0 ? Carbon::now()->subDays($days)->startOfDay() : null;

        $typeMeta = $this->analysedGameTypeMeta($account->id);

        $base = Game::where('connected_account_id', $account->id)
            ->where('analysis_status', AnalysisStatus::Complete);
        $matchingIds = $this->matchingGameIdsForType($account->id, $gameType, AnalysisStatus::Complete->value);
        if ($matchingIds !== null) {
            if ($matchingIds->isEmpty()) {
                return response()->json(array_merge([
                    'games_analysed'        => 0,
                    'wins'                  => 0,
                    'draws'                 => 0,
                    'losses'                => 0,
                    'avg_cp_loss'           => null,
                    'median_cp_loss'        => null,
                    'blunders_per_game'     => null,
                    'median_blunders_per_game' => null,
                    'mistakes_per_game'     => null,
                    'median_mistakes_per_game' => null,
                    'inaccuracies_per_game' => null,
                    'median_inaccuracies_per_game' => null,
                    'rating_trend'          => [],
                    'cp_loss_trend'         => [],
                    'blunders_trend'        => [],
                    'mistakes_trend'        => [],
                    'inaccuracies_trend'    => [],
                    'recent_games'          => [],
                ], $typeMeta));
            }
            $base->whereIn('id', $matchingIds);
        }

        if ($cutoff !== null) {
            $base->where('played_at', '>=', $cutoff);
        }

        $gamesAnalysed = (clone $base)->count();

        if ($gamesAnalysed === 0) {
            return response()->json(array_merge([
                'games_analysed'        => 0,
                'wins'                  => 0,
                'draws'                 => 0,
                'losses'                => 0,
                'avg_cp_loss'           => null,
                'median_cp_loss'        => null,
                'blunders_per_game'     => null,
                'median_blunders_per_game' => null,
                'mistakes_per_game'     => null,
                'median_mistakes_per_game' => null,
                'inaccuracies_per_game' => null,
                'median_inaccuracies_per_game' => null,
                'rating_trend'          => [],
                'cp_loss_trend'         => [],
                'blunders_trend'        => [],
                'mistakes_trend'        => [],
                'inaccuracies_trend'    => [],
                'recent_games'          => [],
            ], $typeMeta));
        }

        // W/D/L from tracked player's perspective.
        // games.result stores the board outcome (white/black/draw), not player-relative.
        $wins = (clone $base)->where(function ($q) {
            $q->where(fn ($q) => $q->where('result', GameResult::White->value)->where('user_colour', PlayerColour::White->value))
              ->orWhere(fn ($q) => $q->where('result', GameResult::Black->value)->where('user_colour', PlayerColour::Black->value));
        })->count();

        $losses = (clone $base)->where(function ($q) {
            $q->where(fn ($q) => $q->where('result', GameResult::Black->value)->where('user_colour', PlayerColour::White->value))
              ->orWhere(fn ($q) => $q->where('result', GameResult::White->value)->where('user_colour', PlayerColour::Black->value));
        })->count();

        $draws = (clone $base)->where('result', GameResult::Draw->value)->count();

        $blundersPerGame     = round((clone $base)->avg('blunder_count') ?? 0, 2);
        $mistakesPerGame     = round((clone $base)->avg('mistake_count') ?? 0, 2);
        $inaccuraciesPerGame = round((clone $base)->avg('inaccuracy_count') ?? 0, 2);

        // Avg CPL: user's colour moves only, non-null cp_loss, games with no analysed moves excluded.
        $rawAvgCpl = DB::table('moves')
            ->join('games', 'moves.game_id', '=', 'games.id')
            ->where('games.connected_account_id', $account->id)
            ->where('games.analysis_status', AnalysisStatus::Complete->value)
            ->when($matchingIds !== null, fn ($query) => $query->whereIn('games.id', $matchingIds))
            ->when($cutoff !== null, fn ($query) => $query->where('games.played_at', '>=', $cutoff))
            ->whereColumn('moves.colour', 'games.user_colour')
            ->whereNotNull('moves.cp_loss')
            ->avg('moves.cp_loss');
        $avgCpLoss = $rawAvgCpl !== null ? round($rawAvgCpl, 1) : null;

        $cpLossByGame = DB::table('games')
            ->join('moves', function ($join) {
                $join->on('moves.game_id', '=', 'games.id')
                     ->on('moves.colour', '=', 'games.user_colour')
                     ->whereNotNull('moves.cp_loss');
            })
            ->where('games.connected_account_id', $account->id)
            ->where('games.analysis_status', AnalysisStatus::Complete->value)
            ->when($matchingIds !== null, fn ($query) => $query->whereIn('games.id', $matchingIds))
            ->when($cutoff !== null, fn ($query) => $query->where('games.played_at', '>=', $cutoff))
            ->select('games.id', 'games.played_at', DB::raw('AVG(moves.cp_loss) as avg_cp_loss'))
            ->groupBy('games.id', 'games.played_at')
            ->orderBy('games.played_at')
            ->get();

        $medianCpLoss = $this->median(
            $cpLossByGame
                ->pluck('avg_cp_loss')
                ->map(fn ($value) => (float) $value)
        );

        $blunderValues = (clone $base)->whereNotNull('blunder_count')->pluck('blunder_count');
        $mistakeValues = (clone $base)->whereNotNull('mistake_count')->pluck('mistake_count');
        $inaccuracyValues = (clone $base)->whereNotNull('inaccuracy_count')->pluck('inaccuracy_count');

        $medianBlundersPerGame = $this->median($blunderValues);
        $medianMistakesPerGame = $this->median($mistakeValues);
        $medianInaccuraciesPerGame = $this->median($inaccuracyValues);

        // Rating trend: last 30 complete games that have at least one rating value.
        $ratingTrend = (clone $base)
            ->where(fn ($q) => $q->whereNotNull('user_rating_after')->orWhereNotNull('user_rating_before'))
            ->orderBy('played_at')
            ->limit(30)
            ->get(['played_at', 'user_rating_after', 'user_rating_before'])
            ->map(fn ($g) => [
                'played_at' => $g->played_at?->toDateString(),
                'rating'    => $g->user_rating_after ?? $g->user_rating_before,
            ])
            ->values();

        // CPL trend: per-game avg cp_loss for user's colour, last 30 games with analysed moves.
        $cpLossTrend = $cpLossByGame
            ->take(-30)
            ->map(fn ($row) => [
                'played_at'   => Carbon::parse($row->played_at)->toDateString(),
                'avg_cp_loss' => round($row->avg_cp_loss, 1),
            ])
            ->values();

        // Blunders trend: last 30 complete games.
        $blundersTrend = (clone $base)
            ->orderBy('played_at')
            ->limit(30)
            ->get(['played_at', 'blunder_count'])
            ->map(fn ($g) => [
                'played_at' => $g->played_at?->toDateString(),
                'blunders'  => $g->blunder_count,
            ])
            ->values();

        $mistakesTrend = (clone $base)
            ->orderBy('played_at')
            ->limit(30)
            ->get(['played_at', 'mistake_count'])
            ->map(fn ($g) => [
                'played_at' => $g->played_at?->toDateString(),
                'mistakes'  => $g->mistake_count,
            ])
            ->values();

        $inaccuraciesTrend = (clone $base)
            ->orderBy('played_at')
            ->limit(30)
            ->get(['played_at', 'inaccuracy_count'])
            ->map(fn ($g) => [
                'played_at'     => $g->played_at?->toDateString(),
                'inaccuracies'  => $g->inaccuracy_count,
            ])
            ->values();

        // Recent 5 analysed games with per-game avg CPL.
        $recentGames = (clone $base)
            ->orderByDesc('played_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'share_code', 'played_at', 'result', 'user_colour', 'opponent_username', 'blunder_count']);

        $recentCplByGame = DB::table('moves')
            ->join('games', 'moves.game_id', '=', 'games.id')
            ->whereIn('moves.game_id', $recentGames->pluck('id'))
            ->whereColumn('moves.colour', 'games.user_colour')
            ->whereNotNull('moves.cp_loss')
            ->select('moves.game_id', DB::raw('AVG(moves.cp_loss) as avg_cp_loss'))
            ->groupBy('moves.game_id')
            ->get()
            ->keyBy('game_id');

        $recentGamesFormatted = $recentGames->map(function ($g) use ($recentCplByGame) {
            $won  = ($g->result === GameResult::White && $g->user_colour === PlayerColour::White)
                 || ($g->result === GameResult::Black && $g->user_colour === PlayerColour::Black);
            $lost = ($g->result === GameResult::Black && $g->user_colour === PlayerColour::White)
                 || ($g->result === GameResult::White && $g->user_colour === PlayerColour::Black);

            $cpl = $recentCplByGame->get($g->id);

            return [
                'share_code'        => $g->share_code,
                'played_at'         => $g->played_at?->toDateString(),
                'result'            => $won ? 'WIN' : ($lost ? 'LOSS' : 'DRAW'),
                'opponent_username' => $g->opponent_username,
                'avg_cp_loss'       => $cpl ? round($cpl->avg_cp_loss, 1) : null,
                'blunder_count'     => $g->blunder_count,
            ];
        })->values();

        return response()->json(array_merge([
            'games_analysed'        => $gamesAnalysed,
            'wins'                  => $wins,
            'draws'                 => $draws,
            'losses'                => $losses,
            'avg_cp_loss'           => $avgCpLoss,
            'median_cp_loss'        => $medianCpLoss !== null ? round($medianCpLoss, 1) : null,
            'blunders_per_game'     => $blundersPerGame,
            'median_blunders_per_game' => $medianBlundersPerGame !== null ? round($medianBlundersPerGame, 1) : null,
            'mistakes_per_game'     => $mistakesPerGame,
            'median_mistakes_per_game' => $medianMistakesPerGame !== null ? round($medianMistakesPerGame, 1) : null,
            'inaccuracies_per_game' => $inaccuraciesPerGame,
            'median_inaccuracies_per_game' => $medianInaccuraciesPerGame !== null ? round($medianInaccuraciesPerGame, 1) : null,
            'rating_trend'          => $ratingTrend,
            'cp_loss_trend'         => $cpLossTrend,
            'blunders_trend'        => $blundersTrend,
            'mistakes_trend'        => $mistakesTrend,
            'inaccuracies_trend'    => $inaccuraciesTrend,
            'recent_games'          => $recentGamesFormatted,
        ], $typeMeta));
    }

    private function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values
            ->map(fn ($value) => (float) $value)
            ->sort()
            ->values();

        $count = $sorted->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($sorted[$middle - 1] + $sorted[$middle]) / 2;
        }

        return $sorted[$middle];
    }

    public function sync(string $platform, string $username): JsonResponse
    {
        $account = ConnectedAccount::where('platform', $platform)
            ->where('normalised_username', strtolower($username))
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($account->sync_status === SyncStatus::Syncing) {
            return response()->json($this->formatAccount($account), 409);
        }

        SyncChessComAccountJob::dispatch($account->id);
        $account->update(['sync_status' => SyncStatus::Syncing->value]);

        return response()->json($this->formatAccount($account->fresh()), 202);
    }

    public function destroy(string $id): JsonResponse
    {
        $account = ConnectedAccount::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $account->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{analysed_counts_by_type: array<string, int>, recommended_game_type: string|null}
     */
    private function analysedGameTypeMeta(string $accountId): array
    {
        $games = Game::query()
            ->where('connected_account_id', $accountId)
            ->where('analysis_status', AnalysisStatus::Complete)
            ->get(['time_control']);

        $counts = [
            self::GAME_TYPE_BULLET => 0,
            self::GAME_TYPE_BLITZ  => 0,
            self::GAME_TYPE_RAPID  => 0,
            self::GAME_TYPE_DAILY  => 0,
        ];

        foreach ($games as $game) {
            $bucket = $this->deriveGameType($game->time_control);
            if ($bucket !== null) {
                $counts[$bucket]++;
            }
        }

        return [
            'analysed_counts_by_type' => $counts,
            'recommended_game_type'   => $this->recommendedGameTypeFromCounts($counts),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function recommendedGameTypeFromCounts(array $counts): ?string
    {
        $max = max($counts);
        if ($max === 0) {
            return null;
        }

        $tieOrder = [
            self::GAME_TYPE_BULLET,
            self::GAME_TYPE_BLITZ,
            self::GAME_TYPE_RAPID,
            self::GAME_TYPE_DAILY,
        ];

        foreach ($tieOrder as $type) {
            if (($counts[$type] ?? 0) === $max) {
                return $type;
            }
        }

        return null;
    }

    private function matchingGameIdsForType(string $accountId, string $gameType, ?string $analysisStatus = null): ?Collection
    {
        if ($gameType === self::GAME_TYPE_ALL) {
            return null;
        }

        $games = Game::query()
            ->where('connected_account_id', $accountId)
            ->when($analysisStatus !== null, fn ($query) => $query->where('analysis_status', $analysisStatus))
            ->get(['id', 'time_control']);

        return $games
            ->filter(fn (Game $game) => $this->deriveGameType($game->time_control) === $gameType)
            ->pluck('id')
            ->values();
    }

    private function deriveGameType(?string $timeControl): ?string
    {
        if ($timeControl === null || $timeControl === '') {
            return null;
        }

        if (str_contains($timeControl, '/')) {
            return self::GAME_TYPE_DAILY;
        }

        if (!preg_match('/^\d+/', $timeControl, $matches)) {
            return null;
        }

        $seconds = (int) $matches[0];
        if ($seconds < 180) {
            return self::GAME_TYPE_BULLET;
        }

        if ($seconds < 480) {
            return self::GAME_TYPE_BLITZ;
        }

        return self::GAME_TYPE_RAPID;
    }

    private function formatAccount(ConnectedAccount $account): array
    {
        return [
            'id'                  => $account->id,
            'platform'            => $account->platform->value,
            'username'            => $account->username,
            'normalised_username' => $account->normalised_username,
            'rapid_rating'        => $account->rapid_rating,
            'blitz_rating'        => $account->blitz_rating,
            'bullet_rating'       => $account->bullet_rating,
            'daily_rating'        => $account->daily_rating,
            'last_synced_at'      => $account->last_synced_at?->toIso8601String(),
            'sync_status'         => $account->sync_status->value,
        ];
    }
}
