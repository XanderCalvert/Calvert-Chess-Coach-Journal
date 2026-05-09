<?php

namespace Database\Factories;

use App\Enums\GamePhase;
use App\Models\TrendSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrendSummary>
 */
class TrendSummaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'computed_at'       => now(),
            'games_analysed'    => fake()->numberBetween(1, 50),
            'avg_accuracy'      => fake()->randomFloat(2, 50, 95),
            'blunders_per_game' => fake()->randomFloat(2, 0, 5),
            'top_mistake_tag_id' => null,
            'opening_weakness'  => null,
            'phase_weakness'    => fake()->randomElement(GamePhase::cases())->value,
            'summary_json'      => [],
        ];
    }
}
