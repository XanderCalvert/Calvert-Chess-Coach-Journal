<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReanalyseCompletedGamesEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.debug', false);
        $this->seed(DevUserSeeder::class);
        $this->user = User::factory()->create([
            'subscription_tier'   => 'free',
            'analysis_quota_used' => 0,
            'quota_period_start'  => now()->startOfMonth()->toDateString(),
        ]);
        config(['chess.free_monthly_analysis_quota' => 10]);
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

    public function test_queues_only_analysed_games_with_force_jobs(): void
    {
        Queue::fake();
        $g1 = $this->createGame(['analysis_status' => AnalysisStatus::Analysed, 'played_at' => now()->subDay()]);
        $g2 = $this->createGame(['analysis_status' => AnalysisStatus::Analysed, 'played_at' => now()]);
        $this->createGame(['analysis_status' => AnalysisStatus::Pending]);

        $this->postJson('/api/v1/games/reanalyse-completed')->assertStatus(202);

        Queue::assertPushed(AnalyseGameJob::class, 2);
        Queue::assertPushed(AnalyseGameJob::class, fn (AnalyseGameJob $j) => $j->gameId === $g2->id && $j->force === true);
        Queue::assertPushed(AnalyseGameJob::class, fn (AnalyseGameJob $j) => $j->gameId === $g1->id && $j->force === true);

        $g1->refresh();
        $g2->refresh();
        $this->assertSame(AnalysisStatus::Queued, $g1->analysis_status);
        $this->assertSame(AnalysisStatus::Queued, $g2->analysis_status);

        $this->user->refresh();
        $this->assertSame(2, $this->user->analysis_quota_used);
    }

    public function test_returns_200_when_no_analysed_games(): void
    {
        Queue::fake();
        $this->createGame(['analysis_status' => AnalysisStatus::Pending]);

        $this->postJson('/api/v1/games/reanalyse-completed')
            ->assertStatus(200)
            ->assertJsonFragment(['queued' => 0, 'eligible' => 0]);

        Queue::assertNothingPushed();
    }

    public function test_returns_422_when_quota_blocks_all(): void
    {
        Queue::fake();
        $this->user->update(['analysis_quota_used' => 10]);
        $this->createGame(['analysis_status' => AnalysisStatus::Analysed]);

        $this->postJson('/api/v1/games/reanalyse-completed')
            ->assertStatus(422)
            ->assertJsonFragment(['queued' => 0, 'eligible' => 1]);

        Queue::assertNothingPushed();
    }

    public function test_partial_queue_when_quota_insufficient(): void
    {
        Queue::fake();
        $this->user->update(['analysis_quota_used' => 9]);
        $this->createGame(['analysis_status' => AnalysisStatus::Analysed, 'played_at' => now()->subDays(2)]);
        $this->createGame(['analysis_status' => AnalysisStatus::Analysed, 'played_at' => now()->subDay()]);
        $this->createGame(['analysis_status' => AnalysisStatus::Analysed, 'played_at' => now()]);

        $this->postJson('/api/v1/games/reanalyse-completed')->assertStatus(202);

        Queue::assertPushed(AnalyseGameJob::class, 1);
        $this->user->refresh();
        $this->assertSame(10, $this->user->analysis_quota_used);
    }

    public function test_app_debug_true_skips_quota_and_queues_all_eligible(): void
    {
        Config::set('app.debug', true);
        Queue::fake();
        $this->user->update(['analysis_quota_used' => 10]);
        $this->createGame(['analysis_status' => AnalysisStatus::Analysed, 'played_at' => now()->subDay()]);
        $this->createGame(['analysis_status' => AnalysisStatus::Analysed, 'played_at' => now()]);

        $response = $this->postJson('/api/v1/games/reanalyse-completed');
        $response->assertStatus(202)
            ->assertJsonFragment(['queued' => 2, 'eligible' => 2, 'quota_bypassed' => true, 'blocked_by_quota' => 0]);

        Queue::assertPushed(AnalyseGameJob::class, 2);
        $this->user->refresh();
        $this->assertSame(10, $this->user->analysis_quota_used);
    }
}
