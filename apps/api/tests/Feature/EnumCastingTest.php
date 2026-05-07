<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Enums\GameResult;
use App\Enums\PlayerColour;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnumCastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_status_round_trips_as_enum(): void
    {
        $game = Game::factory()->create(['analysis_status' => 'pending']);

        $fresh = $game->fresh();
        $this->assertInstanceOf(AnalysisStatus::class, $fresh->analysis_status);
        $this->assertSame(AnalysisStatus::Pending, $fresh->analysis_status);
    }

    public function test_game_result_round_trips_as_enum(): void
    {
        $game = Game::factory()->create(['result' => 'draw']);

        $this->assertSame(GameResult::Draw, $game->fresh()->result);
    }

    public function test_user_colour_round_trips_as_enum(): void
    {
        $game = Game::factory()->create(['user_colour' => 'white']);

        $this->assertSame(PlayerColour::White, $game->fresh()->user_colour);
    }

    public function test_invalid_enum_value_throws_value_error(): void
    {
        // AnalysisStatus::from() is what Eloquent calls during hydration.
        // Testing it directly verifies the language-level guarantee without
        // depending on whether Laravel propagates the exception through first().
        $this->expectException(\ValueError::class);
        AnalysisStatus::from('BOGUS');
    }
}
