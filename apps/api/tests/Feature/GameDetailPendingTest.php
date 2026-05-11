<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameDetailPendingTest extends TestCase
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

    public function test_returns_200_for_pending_game_with_null_accuracy_and_empty_collections(): void
    {
        $game = $this->createGame();

        $response = $this->getJson("/api/v1/games/{$game->id}")
            ->assertStatus(200)
            ->assertJson([
                'analysis_status' => 'pending',
                'accuracy_pct'    => null,
                'blunder_count'   => null,
                'mistake_count'   => null,
                'inaccuracy_count' => null,
                'moves'           => [],
                'key_moments'     => [],
            ]);

        $this->assertIsArray($response->json('moves'));
        $this->assertIsArray($response->json('key_moments'));
    }

    public function test_returns_200_for_queued_game(): void
    {
        $game = $this->createGame(['analysis_status' => AnalysisStatus::Queued]);

        $this->getJson("/api/v1/games/{$game->id}")
            ->assertStatus(200)
            ->assertJson(['analysis_status' => 'queued']);
    }

    public function test_returns_200_for_analysing_game(): void
    {
        $game = $this->createGame(['analysis_status' => AnalysisStatus::Analysing]);

        $this->getJson("/api/v1/games/{$game->id}")
            ->assertStatus(200)
            ->assertJson(['analysis_status' => 'analysing']);
    }

    public function test_returns_200_for_failed_game(): void
    {
        $game = $this->createGame(['analysis_status' => AnalysisStatus::Failed]);

        $this->getJson("/api/v1/games/{$game->id}")
            ->assertStatus(200)
            ->assertJson(['analysis_status' => 'failed']);
    }
}
