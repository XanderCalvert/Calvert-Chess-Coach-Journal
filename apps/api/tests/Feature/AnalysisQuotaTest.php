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

class AnalysisQuotaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
        config(['chess.free_monthly_analysis_quota' => 10]);
        $this->user = User::factory()->create([
            'subscription_tier'   => 'free',
            'analysis_quota_used' => 0,
            'quota_period_start'  => now()->startOfMonth()->toDateString(),
        ]);
        $this->actingAs($this->user, 'sanctum');
    }

    private function createGame(array $overrides = []): Game
    {
        return Game::create(array_merge([
            'user_id'         => $this->user->id,
            'pgn_raw'         => '[White "A"][Black "B"][Result "1-0"] 1.e4 1-0',
            'white_player'    => 'A',
            'black_player'    => 'B',
            'result'          => 'white',
            'user_colour'     => 'white',
            'played_at'       => now(),
            'eco_code'        => 'C20',
            'opening_name'    => 'King Pawn Game',
            'move_count'      => 1,
            'analysis_status' => AnalysisStatus::Pending,
            'imported_from'   => 'paste',
            'share_code'      => substr(md5(uniqid()), 0, 8),
        ], $overrides));
    }

    // --- Endpoint quota enforcement ---

    public function test_free_user_can_analyse_when_quota_available(): void
    {
        Queue::fake();
        $game = $this->createGame();

        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(202);

        $this->user->refresh();
        $this->assertSame(1, $this->user->analysis_quota_used);
    }

    public function test_free_user_at_quota_limit_gets_422(): void
    {
        Queue::fake();
        $this->user->update(['analysis_quota_used' => 10]);
        $game = $this->createGame();

        $response = $this->postJson("/api/v1/games/{$game->id}/analyse");

        $response->assertStatus(422)
            ->assertJsonFragment(['quota_limit' => 10, 'quota_used' => 10]);

        Queue::assertNothingPushed();

        $game->refresh();
        $this->assertSame(AnalysisStatus::Pending, $game->analysis_status);
    }

    public function test_premium_user_bypasses_quota(): void
    {
        Queue::fake();
        $this->user->update([
            'subscription_tier'   => 'premium',
            'analysis_quota_used' => 9999,
        ]);
        $game = $this->createGame();

        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(202);

        Queue::assertPushed(AnalyseGameJob::class);
        // Premium users do not increment quota_used
        $this->user->refresh();
        $this->assertSame(9999, $this->user->analysis_quota_used);
    }

    public function test_quota_not_consumed_when_game_already_queued(): void
    {
        Queue::fake();
        $game = $this->createGame(['analysis_status' => AnalysisStatus::Queued]);

        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(409);

        $this->user->refresh();
        $this->assertSame(0, $this->user->analysis_quota_used);
    }

    public function test_quota_not_consumed_for_another_users_404(): void
    {
        Queue::fake();
        $other = User::factory()->create();
        $game = $this->createGame();
        $game->update(['user_id' => $other->id]);

        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(404);

        $this->user->refresh();
        $this->assertSame(0, $this->user->analysis_quota_used);
    }

    public function test_quota_period_resets_when_rolled_over(): void
    {
        Queue::fake();
        $this->user->update([
            'analysis_quota_used' => 10,
            'quota_period_start'  => now()->subMonth()->startOfMonth()->toDateString(),
        ]);
        $game = $this->createGame();

        // Even though quota_used = 10, the period has rolled so it should reset and allow analysis
        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(202);

        $this->user->refresh();
        $this->assertSame(1, $this->user->analysis_quota_used);
        $this->assertSame(now()->startOfMonth()->toDateString(), $this->user->quota_period_start->toDateString());
    }

    // --- Games list includes quota ---

    public function test_games_index_includes_quota_for_free_user(): void
    {
        $this->user->update(['analysis_quota_used' => 3]);

        $response = $this->getJson('/api/v1/games')->assertStatus(200);

        $response->assertJsonStructure(['data', 'quota' => ['quota_limit', 'quota_used', 'quota_remaining', 'quota_period_start']]);
        $response->assertJsonFragment(['quota_limit' => 10, 'quota_used' => 3, 'quota_remaining' => 7]);
    }

    public function test_games_index_quota_null_for_premium_user(): void
    {
        $this->user->update(['subscription_tier' => 'premium']);

        $response = $this->getJson('/api/v1/games')->assertStatus(200);

        $response->assertJsonFragment(['quota_limit' => null, 'quota_remaining' => null]);
    }

    // --- QueueRecentAnalysisJob respects quota ---

    public function test_queue_recent_analysis_respects_quota(): void
    {
        Queue::fake();
        config(['chess.auto_analyse_on_sync' => 5, 'chess.free_monthly_analysis_quota' => 10]);

        $this->user->update(['analysis_quota_used' => 8]);

        $account = ConnectedAccount::create([
            'user_id'             => $this->user->id,
            'platform'            => 'chesscom',
            'username'            => 'testplayer',
            'normalised_username' => 'testplayer',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Game::create([
                'user_id'              => $this->user->id,
                'connected_account_id' => $account->id,
                'pgn_raw'              => '[White "A"][Black "B"][Result "1-0"] 1.e4 1-0',
                'white_player'         => 'A',
                'black_player'         => 'B',
                'result'               => 'white',
                'user_colour'          => 'white',
                'played_at'            => now()->subDays($i),
                'eco_code'             => 'C20',
                'opening_name'         => 'King Pawn',
                'move_count'           => 1,
                'analysis_status'      => AnalysisStatus::Pending,
                'imported_from'        => 'chesscom',
                'share_code'           => "qrjob0{$i}00",
            ]);
        }

        (new QueueRecentAnalysisJob($account->id))->handle();

        // 10 limit - 8 used = 2 remaining; only 2 of 5 should be dispatched
        Queue::assertPushed(AnalyseGameJob::class, 2);

        $this->user->refresh();
        $this->assertSame(10, $this->user->analysis_quota_used);
    }

    public function test_queue_recent_analysis_premium_dispatches_all(): void
    {
        Queue::fake();
        config(['chess.auto_analyse_on_sync' => 5]);

        $this->user->update(['subscription_tier' => 'premium', 'analysis_quota_used' => 9999]);

        $account = ConnectedAccount::create([
            'user_id'             => $this->user->id,
            'platform'            => 'chesscom',
            'username'            => 'premiumplayer',
            'normalised_username' => 'premiumplayer',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Game::create([
                'user_id'              => $this->user->id,
                'connected_account_id' => $account->id,
                'pgn_raw'              => '[White "A"][Black "B"][Result "1-0"] 1.e4 1-0',
                'white_player'         => 'A',
                'black_player'         => 'B',
                'result'               => 'white',
                'user_colour'          => 'white',
                'played_at'            => now()->subDays($i),
                'eco_code'             => 'C20',
                'opening_name'         => 'King Pawn',
                'move_count'           => 1,
                'analysis_status'      => AnalysisStatus::Pending,
                'imported_from'        => 'chesscom',
                'share_code'           => "premium0{$i}0",
            ]);
        }

        (new QueueRecentAnalysisJob($account->id))->handle();

        Queue::assertPushed(AnalyseGameJob::class, 5);
        // Premium: quota_used unchanged
        $this->user->refresh();
        $this->assertSame(9999, $this->user->analysis_quota_used);
    }

    public function test_queue_recent_analysis_resets_period_before_consuming_quota(): void
    {
        Queue::fake();
        config(['chess.auto_analyse_on_sync' => 5, 'chess.free_monthly_analysis_quota' => 10]);

        $this->user->update([
            'analysis_quota_used' => 10,
            'quota_period_start'  => now()->subMonth()->startOfMonth()->toDateString(),
        ]);

        $account = ConnectedAccount::create([
            'user_id'             => $this->user->id,
            'platform'            => 'chesscom',
            'username'            => 'rolloverplayer',
            'normalised_username' => 'rolloverplayer',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            Game::create([
                'user_id'              => $this->user->id,
                'connected_account_id' => $account->id,
                'pgn_raw'              => '[White "A"][Black "B"][Result "1-0"] 1.e4 1-0',
                'white_player'         => 'A',
                'black_player'         => 'B',
                'result'               => 'white',
                'user_colour'          => 'white',
                'played_at'            => now()->subDays($i),
                'eco_code'             => 'C20',
                'opening_name'         => 'King Pawn',
                'move_count'           => 1,
                'analysis_status'      => AnalysisStatus::Pending,
                'imported_from'        => 'chesscom',
                'share_code'           => "rollovr0{$i}",
            ]);
        }

        (new QueueRecentAnalysisJob($account->id))->handle();

        Queue::assertPushed(AnalyseGameJob::class, 3);

        $this->user->refresh();
        $this->assertSame(3, $this->user->analysis_quota_used);
        $this->assertSame(now()->startOfMonth()->toDateString(), $this->user->quota_period_start->toDateString());
    }
}
