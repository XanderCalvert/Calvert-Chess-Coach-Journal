<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use App\Models\KeyMoment;
use App\Models\Move;
use Database\Seeders\DevUserSeeder;
use Database\Seeders\MistakeTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeyMomentSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
        $this->seed(MistakeTagSeeder::class);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    private function createGame(): Game
    {
        return Game::create([
            'user_id'         => DevUserSeeder::UUID,
            'pgn_raw'         => '',
            'white_player'    => 'A',
            'black_player'    => 'B',
            'result'          => 'white',
            'user_colour'     => 'white',
            'played_at'       => now(),
            'eco_code'        => '',
            'opening_name'    => 'Unknown',
            'move_count'      => 1,
            'analysis_status' => AnalysisStatus::Pending,
            'imported_from'   => 'paste',
            'share_code'      => substr(md5(uniqid()), 0, 8),
        ]);
    }

    /**
     * Creates a move with unique per-move FENs so the Stockfish mock can return
     * the desired cp value for each position.
     *
     * The mock convention: fen_before_{n} → cp = $cpBefore, fen_after_{n} → cp = 0.
     * This yields cp_loss = max(0, cpBefore - 0).
     *
     * Thresholds: ≤10+best=Best, ≤30=Excellent, ≤80=Good, ≤140=Inaccuracy, ≤300=Mistake, >300=Blunder
     */
    private function createMove(Game $game, int $moveNumber, int $cpBefore): Move
    {
        return Move::create([
            'game_id'     => $game->id,
            'move_number' => $moveNumber,
            'colour'      => $moveNumber % 2 === 1 ? 'white' : 'black',
            'san'         => 'e4',
            'uci'         => 'e2e4',
            'fen_before'  => "fen_before_{$moveNumber}",
            'fen_after'   => "fen_after_{$moveNumber}",
        ]);
    }

    /**
     * Runs the job with a Stockfish mock that returns specific cp values per FEN.
     * Convention: "fen_before_{n}" FENs carry the desired cp, "fen_after_{n}" FENs return 0.
     *
     * @param array<int, int> $moveCps  map of move_number => desired cp_before value
     */
    private function runJobWithMocks(Game $game, array $moveCps): void
    {
        $stockfish = \Mockery::mock('overload:App\Services\StockfishService');
        $stockfish->shouldReceive('analyse')->andReturnUsing(function (string $fen) use ($moveCps) {
            // fen_before_{n} → return cpBefore for that move
            if (preg_match('/^fen_before_(\d+)$/', $fen, $m)) {
                $cp = $moveCps[(int) $m[1]] ?? 0;
                return ['best_move' => 'd2d4', 'cp' => $cp, 'mate' => null, 'depth_reached' => 10, 'best_line' => ['d2d4']];
            }
            // fen_after_{n} → return 0 (opponent's perspective)
            return ['best_move' => 'e7e5', 'cp' => 0, 'mate' => null, 'depth_reached' => 10, 'best_line' => ['e7e5']];
        });

        (new AnalyseGameJob($game->id, force: true))->handle();
    }

    public function test_only_inaccuracy_mistake_blunder_moves_become_key_moments(): void
    {
        $game = $this->createGame();
        $this->createMove($game, 1, cpBefore: 60);    // Good (cp_loss=60)
        $this->createMove($game, 3, cpBefore: 0);     // Best  (cp_loss=0, played==best if we match)
        $this->createMove($game, 5, cpBefore: 120);   // Inaccuracy (cp_loss=120)

        // Note: move 3 with cp=0 and played 'e2e4' != best_move 'd2d4' → Excellent (cp_loss=0, played≠best)
        // Actually Excellent requires cp_loss <= 30. cp_loss = 0 → Excellent.
        // Only move 5 (cp_loss=120, Inaccuracy) qualifies.
        $this->runJobWithMocks($game, [1 => 60, 3 => 0, 5 => 120]);

        $moments = KeyMoment::where('game_id', $game->id)->get();
        $this->assertCount(1, $moments);
        $this->assertSame(5, $moments->first()->move->move_number);
    }

    public function test_key_moments_capped_at_3_and_ordered_by_cp_loss(): void
    {
        $game = $this->createGame();
        // Four qualifying moves at non-adjacent plies.
        $this->createMove($game, 1,  cpBefore: 400);  // Blunder
        $this->createMove($game, 3,  cpBefore: 350);  // Blunder
        $this->createMove($game, 5,  cpBefore: 250);  // Mistake
        $this->createMove($game, 7,  cpBefore: 150);  // Inaccuracy

        $this->runJobWithMocks($game, [1 => 400, 3 => 350, 5 => 250, 7 => 150]);

        $moments = KeyMoment::where('game_id', $game->id)->orderBy('rank')->get();
        $this->assertCount(3, $moments);
        $this->assertSame(400, $moments[0]->cp_loss);
        $this->assertSame(350, $moments[1]->cp_loss);
        $this->assertSame(250, $moments[2]->cp_loss);
    }

    public function test_adjacent_ply_clustering_drops_lower_cp_loss_move(): void
    {
        $game = $this->createGame();
        // Plies 3 and 4 are adjacent — ply 3 has higher cp_loss so ply 4 should be dropped.
        $this->createMove($game, 3,  cpBefore: 400);  // Blunder
        $this->createMove($game, 4,  cpBefore: 350);  // Blunder (adjacent to ply 3 — should be dropped)
        $this->createMove($game, 10, cpBefore: 250);  // Mistake (non-adjacent — should be kept)

        $this->runJobWithMocks($game, [3 => 400, 4 => 350, 10 => 250]);

        $moments = KeyMoment::where('game_id', $game->id)->orderBy('rank')->with('move')->get();
        $this->assertCount(2, $moments);
        $moveNumbers = $moments->pluck('move.move_number')->sort()->values()->toArray();
        $this->assertSame([3, 10], $moveNumbers);
    }

    public function test_reanalysis_replaces_stale_key_moments(): void
    {
        $game = $this->createGame();
        $this->createMove($game, 1, cpBefore: 400);
        $this->createMove($game, 5, cpBefore: 150);

        $this->runJobWithMocks($game, [1 => 400, 5 => 150]);
        $firstCount = KeyMoment::where('game_id', $game->id)->count();
        $this->assertGreaterThan(0, $firstCount);

        // Re-run — should replace, not accumulate.
        $this->runJobWithMocks($game, [1 => 400, 5 => 150]);
        $this->assertSame($firstCount, KeyMoment::where('game_id', $game->id)->count());
    }

    public function test_game_with_no_qualifying_moves_creates_no_key_moments(): void
    {
        $game = $this->createGame();
        $this->createMove($game, 1, cpBefore: 0);   // Excellent
        $this->createMove($game, 2, cpBefore: 60);  // Good

        $this->runJobWithMocks($game, [1 => 0, 2 => 60]);

        $this->assertSame(0, KeyMoment::where('game_id', $game->id)->count());
    }

    public function test_game_phase_is_derived_from_move_number(): void
    {
        $game = $this->createGame();
        $this->createMove($game, 10, cpBefore: 400);  // ply 10 ≤ 30 → opening
        $this->createMove($game, 40, cpBefore: 350);  // 30 < ply ≤ 70 → middlegame
        $this->createMove($game, 80, cpBefore: 300);  // ply > 70 → endgame

        $this->runJobWithMocks($game, [10 => 400, 40 => 350, 80 => 300]);

        $moments = KeyMoment::where('game_id', $game->id)->orderBy('rank')->with('move')->get();
        $phaseByPly = $moments->keyBy(fn ($km) => $km->move->move_number);

        $this->assertSame('opening',    $phaseByPly[10]->game_phase->value);
        $this->assertSame('middlegame', $phaseByPly[40]->game_phase->value);
        $this->assertSame('endgame',    $phaseByPly[80]->game_phase->value);
    }
}
