<?php

namespace App\Console\Commands;

use App\Enums\AnalysisStatus;
use App\Jobs\ComputeWeaknessProfileJob;
use App\Models\ConnectedAccount;
use Illuminate\Console\Command;

class ComputeWeaknessProfiles extends Command
{
    protected $signature = 'chess:compute-weakness-profiles
        {--account= : UUID of a specific connected account}
        {--all : Run for all accounts with at least one analysed game}
        {--dry-run : Show what would be computed without inserting rows}';

    protected $description = 'Compute weakness profile snapshots for connected accounts';

    public function handle(): int
    {
        $accountId = $this->option('account');
        $all       = $this->option('all');
        $dryRun    = $this->option('dry-run');

        if (! $accountId && ! $all) {
            $this->error('Specify --account=<id> or --all.');
            $this->line('Options:');
            $this->line('  --account=<id>   Compute profile for a specific connected account');
            $this->line('  --all            Compute profiles for all accounts with analysed games');
            $this->line('  --dry-run        Preview output without writing to the database');
            return self::FAILURE;
        }

        if ($accountId) {
            return $this->handleSingle($accountId, $dryRun);
        }

        return $this->handleAll($dryRun);
    }

    private function handleSingle(string $accountId, bool $dryRun): int
    {
        $account = ConnectedAccount::find($accountId);

        if (! $account) {
            $this->error("Connected account {$accountId} not found.");
            return self::FAILURE;
        }

        $this->info("Computing weakness profile for {$account->platform->value}:{$account->username}" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        if ($dryRun) {
            $this->runDryRun(collect([$account]));
            return self::SUCCESS;
        }

        ComputeWeaknessProfileJob::dispatch($accountId);
        $this->info("Dispatched profile computation for {$account->platform->value}:{$account->username}.");

        return self::SUCCESS;
    }

    private function handleAll(bool $dryRun): int
    {
        $this->info('Computing weakness profiles for all eligible accounts' . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $computed = 0;
        $skipped  = 0;
        $errors   = 0;

        $query = ConnectedAccount::whereHas('games', fn ($q) => $q->where('analysis_status', AnalysisStatus::Analysed));
        $total = $query->count();

        if ($total === 0) {
            $this->warn('No accounts with analysed games found.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $query->get()->tap(fn ($accounts) => $this->runDryRun($accounts));
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(50, function ($accounts) use (&$computed, &$skipped, &$errors, $bar) {
            foreach ($accounts as $account) {
                try {
                    ComputeWeaknessProfileJob::dispatch($account->id);
                    $computed++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->warn("Account {$account->id}: {$e->getMessage()}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Dispatched: {$computed}, Skipped: {$skipped}, Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runDryRun(\Illuminate\Support\Collection $accounts): void
    {
        $windowSize      = config('chess.weakness_profile_window', 20);
        $openingMinGames = config('chess.weakness_opening_min_games', 3);
        $motifMinGames   = config('chess.weakness_motif_min_games', 2);

        foreach ($accounts as $account) {
            $games = $account->games()
                ->where('analysis_status', AnalysisStatus::Analysed)
                ->orderByDesc('played_at')
                ->limit($windowSize)
                ->count();

            $this->line(json_encode([
                'account_id'           => $account->id,
                'platform'             => $account->platform->value,
                'username'             => $account->username,
                'analysed_games_count' => $games,
                'sufficient_data'      => $games >= 3,
                'window_size'          => $windowSize,
                'opening_min_games'    => $openingMinGames,
                'motif_min_games'      => $motifMinGames,
                'would_insert'         => $games > 0,
            ]));
        }
    }
}
