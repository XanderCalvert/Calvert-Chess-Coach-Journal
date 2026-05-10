<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        $limit = config('chess.auto_analyse_on_sync', 5);

        $games = Game::where('connected_account_id', $this->connectedAccountId)
            ->where('analysis_status', AnalysisStatus::Pending)
            ->orderByDesc('played_at')
            ->limit($limit)
            ->get();

        foreach ($games as $game) {
            $game->update(['analysis_status' => AnalysisStatus::Running]);
            AnalyseGameJob::dispatch($game->id)->afterCommit();
        }

        Log::info('QueueRecentAnalysisJob: queued analysis', [
            'connected_account_id' => $this->connectedAccountId,
            'count'                => $games->count(),
        ]);
    }
}
