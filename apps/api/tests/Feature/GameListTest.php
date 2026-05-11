<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Models\Game;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    private function createGame(array $overrides = []): Game
    {
        return Game::create(array_merge([
            'user_id'         => DevUserSeeder::UUID,
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
        $this->getJson('/api/v1/games')
             ->assertStatus(200)
             ->assertExactJson([]);
    }

    public function test_returns_all_games_for_dev_user(): void
    {
        $this->createGame(['white_player' => 'Morphy']);
        $this->createGame(['white_player' => 'Kasparov']);

        $response = $this->getJson('/api/v1/games')->assertStatus(200);

        $this->assertCount(2, $response->json());
    }

    public function test_each_game_has_required_fields(): void
    {
        $this->createGame();

        $game = $this->getJson('/api/v1/games')->assertStatus(200)->json('0');

        foreach (['id', 'white_player', 'black_player', 'result', 'played_at', 'eco_code', 'opening_name', 'move_count', 'analysis_status', 'accuracy_pct', 'blunder_count', 'mistake_count', 'inaccuracy_count'] as $field) {
            $this->assertArrayHasKey($field, $game, "Missing field: {$field}");
        }
    }

    public function test_analysis_status_is_string_value(): void
    {
        $this->createGame(['analysis_status' => AnalysisStatus::Complete]);

        $game = $this->getJson('/api/v1/games')->json('0');

        $this->assertSame('complete', $game['analysis_status']);
    }

    public function test_result_is_string_value(): void
    {
        $this->createGame(['result' => 'draw']);

        $game = $this->getJson('/api/v1/games')->json('0');

        $this->assertSame('draw', $game['result']);
    }

    public function test_games_ordered_by_played_at_descending(): void
    {
        $this->createGame(['white_player' => 'Older', 'played_at' => now()->subDays(5)]);
        $this->createGame(['white_player' => 'Newer', 'played_at' => now()]);

        $games = $this->getJson('/api/v1/games')->json();

        $this->assertSame('Newer', $games[0]['white_player']);
        $this->assertSame('Older', $games[1]['white_player']);
    }

    public function test_does_not_include_moves(): void
    {
        $this->createGame();

        $game = $this->getJson('/api/v1/games')->json('0');

        $this->assertArrayNotHasKey('moves', $game);
    }
}
