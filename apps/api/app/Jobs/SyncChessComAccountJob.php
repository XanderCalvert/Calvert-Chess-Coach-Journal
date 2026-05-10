<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\ConnectedAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncChessComAccountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [120];

    public function __construct(
        public readonly string $connectedAccountId,
        /** When true, queue imports for every game in every archive month; when false, same cap as web sync (recent window). */
        public readonly bool $fullArchive = false,
    ) {}

    public function handle(): void
    {
        $account = ConnectedAccount::find($this->connectedAccountId);

        if (! $account) {
            return;
        }

        $username = $account->username;
        $http = Http::withHeaders(['User-Agent' => 'CalvertChessCoach/1.0'])->timeout(15);

        // Fetch and update ratings
        $statsResponse = $http->get("https://api.chess.com/pub/player/{$username}/stats");

        if ($statsResponse->status() === 404) {
            $account->update(['sync_status' => SyncStatus::Failed->value]);
            Log::warning("SyncChessComAccountJob: Chess.com username not found", [
                'connected_account_id' => $this->connectedAccountId,
                'username'             => $username,
            ]);
            // Return without throwing — 404 is not retriable
            $this->delete();
            return;
        }

        $statsResponse->throw();
        $stats = $statsResponse->json();

        $account->update([
            'rapid_rating'  => $stats['chess_rapid']['last']['rating'] ?? null,
            'blitz_rating'  => $stats['chess_blitz']['last']['rating'] ?? null,
            'bullet_rating' => $stats['chess_bullet']['last']['rating'] ?? null,
            'daily_rating'  => $stats['chess_daily']['last']['rating'] ?? null,
        ]);

        // Fetch archive list
        $archivesResponse = $http->get("https://api.chess.com/pub/player/{$username}/games/archives");
        $archivesResponse->throw();

        $archives = $archivesResponse->json('archives', []);

        if (empty($archives)) {
            $account->update([
                'sync_status'   => SyncStatus::Synced->value,
                'last_synced_at' => now(),
            ]);
            return;
        }

        if ($this->fullArchive) {
            foreach (array_reverse($archives) as $archiveUrl) {
                $gamesResponse = $http->get($archiveUrl);
                $gamesResponse->throw();

                foreach ($gamesResponse->json('games', []) as $game) {
                    ImportExternalGameJob::dispatch($account->id, $game);
                }
            }
        } else {
            // Walk archives newest→oldest, collecting up to 20 games across months (web sync parity)
            $reversedArchives = array_reverse($archives);
            $recent = [];

            foreach ($reversedArchives as $archiveUrl) {
                $gamesResponse = $http->get($archiveUrl);
                $gamesResponse->throw();

                $monthGames = $gamesResponse->json('games', []);

                // Prepend so the final list is still oldest→newest within the batch
                $needed = 20 - count($recent);
                $recent = array_merge(array_slice($monthGames, -$needed), $recent);

                if (count($recent) >= 20) {
                    break;
                }
            }

            foreach ($recent as $game) {
                ImportExternalGameJob::dispatch($account->id, $game);
            }
        }

        $account->update([
            'sync_status'    => SyncStatus::Synced->value,
            'last_synced_at' => now(),
        ]);

        QueueRecentAnalysisJob::dispatch($this->connectedAccountId)
            ->delay(now()->addSeconds(60));
    }

    public function failed(Throwable $e): void
    {
        ConnectedAccount::where('id', $this->connectedAccountId)
            ->update(['sync_status' => SyncStatus::Failed->value]);

        Log::error('SyncChessComAccountJob failed', [
            'connected_account_id' => $this->connectedAccountId,
            'error'                => $e->getMessage(),
        ]);
    }
}
