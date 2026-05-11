<?php

namespace Tests\Feature;

use App\Jobs\ComputeWeaknessProfileJob;
use App\Models\ConnectedAccount;
use App\Models\Game;
use App\Models\WeaknessProfile;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComputeWeaknessProfilesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    private function createAccount(string $username = 'TestPlayer'): ConnectedAccount
    {
        return ConnectedAccount::create([
            'user_id'             => DevUserSeeder::UUID,
            'platform'            => 'chesscom',
            'username'            => $username,
            'normalised_username' => strtolower($username),
            'sync_status'         => 'never_synced',
        ]);
    }

    public function test_no_options_exits_with_failure_and_shows_help(): void
    {
        $this->artisan('chess:compute-weakness-profiles')
            ->assertFailed()
            ->expectsOutput('Specify --account=<id> or --all.');
    }

    public function test_account_option_dispatches_job_for_specific_account(): void
    {
        Queue::fake();
        $account = $this->createAccount();

        $this->artisan("chess:compute-weakness-profiles --account={$account->id}")
            ->assertSuccessful();

        Queue::assertPushed(ComputeWeaknessProfileJob::class, function ($job) use ($account) {
            return $job->connectedAccountId === $account->id;
        });
    }

    public function test_all_option_dispatches_jobs_for_all_accounts(): void
    {
        Queue::fake();
        $account1 = $this->createAccount('Player1');
        $account2 = $this->createAccount('Player2');

        foreach ([$account1, $account2] as $idx => $account) {
            Game::create([
                'user_id'              => DevUserSeeder::UUID,
                'connected_account_id' => $account->id,
                'pgn_raw'              => '*',
                'white_player'         => $account->username,
                'black_player'         => 'Opp',
                'result'               => 'white',
                'user_colour'          => 'white',
                'played_at'            => now(),
                'eco_code'             => 'C20',
                'opening_name'         => 'KP',
                'move_count'           => 0,
                'analysis_status'      => 'analysed',
                'imported_from'        => 'chesscom',
                'share_code'           => 'alltest0' . $idx,
            ]);
        }

        $this->artisan('chess:compute-weakness-profiles --all')
            ->assertSuccessful();

        Queue::assertPushed(ComputeWeaknessProfileJob::class, 2);
    }

    public function test_dry_run_does_not_insert_profile(): void
    {
        $account = $this->createAccount();
        Game::create([
            'user_id'              => DevUserSeeder::UUID,
            'connected_account_id' => $account->id,
            'pgn_raw'              => '*',
            'white_player'         => 'TestPlayer',
            'black_player'         => 'Opp',
            'result'               => 'white',
            'user_colour'          => 'white',
            'played_at'            => now(),
            'eco_code'             => 'C20',
            'opening_name'         => 'KP',
            'move_count'           => 0,
            'analysis_status'      => 'analysed',
            'imported_from'        => 'chesscom',
            'share_code'           => 'dryrn001',
        ]);

        $this->artisan("chess:compute-weakness-profiles --account={$account->id} --dry-run")
            ->assertSuccessful();

        $this->assertCount(0, WeaknessProfile::all());
    }
}
