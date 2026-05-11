<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Jobs\SyncChessComAccountJob;
use App\Models\ConnectedAccount;
use Illuminate\Console\Command;
use Throwable;
use ValueError;

class SyncConnectedAccount extends Command
{
    protected $signature = 'chess:sync-connected-account
                            {platform : Platform key (chesscom)}
                            {username : Username on that platform}
                            {--create : Create the connected_accounts row if it does not exist}
                            {--recent : Only the recent-game window (same as web Sync), not full archives}
                            {--sync : Run the sync job in this process (HTTP + queue import jobs); otherwise only dispatch the sync job to the queue}';

    protected $description = 'Fetch platform archives and queue game imports for a connected account (Chess.com full history by default)';

    public function handle(): int
    {
        try {
            $platform = Platform::from($this->argument('platform'));
        } catch (ValueError) {
            $this->error('Invalid platform. Use: chesscom');

            return self::FAILURE;
        }

        if ($platform !== Platform::Chesscom) {
            $this->error('Only chesscom is supported for archive sync right now.');

            return self::FAILURE;
        }

        $username   = (string) $this->argument('username');
        $normalised = strtolower($username);

        $account = ConnectedAccount::query()
            ->where('platform', $platform)
            ->where('normalised_username', $normalised)
            ->first();

        if (! $account) {
            if (! $this->option('create')) {
                $this->error("No connected account for [{$platform->value}] {$username}. Re-run with --create.");

                return self::FAILURE;
            }

            $account = ConnectedAccount::create([
                'user_id'             => null,
                'platform'            => $platform,
                'username'            => $username,
                'normalised_username' => $normalised,
                'sync_status'         => SyncStatus::NeverSynced,
            ]);
            $this->info('Created connected account row.');
        }

        $account->update([
            'sync_status' => SyncStatus::Syncing,
            'username'    => $username,
        ]);

        $fullArchive = ! $this->option('recent');

        try {
            if ($this->option('sync')) {
                $this->info('Running sync job synchronously…');
                SyncChessComAccountJob::dispatchSync($account->id, $fullArchive);
                $this->info('Sync job finished. Import jobs were queued; run `php artisan queue:work` unless QUEUE_CONNECTION=sync.');
            } else {
                SyncChessComAccountJob::dispatch($account->id, $fullArchive);
                $this->info('Dispatched sync job. Run `php artisan queue:work` to fetch archives and queue imports.');
            }
        } catch (Throwable $e) {
            $this->error("Sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
