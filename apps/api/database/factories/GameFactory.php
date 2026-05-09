<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Enums\GameResult;
use App\Enums\ImportSource;
use App\Enums\PlayerColour;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'pgn_raw'          => '[Event "?"] [White "Player1"] [Black "Player2"] 1. e4 e5 *',
            'white_player'     => fake()->name(),
            'black_player'     => fake()->name(),
            'played_at'        => fake()->dateTimeBetween('-1 year', 'now'),
            'result'           => fake()->randomElement(GameResult::cases())->value,
            'user_colour'      => fake()->randomElement(PlayerColour::cases())->value,
            'opening_name'     => 'King\'s Pawn Opening',
            'eco_code'         => 'C20',
            'move_count'       => fake()->numberBetween(20, 60),
            'accuracy_pct'     => null,
            'blunder_count'    => 0,
            'mistake_count'    => 0,
            'inaccuracy_count' => 0,
            'summary_text'     => null,
            'analysis_status'  => AnalysisStatus::Pending->value,
            'imported_from'    => fake()->randomElement(ImportSource::cases())->value,
            'external_id'      => null,
        ];
    }
}
