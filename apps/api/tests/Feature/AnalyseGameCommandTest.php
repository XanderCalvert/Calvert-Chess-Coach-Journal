<?php

namespace Tests\Feature;

use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AnalyseGameCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    private function createGame(): Game
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
            'analysis_status' => 'pending',
            'imported_from' => 'paste',
            'share_code' => 'abc234de',
        ]);
    }

    public function test_command_dispatches_sync_analysis_job(): void
    {
        Bus::fake();
        $game = $this->createGame();

        $this->artisan('chess:analyse', ['game_id' => $game->id])
            ->expectsOutputToContain("Starting analysis for game {$game->id}")
            ->assertSuccessful();

        Bus::assertDispatchedSync(AnalyseGameJob::class, function (AnalyseGameJob $job) use ($game): bool {
            return $job->gameId === $game->id && $job->force === false;
        });
    }
}
