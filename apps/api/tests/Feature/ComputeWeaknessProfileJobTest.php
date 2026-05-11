<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Jobs\ComputeWeaknessProfileJob;
use App\Models\ConnectedAccount;
use App\Models\Game;
use App\Models\Move;
use App\Models\MoveTacticalEvent;
use App\Models\MoveThreatEvent;
use App\Models\WeaknessProfile;
use Database\Seeders\DevUserSeeder;
use Database\Seeders\MistakeTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputeWeaknessProfileJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
        $this->seed(MistakeTagSeeder::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createAccount(): ConnectedAccount
    {
        return ConnectedAccount::create([
            'user_id'             => DevUserSeeder::UUID,
            'platform'            => 'chesscom',
            'username'            => 'TestPlayer',
            'normalised_username' => 'testplayer',
            'sync_status'         => 'never_synced',
        ]);
    }

    private function createAnalysedGame(ConnectedAccount $account, array $overrides = []): Game
    {
        static $counter = 0;
        $counter++;
        return Game::create(array_merge([
            'user_id'              => DevUserSeeder::UUID,
            'connected_account_id' => $account->id,
            'pgn_raw'              => '*',
            'white_player'         => 'TestPlayer',
            'black_player'         => 'Opponent',
            'result'               => 'white',
            'user_colour'          => 'white',
            'played_at'            => now()->subDays($counter),
            'eco_code'             => 'C20',
            'opening_name'         => 'King Pawn',
            'move_count'           => 10,
            'analysis_status'      => AnalysisStatus::Analysed,
            'imported_from'        => 'chesscom',
            'share_code'           => str_pad((string) $counter, 8, '0', STR_PAD_LEFT),
            'blunder_count'        => 1,
            'mistake_count'        => 1,
            'inaccuracy_count'     => 1,
            'accuracy_pct'         => 85.0,
        ], $overrides));
    }

    private function createMove(Game $game, array $overrides = []): Move
    {
        static $ply = 0;
        $ply++;
        return Move::create(array_merge([
            'game_id'     => $game->id,
            'move_number' => $ply,
            'colour'      => 'white',
            'san'         => 'e4',
            'uci'         => 'e2e4',
            'fen_before'  => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            'fen_after'   => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 1',
            'game_phase'  => 'opening',
            'classification' => 'good',
            'cp_loss'     => 20,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Insufficient data
    // -------------------------------------------------------------------------

    public function test_job_inserts_insufficient_data_profile_when_fewer_than_3_games(): void
    {
        $account = $this->createAccount();
        $this->createAnalysedGame($account);
        $this->createAnalysedGame($account);

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $this->assertCount(1, $account->weaknessProfiles()->get());

        $profile = $account->latestWeaknessProfile;
        $this->assertSame(2, $profile->analysed_games_count);
        $this->assertNull($profile->weakest_phase);
        $this->assertNull($profile->top_motif);
        $this->assertSame([], $profile->opening_breakdown);
        $this->assertSame([], $profile->motif_frequencies);
        $this->assertFalse($profile->summary_json['sufficient_data']);
    }

    public function test_job_does_not_insert_profile_when_no_games_exist(): void
    {
        $account = $this->createAccount();

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $this->assertCount(0, $account->weaknessProfiles()->get());
    }

    // -------------------------------------------------------------------------
    // Phase breakdown
    // -------------------------------------------------------------------------

    public function test_phase_breakdown_identifies_weakest_phase(): void
    {
        $account = $this->createAccount();

        // Create 5 games — enough for sufficient_data
        for ($i = 0; $i < 5; $i++) {
            $game = $this->createAnalysedGame($account);

            // 2 opening moves per game — 1 blunder (weight 3) + 1 good
            $this->createMove($game, ['game_phase' => 'opening', 'classification' => 'blunder', 'cp_loss' => 400]);
            $this->createMove($game, ['game_phase' => 'opening', 'classification' => 'good', 'cp_loss' => 20]);

            // 4 middlegame moves per game — all excellent (weight 0)
            for ($j = 0; $j < 4; $j++) {
                $this->createMove($game, ['game_phase' => 'middlegame', 'classification' => 'excellent', 'cp_loss' => 5]);
            }
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $profile = $account->latestWeaknessProfile;
        $this->assertSame('opening', $profile->weakest_phase);
        $this->assertArrayHasKey('opening', $profile->phase_breakdown);
        $this->assertArrayHasKey('middlegame', $profile->phase_breakdown);

        // Opening error_rate = (5 blunders × 3) / 10 opening moves = 1.5
        $this->assertEqualsWithDelta(1.5, $profile->phase_breakdown['opening']['error_rate'], 0.01);
        // Middlegame error_rate = 0 / 20 = 0
        $this->assertEqualsWithDelta(0.0, $profile->phase_breakdown['middlegame']['error_rate'], 0.01);
    }

    public function test_phase_breakdown_counts_classifications_correctly(): void
    {
        $account = $this->createAccount();

        for ($i = 0; $i < 3; $i++) {
            $game = $this->createAnalysedGame($account);
            $this->createMove($game, ['game_phase' => 'middlegame', 'classification' => 'blunder']);
            $this->createMove($game, ['game_phase' => 'middlegame', 'classification' => 'mistake']);
            $this->createMove($game, ['game_phase' => 'middlegame', 'classification' => 'inaccuracy']);
            $this->createMove($game, ['game_phase' => 'middlegame', 'classification' => 'good']);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $breakdown = $account->latestWeaknessProfile->phase_breakdown['middlegame'];
        $this->assertSame(3, $breakdown['blunders']);
        $this->assertSame(3, $breakdown['mistakes']);
        $this->assertSame(3, $breakdown['inaccuracies']);
        $this->assertSame(12, $breakdown['move_count']);
    }

    // -------------------------------------------------------------------------
    // Opening breakdown — minimum games threshold
    // -------------------------------------------------------------------------

    public function test_opening_below_min_games_threshold_is_excluded(): void
    {
        config(['chess.weakness_opening_min_games' => 3]);
        $account = $this->createAccount();

        // 5 games with eco C20 (meets threshold of 3)
        for ($i = 0; $i < 5; $i++) {
            $game = $this->createAnalysedGame($account, ['eco_code' => 'C20']);
            $this->createMove($game, ['game_phase' => 'opening', 'classification' => 'blunder']);
        }

        // Only 2 games with eco D00 (below threshold)
        for ($i = 0; $i < 2; $i++) {
            $game = $this->createAnalysedGame($account, ['eco_code' => 'D00', 'opening_name' => 'Queens Pawn']);
            $this->createMove($game, ['game_phase' => 'opening', 'classification' => 'blunder']);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $breakdown = $account->latestWeaknessProfile->opening_breakdown;
        $ecoCodes  = array_column($breakdown, 'eco_code');

        $this->assertContains('C20', $ecoCodes);
        $this->assertNotContains('D00', $ecoCodes);
    }

    public function test_opening_with_exactly_min_games_is_included(): void
    {
        config(['chess.weakness_opening_min_games' => 3]);
        $account = $this->createAccount();

        for ($i = 0; $i < 3; $i++) {
            $game = $this->createAnalysedGame($account, ['eco_code' => 'E00', 'opening_name' => 'Indians']);
            $this->createMove($game, ['game_phase' => 'opening', 'classification' => 'mistake']);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $ecoCodes = array_column($account->latestWeaknessProfile->opening_breakdown, 'eco_code');
        $this->assertContains('E00', $ecoCodes);
    }

    public function test_opening_breakdown_empty_when_no_opening_meets_threshold(): void
    {
        config(['chess.weakness_opening_min_games' => 3]);
        $account = $this->createAccount();

        // 3 games, all different eco codes — none will meet min threshold of 3
        foreach (['C20', 'D00', 'E05'] as $eco) {
            $game = $this->createAnalysedGame($account, ['eco_code' => $eco]);
            $this->createMove($game, ['game_phase' => 'opening']);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $this->assertSame([], $account->latestWeaknessProfile->opening_breakdown);
    }

    public function test_opening_breakdown_sorted_by_weakness_score_descending(): void
    {
        config(['chess.weakness_opening_min_games' => 3]);
        $account = $this->createAccount();

        // Eco C20: 3 games, 1 blunder per game → weakness_score = 3
        for ($i = 0; $i < 3; $i++) {
            $game = $this->createAnalysedGame($account, ['eco_code' => 'C20', 'opening_name' => 'KP']);
            $this->createMove($game, ['game_phase' => 'opening', 'classification' => 'blunder']);
        }

        // Eco D00: 3 games, 1 inaccuracy per game → weakness_score = 0.33
        for ($i = 0; $i < 3; $i++) {
            $game = $this->createAnalysedGame($account, ['eco_code' => 'D00', 'opening_name' => 'QP']);
            $this->createMove($game, ['game_phase' => 'opening', 'classification' => 'inaccuracy']);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $breakdown = $account->latestWeaknessProfile->opening_breakdown;
        $this->assertSame('C20', $breakdown[0]['eco_code']);
        $this->assertGreaterThan($breakdown[1]['weakness_score'], $breakdown[0]['weakness_score']);
    }

    // -------------------------------------------------------------------------
    // Motif frequencies — minimum affected games threshold
    // -------------------------------------------------------------------------

    public function test_motif_below_min_affected_games_is_excluded(): void
    {
        config(['chess.weakness_motif_min_games' => 2]);
        $account = $this->createAccount();

        for ($i = 0; $i < 3; $i++) {
            $game = $this->createAnalysedGame($account);
            $move = $this->createMove($game, ['game_phase' => 'middlegame']);
            // hanging_piece in all 3 games — meets threshold
            MoveTacticalEvent::create([
                'move_id' => $move->id, 'motif' => 'hanging_piece',
                'severity' => 'major', 'confidence' => 'high', 'detector_version' => '1.0',
            ]);
        }

        // possible_fork only in 1 game — below threshold of 2
        $game2 = $this->createAnalysedGame($account);
        $move2 = $this->createMove($game2, ['game_phase' => 'middlegame']);
        MoveTacticalEvent::create([
            'move_id' => $move2->id, 'motif' => 'possible_fork',
            'severity' => 'minor', 'confidence' => 'low', 'detector_version' => '1.0',
        ]);

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $motifs = array_column($account->latestWeaknessProfile->motif_frequencies, 'motif');
        $this->assertContains('hanging_piece', $motifs);
        $this->assertNotContains('possible_fork', $motifs);
    }

    public function test_motif_with_exactly_min_affected_games_is_included(): void
    {
        config(['chess.weakness_motif_min_games' => 2]);
        $account = $this->createAccount();

        for ($i = 0; $i < 3; $i++) {
            $game = $this->createAnalysedGame($account);
            $this->createMove($game, ['game_phase' => 'opening']);
        }

        // engine_prefers_capture in exactly 2 games
        $games = $account->games()->get();
        foreach ($games->take(2) as $game) {
            $move = $game->moves()->first();
            MoveTacticalEvent::create([
                'move_id' => $move->id, 'motif' => 'engine_prefers_capture',
                'severity' => 'major', 'confidence' => 'high', 'detector_version' => '1.0',
            ]);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $motifs = array_column($account->latestWeaknessProfile->motif_frequencies, 'motif');
        $this->assertContains('engine_prefers_capture', $motifs);
    }

    public function test_motif_frequencies_sorted_by_score_descending(): void
    {
        config(['chess.weakness_motif_min_games' => 2]);
        $account = $this->createAccount();

        for ($i = 0; $i < 4; $i++) {
            $game = $this->createAnalysedGame($account);
            $move = $this->createMove($game, ['game_phase' => 'middlegame']);
            // hanging_piece (major, severity_weight=2) in all 4 games
            MoveTacticalEvent::create([
                'move_id' => $move->id, 'motif' => 'hanging_piece',
                'severity' => 'major', 'confidence' => 'high', 'detector_version' => '1.0',
            ]);
        }

        // possible_fork (minor, severity_weight=1) in 2 games — lower score
        $games = $account->games()->get();
        foreach ($games->take(2) as $game) {
            $move = $game->moves()->first();
            MoveTacticalEvent::create([
                'move_id' => $move->id, 'motif' => 'possible_fork',
                'severity' => 'minor', 'confidence' => 'low', 'detector_version' => '1.0',
            ]);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $motifs = $account->latestWeaknessProfile->motif_frequencies;
        $this->assertSame('hanging_piece', $motifs[0]['motif']);
        $this->assertGreaterThan($motifs[1]['score'], $motifs[0]['score']);
    }

    // -------------------------------------------------------------------------
    // Append-only / idempotency
    // -------------------------------------------------------------------------

    public function test_running_job_twice_inserts_two_rows(): void
    {
        $account = $this->createAccount();
        for ($i = 0; $i < 3; $i++) {
            $game = $this->createAnalysedGame($account);
            $this->createMove($game, ['game_phase' => 'opening']);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();
        (new ComputeWeaknessProfileJob($account->id))->handle();

        $this->assertCount(2, $account->weaknessProfiles()->get());
    }

    public function test_latest_profile_returns_most_recent_after_two_runs(): void
    {
        $account = $this->createAccount();
        for ($i = 0; $i < 3; $i++) {
            $game = $this->createAnalysedGame($account);
            $this->createMove($game, ['game_phase' => 'opening']);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();
        $first = $account->fresh()->latestWeaknessProfile;

        // Tiny sleep ensures computed_at differs
        sleep(1);

        (new ComputeWeaknessProfileJob($account->id))->handle();
        $second = $account->fresh()->latestWeaknessProfile;

        $this->assertFalse($first->is($second));
        $this->assertTrue($second->computed_at->greaterThanOrEqualTo($first->computed_at));
    }

    // -------------------------------------------------------------------------
    // computed_from_game_id / computed_to_game_id
    // -------------------------------------------------------------------------

    public function test_computed_from_and_to_game_ids_are_set(): void
    {
        $account = $this->createAccount();
        $games   = [];
        for ($i = 0; $i < 3; $i++) {
            $games[] = $this->createAnalysedGame($account, ['played_at' => now()->subDays(3 - $i)]);
            $this->createMove(end($games), ['game_phase' => 'opening']);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $profile = $account->latestWeaknessProfile;
        // Window loaded newest-first; from = oldest game in window, to = newest
        $this->assertNotNull($profile->computed_from_game_id);
        $this->assertNotNull($profile->computed_to_game_id);
        $this->assertNotSame($profile->computed_from_game_id, $profile->computed_to_game_id);
    }

    // -------------------------------------------------------------------------
    // summary_json structure
    // -------------------------------------------------------------------------

    public function test_summary_json_contains_structured_facts_not_prose(): void
    {
        $account = $this->createAccount();
        for ($i = 0; $i < 3; $i++) {
            $game = $this->createAnalysedGame($account);
            $this->createMove($game, ['game_phase' => 'middlegame', 'classification' => 'blunder']);
        }

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $summary = $account->latestWeaknessProfile->summary_json;

        $this->assertTrue($summary['sufficient_data']);
        $this->assertArrayHasKey('weakest_phase', $summary);
        $this->assertArrayHasKey('phase_error_rates', $summary);
        $this->assertArrayHasKey('threat_response_rate_overall', $summary);
        $this->assertArrayHasKey('window_size', $summary);
        $this->assertArrayHasKey('analysed_games_count', $summary);
        $this->assertArrayHasKey('profile_version', $summary);

        // Must NOT contain pre-written prose
        $this->assertArrayNotHasKey('key_weaknesses', $summary);
    }

    public function test_summary_json_has_sufficient_data_false_when_fewer_than_3_games(): void
    {
        $account = $this->createAccount();
        $this->createAnalysedGame($account);

        (new ComputeWeaknessProfileJob($account->id))->handle();

        $summary = $account->latestWeaknessProfile->summary_json;
        $this->assertFalse($summary['sufficient_data']);
        $this->assertArrayNotHasKey('weakest_phase', $summary);
    }
}
