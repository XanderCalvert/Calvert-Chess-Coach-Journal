<?php

namespace Tests\Feature;

use App\Jobs\AnalyseGameJob;
use App\Jobs\ComputeWeaknessProfileJob;
use App\Models\ConnectedAccount;
use App\Models\Game;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyseGameJobDispatchesWeaknessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    private function createAccount(): ConnectedAccount
    {
        return ConnectedAccount::create([
            'user_id'             => DevUserSeeder::UUID,
            'platform'            => 'chesscom',
            'username'            => 'TestPlayer',
            'normalised_username' => 'testplayer',
            'sync_status'         => 'never_synced',
        ]);
    }

    private function createGame(?ConnectedAccount $account = null): Game
    {
        return Game::create([
            'user_id'              => DevUserSeeder::UUID,
            'connected_account_id' => $account?->id,
            'pgn_raw'              => '*',
            'white_player'         => 'TestPlayer',
            'black_player'         => 'Opponent',
            'result'               => 'white',
            'user_colour'          => 'white',
            'played_at'            => now(),
            'eco_code'             => 'C20',
            'opening_name'         => 'King Pawn',
            'move_count'           => 0,
            'analysis_status'      => 'pending',
            'imported_from'        => 'chesscom',
            'share_code'           => 'testcode',
        ]);
    }

    public function test_dispatches_compute_weakness_profile_when_connected_account_set(): void
    {
        Queue::fake();
        $account = $this->createAccount();
        $game    = $this->createGame($account);

        (new AnalyseGameJob($game->id))->handle();

        Queue::assertPushed(ComputeWeaknessProfileJob::class, function ($job) use ($account) {
            return $job->connectedAccountId === $account->id;
        });
    }

    public function test_does_not_dispatch_compute_weakness_profile_when_no_connected_account(): void
    {
        Queue::fake();
        $game = $this->createGame(null);

        (new AnalyseGameJob($game->id))->handle();

        Queue::assertNotPushed(ComputeWeaknessProfileJob::class);
    }
}
