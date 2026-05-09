<?php

namespace Tests\Feature;

use App\Models\EngineAnalysis;
use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_games(): void
    {
        $user = User::factory()->create();
        Game::factory()->count(3)->for($user)->create();

        $this->assertCount(3, $user->games);
        $this->assertInstanceOf(Game::class, $user->games->first());
    }

    public function test_game_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $this->assertTrue($game->user->is($user));
    }

    public function test_game_has_many_moves(): void
    {
        $game = Game::factory()->create();
        Move::factory()->for($game)->count(5)->sequence(
            fn ($sequence) => ['move_number' => $sequence->index + 1]
        )->create();

        $this->assertCount(5, $game->moves);
        $this->assertInstanceOf(Move::class, $game->moves->first());
    }

    public function test_move_has_one_engine_analysis(): void
    {
        $game = Game::factory()->create();
        $move = Move::factory()->for($game)->create(['move_number' => 1]);
        $analysis = EngineAnalysis::factory()->for($move)->create();

        $this->assertTrue($move->engineAnalysis->is($analysis));
    }

    public function test_full_chain_user_game_move_engine_analysis(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        $move = Move::factory()->for($game)->create(['move_number' => 1]);
        $analysis = EngineAnalysis::factory()->for($move)->create();

        $loaded = User::with('games.moves.engineAnalysis')->find($user->id);

        $this->assertSame($user->id, $loaded->games->first()->user_id);
        $this->assertSame($game->id, $loaded->games->first()->moves->first()->game_id);
        $this->assertSame($move->id, $loaded->games->first()->moves->first()->engineAnalysis->move_id);
        $this->assertSame($analysis->id, $loaded->games->first()->moves->first()->engineAnalysis->id);
    }

    public function test_uuids_are_generated_on_create(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        $move = Move::factory()->for($game)->create(['move_number' => 1]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $user->id,
        );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $game->id,
        );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $move->id,
        );
    }
}
