<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyseEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
        $this->user = User::factory()->create();
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

    public function test_returns_202_and_queues_job_for_pending_game(): void
    {
        Queue::fake();
        $game = $this->createGame();

        $this->postJson("/api/v1/games/{$game->id}/analyse")
            ->assertStatus(202);

        Queue::assertPushed(AnalyseGameJob::class, fn ($job) => $job->gameId === $game->id);

        $game->refresh();
        $this->assertSame(AnalysisStatus::Queued, $game->analysis_status);
        $this->assertNotNull($game->analysis_requested_at);
    }

    public function test_sets_analysis_requested_at_timestamp(): void
    {
        Queue::fake();
        $game = $this->createGame();

        $before = now()->subSecond();
        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(202);
        $after = now()->addSecond();

        $game->refresh();
        $this->assertTrue($game->analysis_requested_at->between($before, $after));
    }

    public function test_returns_409_for_queued_game(): void
    {
        Queue::fake();
        $game = $this->createGame(['analysis_status' => AnalysisStatus::Queued]);

        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(409);
        Queue::assertNothingPushed();
    }

    public function test_returns_409_for_analysing_game(): void
    {
        Queue::fake();
        $game = $this->createGame(['analysis_status' => AnalysisStatus::Analysing]);

        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(409);
        Queue::assertNothingPushed();
    }

    public function test_returns_409_for_analysed_game(): void
    {
        Queue::fake();
        $game = $this->createGame(['analysis_status' => AnalysisStatus::Analysed]);

        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(409);
        Queue::assertNothingPushed();
    }

    public function test_requeues_failed_game(): void
    {
        Queue::fake();
        $game = $this->createGame(['analysis_status' => AnalysisStatus::Failed]);

        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(202);
        Queue::assertPushed(AnalyseGameJob::class);

        $game->refresh();
        $this->assertSame(AnalysisStatus::Queued, $game->analysis_status);
    }

    public function test_returns_404_for_another_users_game(): void
    {
        Queue::fake();
        $other = User::factory()->create();
        $game = Game::create([
            'user_id'         => $other->id,
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
            'share_code'      => 'other0001',
        ]);

        $this->postJson("/api/v1/games/{$game->id}/analyse")->assertStatus(404);
        Queue::assertNothingPushed();
    }
}
