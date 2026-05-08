<?php

namespace Database\Factories;

use App\Models\EngineAnalysis;
use App\Models\Move;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EngineAnalysis>
 */
class EngineAnalysisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'move_id'       => Move::factory(),
            'engine_name'   => 'stockfish',
            'best_move_uci' => 'e2e4',
            'best_move_san' => 'e4',
            'best_line'     => ['e4', 'e5', 'Nf3', 'Nc6', 'Bc4'],
            'depth'         => fake()->numberBetween(18, 25),
            'depth_requested' => 12,
            'depth_reached' => 12,
            'cp_evaluation' => fake()->numberBetween(-100, 100),
            'analysed_at'   => now(),
        ];
    }
}
