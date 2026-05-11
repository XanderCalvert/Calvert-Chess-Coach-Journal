<?php

namespace App\Console\Commands;

use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use Illuminate\Console\Command;
use Throwable;

class ReanalyseGames extends Command
{
    protected $signature = 'chess:reanalyse
        {--all : Re-analyse all games}
        {--game_id=* : Re-analyse one or more game UUIDs}';

    protected $description = 'Re-run game analysis in bulk with force enabled';

    public function handle(): int
    {
        /** @var list<string> $gameIds */
        $gameIds = array_values(array_filter((array) $this->option('game_id')));
        $all = (bool) $this->option('all');

        if (! $all && empty($gameIds)) {
            $this->error('Provide --all or at least one --game_id option.');
            return self::FAILURE;
        }

        if ($all) {
            $gameIds = Game::query()->pluck('id')->all();
        } else {
            $gameIds = array_values(array_unique($gameIds));
        }

        if (empty($gameIds)) {
            $this->warn('No games matched the requested scope.');
            return self::SUCCESS;
        }

        $this->info('Re-analysing ' . count($gameIds) . ' game(s) with --force...');

        $processed = 0;
        $failed = 0;

        foreach ($gameIds as $gameId) {
            $this->line(" - {$gameId}");

            try {
                AnalyseGameJob::dispatchSync($gameId, force: true);
                $processed++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("   failed: {$e->getMessage()}");
            }
        }

        $this->info("Done. processed={$processed}, failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
