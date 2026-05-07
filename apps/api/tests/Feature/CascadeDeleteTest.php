<?php

namespace Tests\Feature;

use App\Models\EngineAnalysis;
use App\Models\Game;
use App\Models\KeyMoment;
use App\Models\MistakeTag;
use App\Models\Move;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_game_cascades_to_moves(): void
    {
        $game = Game::factory()->create();
        $move = Move::factory()->for($game)->create(['move_number' => 1]);

        $game->delete();

        $this->assertDatabaseMissing('moves', ['id' => $move->id]);
    }

    public function test_deleting_game_cascades_to_engine_analyses(): void
    {
        $game     = Game::factory()->create();
        $move     = Move::factory()->for($game)->create(['move_number' => 1]);
        $analysis = EngineAnalysis::factory()->for($move)->create();

        $game->delete();

        $this->assertDatabaseMissing('engine_analyses', ['id' => $analysis->id]);
    }

    public function test_deleting_game_cascades_to_key_moments(): void
    {
        $game = Game::factory()->create();
        $move = Move::factory()->for($game)->create(['move_number' => 1]);
        $tag  = MistakeTag::factory()->create();
        $km   = KeyMoment::factory()->create([
            'game_id'        => $game->id,
            'move_id'        => $move->id,
            'mistake_tag_id' => $tag->id,
            'rank'           => 1,
        ]);

        $game->delete();

        $this->assertDatabaseMissing('key_moments', ['id' => $km->id]);
    }

    public function test_deleting_user_cascades_to_games(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $user->delete();

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
    }

    public function test_deleting_user_cascades_transitively_to_moves(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        $move = Move::factory()->for($game)->create(['move_number' => 1]);

        $user->delete();

        $this->assertDatabaseMissing('moves', ['id' => $move->id]);
    }
}
