<?php

namespace Tests\Feature;

use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReanalyseGamesCommandTest extends TestCase
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
            'analysis_status' => 'complete',
            'imported_from' => 'paste',
            'share_code' => strtolower(substr(bin2hex(random_bytes(4)), 0, 8)),
        ], $overrides));
    }

    public function test_command_requires_scope_option(): void
    {
        $this->artisan('chess:reanalyse')
            ->expectsOutputToContain('Provide --all or at least one --game_id option.')
            ->assertFailed();
    }

    public function test_command_dispatches_sync_force_jobs_for_specific_game_ids(): void
    {
        Bus::fake();
        $gameA = $this->createGame();
        $gameB = $this->createGame();

        $this->artisan('chess:reanalyse', ['--game_id' => [$gameA->id, $gameB->id]])
            ->expectsOutputToContain('Re-analysing 2 game(s) with --force...')
            ->assertSuccessful();

        Bus::assertDispatchedSync(AnalyseGameJob::class, 2);
        Bus::assertDispatchedSync(AnalyseGameJob::class, fn (AnalyseGameJob $job): bool => $job->gameId === $gameA->id && $job->force === true);
        Bus::assertDispatchedSync(AnalyseGameJob::class, fn (AnalyseGameJob $job): bool => $job->gameId === $gameB->id && $job->force === true);
    }

    public function test_command_with_all_dispatches_for_every_game(): void
    {
        Bus::fake();
        $this->createGame();
        $this->createGame();
        $this->createGame();

        $this->artisan('chess:reanalyse', ['--all' => true])
            ->expectsOutputToContain('Re-analysing 3 game(s) with --force...')
            ->assertSuccessful();

        Bus::assertDispatchedSync(AnalyseGameJob::class, 3);
    }
}
