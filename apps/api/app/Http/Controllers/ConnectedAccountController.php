<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Jobs\SyncChessComAccountJob;
use App\Models\ConnectedAccount;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConnectedAccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = ConnectedAccount::query()
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
            ['platform' => $data['platform'], 'normalised_username' => $normalised],
            ['username' => $data['username']],
        );

        return response()->json($this->formatAccount($account), $account->wasRecentlyCreated ? 201 : 200);
    }

    public function showByUsername(string $platform, string $username): JsonResponse
    {
        $account = ConnectedAccount::where('platform', $platform)
            ->where('normalised_username', strtolower($username))
            ->firstOrFail();

        return response()->json($this->formatAccount($account));
    }

    public function gamesByUsername(string $platform, string $username): JsonResponse
    {
        $account = ConnectedAccount::where('platform', $platform)
            ->where('normalised_username', strtolower($username))
            ->firstOrFail();

        $games = Game::where('connected_account_id', $account->id)
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

    public function sync(string $platform, string $username): JsonResponse
    {
        $account = ConnectedAccount::where('platform', $platform)
            ->where('normalised_username', strtolower($username))
            ->firstOrFail();

        if ($account->sync_status === SyncStatus::Syncing) {
            return response()->json($this->formatAccount($account), 409);
        }

        SyncChessComAccountJob::dispatch($account->id);
        $account->update(['sync_status' => SyncStatus::Syncing->value]);

        return response()->json($this->formatAccount($account->fresh()), 202);
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
