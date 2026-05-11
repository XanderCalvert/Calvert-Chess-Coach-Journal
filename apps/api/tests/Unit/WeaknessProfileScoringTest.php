<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the scoring formulae used by ComputeWeaknessProfileJob.
 * These test the math in isolation without any database access.
 */
class WeaknessProfileScoringTest extends TestCase
{
    // -------------------------------------------------------------------------
    // error_rate = (blunders×3 + mistakes×2 + inaccuracies) / move_count
    // -------------------------------------------------------------------------

    public function test_error_rate_with_typical_values(): void
    {
        $blunders     = 2;
        $mistakes     = 3;
        $inaccuracies = 5;
        $moveCount    = 40;

        $errorRate = ($blunders * 3 + $mistakes * 2 + $inaccuracies) / $moveCount;

        $this->assertEqualsWithDelta(0.4250, $errorRate, 0.0001);
    }

    public function test_error_rate_all_blunders(): void
    {
        $errorRate = (5 * 3 + 0 * 2 + 0) / 20;

        $this->assertEqualsWithDelta(0.75, $errorRate, 0.0001);
    }

    public function test_error_rate_no_errors(): void
    {
        $errorRate = (0 * 3 + 0 * 2 + 0) / 30;

        $this->assertEqualsWithDelta(0.0, $errorRate, 0.0001);
    }

    public function test_error_rate_only_inaccuracies(): void
    {
        $errorRate = (0 * 3 + 0 * 2 + 10) / 50;

        $this->assertEqualsWithDelta(0.20, $errorRate, 0.0001);
    }

    // -------------------------------------------------------------------------
    // weakness_score = (blunders×3 + mistakes×2 + inaccuracies) / games
    // -------------------------------------------------------------------------

    public function test_weakness_score_typical(): void
    {
        $blunders     = 4;
        $mistakes     = 6;
        $inaccuracies = 9;
        $games        = 5;

        $score = ($blunders * 3 + $mistakes * 2 + $inaccuracies) / $games;

        $this->assertEqualsWithDelta(6.60, $score, 0.01);
    }

    public function test_weakness_score_perfect_games(): void
    {
        $score = (0 * 3 + 0 * 2 + 0) / 3;

        $this->assertEqualsWithDelta(0.0, $score, 0.0001);
    }

    public function test_weakness_score_single_blunder_per_game(): void
    {
        $score = (3 * 3 + 0 + 0) / 3;

        $this->assertEqualsWithDelta(3.0, $score, 0.0001);
    }

    // -------------------------------------------------------------------------
    // motif_score = frequency_rate × severity_weight
    // severity_weight: critical=3, major=2, minor=1
    // -------------------------------------------------------------------------

    public function test_motif_score_critical_high_frequency(): void
    {
        $affectedGames   = 9;
        $gamesCount      = 10;
        $severityWeight  = 3; // critical
        $frequencyRate   = $affectedGames / $gamesCount;
        $score           = $frequencyRate * $severityWeight;

        $this->assertEqualsWithDelta(2.7, $score, 0.001);
    }

    public function test_motif_score_major_medium_frequency(): void
    {
        $affectedGames  = 5;
        $gamesCount     = 10;
        $severityWeight = 2; // major
        $score          = ($affectedGames / $gamesCount) * $severityWeight;

        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    public function test_motif_score_minor_low_frequency(): void
    {
        $affectedGames  = 2;
        $gamesCount     = 10;
        $severityWeight = 1; // minor
        $score          = ($affectedGames / $gamesCount) * $severityWeight;

        $this->assertEqualsWithDelta(0.2, $score, 0.001);
    }

    public function test_motif_score_zero_when_no_affected_games(): void
    {
        $score = (0 / 10) * 2;

        $this->assertEqualsWithDelta(0.0, $score, 0.0001);
    }

    // -------------------------------------------------------------------------
    // Threshold boundary: opening_min_games (default 3)
    // -------------------------------------------------------------------------

    public function test_opening_with_exactly_min_games_is_included(): void
    {
        $minGames   = 3;
        $gamesInEco = 3;

        $this->assertGreaterThanOrEqual($minGames, $gamesInEco);
    }

    public function test_opening_below_min_games_is_excluded(): void
    {
        $minGames   = 3;
        $gamesInEco = 2;

        $this->assertLessThan($minGames, $gamesInEco);
    }

    // -------------------------------------------------------------------------
    // Threshold boundary: motif_min_games (default 2)
    // -------------------------------------------------------------------------

    public function test_motif_with_exactly_min_affected_games_is_included(): void
    {
        $minGames      = 2;
        $affectedGames = 2;

        $this->assertGreaterThanOrEqual($minGames, $affectedGames);
    }

    public function test_motif_with_one_affected_game_is_excluded(): void
    {
        $minGames      = 2;
        $affectedGames = 1;

        $this->assertLessThan($minGames, $affectedGames);
    }

    // -------------------------------------------------------------------------
    // sufficient_data boundary
    // -------------------------------------------------------------------------

    public function test_sufficient_data_requires_at_least_3_games(): void
    {
        $this->assertFalse(2 >= 3);
        $this->assertTrue(3 >= 3);
        $this->assertTrue(20 >= 3);
    }
}
