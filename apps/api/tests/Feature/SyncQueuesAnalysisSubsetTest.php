<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Jobs\AnalyseGameJob;
use App\Jobs\QueueRecentAnalysisJob;
use App\Models\ConnectedAccount;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncQueuesAnalysisSubsetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ConnectedAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
        $this->user = User::factory()->create();
        $this->account = ConnectedAccount::create([
            'user_id'              => $this->user->id,
            'platform'             => 'chesscom',
            'username'             => 'testplayer',
            'normalised_username'  => 'testplayer',
        ]);
    }

    private function createPendingGame(string $shareCode): Game
    {
        return Game::create([
            'user_id'              => $this->user->id,
            'connected_account_id' => $this->account->id,
            'pgn_raw'              => '[White "A"][Black "B"][Result "1-0"] 1.e4 1-0',
            'white_player'         => 'A',
            'black_player'         => 'B',
            'result'               => 'white',
            'user_colour'          => 'white',
            'played_at'            => now(),
            'eco_code'             => 'C20',
            'opening_name'         => 'King Pawn',
            'move_count'           => 1,
            'analysis_status'      => AnalysisStatus::Pending,
            'imported_from'        => 'chesscom',
            'share_code'           => $shareCode,
        ]);
    }

    public function test_queues_only_recent_subset_when_more_games_than_limit(): void
    {
        config(['chess.auto_analyse_on_sync' => 3]);
        Queue::fake();

        for ($i = 1; $i <= 5; $i++) {
            $this->createPendingGame("game0000{$i}");
        }

        (new QueueRecentAnalysisJob($this->account->id))->handle();

        Queue::assertPushed(AnalyseGameJob::class, 3);

        $queued = Game::where('connected_account_id', $this->account->id)
            ->where('analysis_status', AnalysisStatus::Queued)
            ->count();
        $this->assertSame(3, $queued);

        $stillPending = Game::where('connected_account_id', $this->account->id)
            ->where('analysis_status', AnalysisStatus::Pending)
            ->count();
        $this->assertSame(2, $stillPending);
    }

    public function test_queues_all_games_when_fewer_than_limit(): void
    {
        config(['chess.auto_analyse_on_sync' => 5]);
        Queue::fake();

        $this->createPendingGame('game00001');
        $this->createPendingGame('game00002');

        (new QueueRecentAnalysisJob($this->account->id))->handle();

        Queue::assertPushed(AnalyseGameJob::class, 2);

        $queued = Game::where('connected_account_id', $this->account->id)
            ->where('analysis_status', AnalysisStatus::Queued)
            ->count();
        $this->assertSame(2, $queued);
    }

    public function test_does_nothing_when_no_pending_games(): void
    {
        Queue::fake();

        (new QueueRecentAnalysisJob($this->account->id))->handle();

        Queue::assertNothingPushed();
    }
}
