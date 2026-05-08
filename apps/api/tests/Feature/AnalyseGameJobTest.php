<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Jobs\AnalyseGameJob;
use App\Models\EngineAnalysis;
use App\Models\Game;
use App\Models\Move;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyseGameJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    private function createPendingGame(): Game
    {
        return Game::create([
            'user_id' => DevUserSeeder::UUID,
            'pgn_raw' => '[White "A"][Black "B"][Result "1-0"] 1.e4 1-0',
            'white_player' => 'A',
            'black_player' => 'B',
            'result' => 'white',
            'user_colour' => 'white',
            'played_at' => now(),
            'eco_code' => 'C20',
            'opening_name' => 'King Pawn Game',
            'move_count' => 1,
            'analysis_status' => AnalysisStatus::Pending,
            'imported_from' => 'paste',
            'share_code' => 'abc234de',
        ]);
    }

    public function test_job_sets_game_complete_and_updates_move_and_engine_analysis(): void
    {
        $game = $this->createPendingGame();
        $move = Move::create([
            'game_id' => $game->id,
            'move_number' => 1,
            'colour' => 'white',
            'san' => 'e4',
            'uci' => 'e2e4',
            'fen_before' => 'before-fen',
            'fen_after' => 'after-fen',
        ]);

        $stockfish = \Mockery::mock('overload:App\Services\StockfishService');
        $stockfish->shouldReceive('analyse')->once()->with('before-fen')->andReturn([
            'best_move' => 'e2e4',
            'cp' => 120,
            'depth_reached' => 14,
            'best_line' => ['e2e4', 'e7e5'],
        ]);
        $stockfish->shouldReceive('analyse')->once()->with('after-fen')->andReturn([
            'best_move' => 'e7e5',
            'cp' => -40,
            'depth_reached' => 13,
            'best_line' => ['e7e5', 'g1f3'],
        ]);

        (new AnalyseGameJob($game->id))->handle();

        $game->refresh();
        $move->refresh();

        $this->assertSame('complete', $game->analysis_status->value);
        $this->assertSame(0, $game->blunder_count);
        $this->assertSame(0, $game->mistake_count);
        $this->assertSame(0, $game->inaccuracy_count);

        $this->assertSame(120, $move->cp_score);
        $this->assertSame(80, $move->cp_loss);
        $this->assertSame('good', $move->classification?->value);

        $this->assertDatabaseHas('engine_analyses', [
            'move_id' => $move->id,
            'engine_name' => 'stockfish',
            'best_move_uci' => 'e2e4',
            'cp_evaluation' => 120,
        ]);

        $analysis = EngineAnalysis::where('move_id', $move->id)->firstOrFail();
        $this->assertNull($analysis->best_move_san);
    }

    public function test_job_skips_complete_games_unless_forced(): void
    {
        $game = $this->createPendingGame();
        $game->update(['analysis_status' => AnalysisStatus::Complete]);

        $stockfish = \Mockery::mock('overload:App\Services\StockfishService');
        $stockfish->shouldNotReceive('analyse');

        (new AnalyseGameJob($game->id))->handle();

        $game->refresh();
        $this->assertSame('complete', $game->analysis_status->value);
    }

    public function test_failed_marks_game_as_failed(): void
    {
        $game = $this->createPendingGame();

        (new AnalyseGameJob($game->id))->failed(new \RuntimeException('boom'));

        $game->refresh();
        $this->assertSame('failed', $game->analysis_status->value);
    }
}
