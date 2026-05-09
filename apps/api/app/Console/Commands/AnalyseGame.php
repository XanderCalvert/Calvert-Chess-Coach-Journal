<?php

namespace App\Console\Commands;

use App\Jobs\AnalyseGameJob;
use Illuminate\Console\Command;
use Throwable;

class AnalyseGame extends Command
{
    protected $signature = 'chess:analyse {game_id : UUID of the game to analyse} {--force : Re-analyse even if already complete}';

    protected $description = 'Run Stockfish analysis synchronously on a game (for local testing)';

    public function handle(): int
    {
        $gameId = $this->argument('game_id');
        $force  = (bool) $this->option('force');

        $this->info("Starting analysis for game {$gameId}" . ($force ? ' (forced)' : '') . ' …');

        try {
            AnalyseGameJob::dispatchSync($gameId, force: $force);
            $this->info('Analysis complete. Check storage/logs/laravel.log for move-by-move detail.');
        } catch (Throwable $e) {
            $this->error("Analysis failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
