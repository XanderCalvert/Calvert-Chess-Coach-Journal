<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\KeyMoment;
use App\Models\MistakeTag;
use App\Models\Move;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_moves_game_id_move_number_must_be_unique(): void
    {
        $game = Game::factory()->create();
        Move::factory()->for($game)->create(['move_number' => 1]);

        $this->expectException(QueryException::class);
        Move::factory()->for($game)->create(['move_number' => 1]);
    }

    public function test_moves_same_number_in_different_games_is_allowed(): void
    {
        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();

        Move::factory()->for($game1)->create(['move_number' => 1]);
        $move = Move::factory()->for($game2)->create(['move_number' => 1]);

        $this->assertDatabaseHas('moves', ['id' => $move->id]);
    }

    public function test_key_moments_game_id_rank_must_be_unique(): void
    {
        $game = Game::factory()->create();
        $tag  = MistakeTag::factory()->create();
        $move1 = Move::factory()->for($game)->create(['move_number' => 1]);
        $move2 = Move::factory()->for($game)->create(['move_number' => 2]);

        KeyMoment::factory()->create([
            'game_id'        => $game->id,
            'move_id'        => $move1->id,
            'mistake_tag_id' => $tag->id,
            'rank'           => 1,
        ]);

        $this->expectException(QueryException::class);
        KeyMoment::factory()->create([
            'game_id'        => $game->id,
            'move_id'        => $move2->id,
            'mistake_tag_id' => $tag->id,
            'rank'           => 1,
        ]);
    }

    public function test_key_moments_same_rank_in_different_games_is_allowed(): void
    {
        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();
        $tag   = MistakeTag::factory()->create();
        $move1 = Move::factory()->for($game1)->create(['move_number' => 1]);
        $move2 = Move::factory()->for($game2)->create(['move_number' => 1]);

        $km1 = KeyMoment::factory()->create([
            'game_id'        => $game1->id,
            'move_id'        => $move1->id,
            'mistake_tag_id' => $tag->id,
            'rank'           => 1,
        ]);
        $km2 = KeyMoment::factory()->create([
            'game_id'        => $game2->id,
            'move_id'        => $move2->id,
            'mistake_tag_id' => $tag->id,
            'rank'           => 1,
        ]);

        $this->assertDatabaseHas('key_moments', ['id' => $km1->id]);
        $this->assertDatabaseHas('key_moments', ['id' => $km2->id]);
    }

    public function test_key_moments_rank_check_constraint_postgres_only(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('CHECK constraint on key_moments.rank is Postgres-only');
        }

        $game = Game::factory()->create();
        $tag  = MistakeTag::factory()->create();
        $move = Move::factory()->for($game)->create(['move_number' => 1]);

        $this->expectException(QueryException::class);
        KeyMoment::factory()->create([
            'game_id'        => $game->id,
            'move_id'        => $move->id,
            'mistake_tag_id' => $tag->id,
            'rank'           => 5, // violates CHECK rank BETWEEN 1 AND 3
        ]);
    }

    public function test_games_partial_unique_index_postgres_only(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Partial unique index on games is Postgres-only');
        }

        $user = \App\Models\User::factory()->create();

        Game::factory()->for($user)->create([
            'imported_from' => 'lichess',
            'external_id'   => 'abc123',
        ]);

        $this->expectException(QueryException::class);
        Game::factory()->for($user)->create([
            'imported_from' => 'lichess',
            'external_id'   => 'abc123',
        ]);
    }
}
