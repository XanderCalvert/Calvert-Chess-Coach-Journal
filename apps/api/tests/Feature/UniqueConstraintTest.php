<?php

namespace Tests\Feature;

use App\Models\EngineAnalysis;
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

    public function test_engine_analyses_move_id_and_engine_name_must_be_unique(): void
    {
        $move = Move::factory()->create();

        EngineAnalysis::create([
            'move_id' => $move->id,
            'engine_name' => 'stockfish',
            'best_move_uci' => 'e2e4',
            'best_move_san' => 'e4',
            'best_line' => ['e2e4'],
            'depth' => 18,
            'depth_requested' => 12,
            'depth_reached' => 12,
            'cp_evaluation' => 32,
            'analysed_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        EngineAnalysis::create([
            'move_id' => $move->id,
            'engine_name' => 'stockfish',
            'best_move_uci' => 'd2d4',
            'best_move_san' => 'd4',
            'best_line' => ['d2d4'],
            'depth' => 18,
            'depth_requested' => 12,
            'depth_reached' => 12,
            'cp_evaluation' => 20,
            'analysed_at' => now(),
        ]);
    }

    public function test_engine_analyses_allows_same_move_for_different_engines(): void
    {
        $move = Move::factory()->create();

        $analysisA = EngineAnalysis::create([
            'move_id' => $move->id,
            'engine_name' => 'stockfish',
            'best_move_uci' => 'e2e4',
            'best_move_san' => null,
            'best_line' => ['e2e4'],
            'depth' => 18,
            'depth_requested' => 12,
            'depth_reached' => 12,
            'cp_evaluation' => 32,
            'analysed_at' => now(),
        ]);
        $analysisB = EngineAnalysis::create([
            'move_id' => $move->id,
            'engine_name' => 'lc0',
            'best_move_uci' => 'd2d4',
            'best_move_san' => 'd4',
            'best_line' => ['d2d4'],
            'depth' => 18,
            'depth_requested' => 12,
            'depth_reached' => 12,
            'cp_evaluation' => 15,
            'analysed_at' => now(),
        ]);

        $this->assertDatabaseHas('engine_analyses', ['id' => $analysisA->id]);
        $this->assertDatabaseHas('engine_analyses', ['id' => $analysisB->id]);
    }
}
