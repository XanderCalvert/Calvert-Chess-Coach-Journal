<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DevUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClaimDevDataCommand extends Command
{
    protected $signature = 'chess:claim-dev-data {email : Email address of the real user to claim dev data for}';

    protected $description = 'Reassign all dev-seed data (games, connected accounts) from the dev UUID to a real user';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        $devUuid = DevUserSeeder::UUID;

        $tables = [
            'games'              => 'user_id',
            'connected_accounts' => 'user_id',
        ];

        foreach ($tables as $table => $column) {
            $count = DB::table($table)
                ->where($column, $devUuid)
                ->update([$column => $user->id]);

            $this->line("  {$table}: {$count} row(s) claimed");
        }

        $this->info("Done. All dev data reassigned to {$email} ({$user->id}).");

        return self::SUCCESS;
    }
}
