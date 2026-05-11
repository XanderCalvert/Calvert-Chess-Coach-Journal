<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameListTest extends TestCase
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
            'white_player'    => 'White Player',
            'black_player'    => 'Black Player',
            'result'          => 'white',
            'user_colour'     => 'white',
            'played_at'       => now(),
            'eco_code'        => 'B00',
            'opening_name'    => 'Test Opening',
            'move_count'      => 1,
            'analysis_status' => AnalysisStatus::Pending,
            'imported_from'   => 'paste',
        ], $overrides));
    }

    public function test_returns_200_with_empty_array_when_no_games(): void
    {
        $response = $this->getJson('/api/v1/games')->assertStatus(200);

        $this->assertEmpty($response->json('data'));
        $this->assertArrayHasKey('quota', $response->json());
    }

    public function test_returns_all_games_for_authenticated_user(): void
    {
        $this->createGame(['white_player' => 'Morphy']);
        $this->createGame(['white_player' => 'Kasparov']);

        $response = $this->getJson('/api/v1/games')->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_each_game_has_required_fields(): void
    {
        $this->createGame();

        $game = $this->getJson('/api/v1/games')->assertStatus(200)->json('data.0');

        foreach (['id', 'white_player', 'black_player', 'result', 'played_at', 'eco_code', 'opening_name', 'move_count', 'analysis_status', 'accuracy_pct', 'blunder_count', 'mistake_count', 'inaccuracy_count'] as $field) {
            $this->assertArrayHasKey($field, $game, "Missing field: {$field}");
        }
    }

    public function test_analysis_status_is_string_value(): void
    {
        $this->createGame(['analysis_status' => AnalysisStatus::Analysed]);

        $game = $this->getJson('/api/v1/games')->json('data.0');

        $this->assertSame('analysed', $game['analysis_status']);
    }

    public function test_result_is_string_value(): void
    {
        $this->createGame(['result' => 'draw']);

        $game = $this->getJson('/api/v1/games')->json('data.0');

        $this->assertSame('draw', $game['result']);
    }

    public function test_games_ordered_by_played_at_descending(): void
    {
        $this->createGame(['white_player' => 'Older', 'played_at' => now()->subDays(5)]);
        $this->createGame(['white_player' => 'Newer', 'played_at' => now()]);

        $games = $this->getJson('/api/v1/games')->json('data');

        $this->assertSame('Newer', $games[0]['white_player']);
        $this->assertSame('Older', $games[1]['white_player']);
    }

    public function test_does_not_include_moves(): void
    {
        $this->createGame();

        $game = $this->getJson('/api/v1/games')->json('data.0');

        $this->assertArrayNotHasKey('moves', $game);
    }

    public function test_does_not_return_other_users_games(): void
    {
        $otherUser = User::factory()->create();
        Game::create([
            'user_id'         => $otherUser->id,
            'pgn_raw'         => '[White "A"][Black "B"][Result "1-0"] 1.e4 1-0',
            'white_player'    => 'OtherUser',
            'black_player'    => 'Opponent',
            'result'          => 'white',
            'user_colour'     => 'white',
            'played_at'       => now(),
            'eco_code'        => 'B00',
            'opening_name'    => 'Test Opening',
            'move_count'      => 1,
            'analysis_status' => AnalysisStatus::Pending,
            'imported_from'   => 'paste',
        ]);

        $this->createGame(['white_player' => 'MyGame']);

        $games = $this->getJson('/api/v1/games')->assertStatus(200)->json('data');

        $this->assertCount(1, $games);
        $this->assertSame('MyGame', $games[0]['white_player']);
    }

    public function test_response_includes_quota_for_free_user(): void
    {
        $this->user->update(['analysis_quota_used' => 3]);

        $response = $this->getJson('/api/v1/games')->assertStatus(200);

        $response->assertJsonStructure(['quota' => ['quota_limit', 'quota_used', 'quota_remaining', 'quota_period_start']]);
        $this->assertSame(10, $response->json('quota.quota_limit'));
        $this->assertSame(3, $response->json('quota.quota_used'));
        $this->assertSame(7, $response->json('quota.quota_remaining'));
    }

    public function test_response_includes_subscription_tier(): void
    {
        $this->user->update(['subscription_tier' => 'premium']);

        $response = $this->getJson('/api/v1/games')->assertStatus(200);

        $this->assertSame('premium', $response->json('subscription_tier'));
    }
}
