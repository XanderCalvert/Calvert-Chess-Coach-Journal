<?php

namespace Database\Factories;

use App\Enums\PlayerColour;
use App\Models\Game;
use App\Models\Move;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Move>
 */
class MoveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id'        => Game::factory(),
            'move_number'    => fake()->numberBetween(1, 80),
            'san'            => fake()->randomElement(['e4', 'e5', 'Nf3', 'Nc6', 'd4', 'd5', 'Bc4']),
            'uci'            => 'e2e4',
            'fen_before'     => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            'fen_after'      => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1',
            'colour'         => fake()->randomElement(PlayerColour::cases())->value,
            'cp_score'       => null,
            'cp_loss'        => null,
            'classification' => null,
        ];
    }
}
