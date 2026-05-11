<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\ConnectedAccount;
use App\Models\Game;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueueRecentAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $connectedAccountId,
    ) {}

    public function handle(): void
    {
        $syncLimit = config('chess.auto_analyse_on_sync', 5);

        $account = ConnectedAccount::with('user')->findOrFail($this->connectedAccountId);
        $user    = $account->user;

        $candidates = Game::where('connected_account_id', $this->connectedAccountId)
            ->where('analysis_status', AnalysisStatus::Pending)
            ->orderByDesc('played_at')
            ->limit($syncLimit)
            ->get();

        $dispatched = 0;

        DB::transaction(function () use ($user, $candidates, &$dispatched) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->first();
            $lockedUser->resetPeriodIfRolled();

            foreach ($candidates as $game) {
                $limit = $lockedUser->quotaLimit();
                if ($limit !== null && $lockedUser->analysis_quota_used >= $limit) {
                    break;
                }

                if ($limit !== null) {
                    $lockedUser->analysis_quota_used++;
                }

                $game->analysis_status = AnalysisStatus::Queued;
                $game->save();

                AnalyseGameJob::dispatch($game->id)->afterCommit();
                $dispatched++;
            }

            $lockedUser->save();
        });

        Log::info('QueueRecentAnalysisJob: queued analysis', [
            'connected_account_id' => $this->connectedAccountId,
            'count'                => $dispatched,
        ]);
    }
}
