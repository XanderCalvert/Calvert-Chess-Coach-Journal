<?php

namespace Database\Factories;

use App\Enums\ExplanationStatus;
use App\Enums\GamePhase;
use App\Models\Game;
use App\Models\KeyMoment;
use App\Models\MistakeTag;
use App\Models\Move;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeyMoment>
 */
class KeyMomentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id'            => Game::factory(),
            'move_id'            => Move::factory(),
            'mistake_tag_id'     => MistakeTag::factory(),
            'rank'               => 1,
            'cp_loss'            => fake()->numberBetween(50, 500),
            'explanation_text'   => null,
            'explanation_status' => ExplanationStatus::Pending->value,
            'game_phase'         => fake()->randomElement(GamePhase::cases())->value,
        ];
    }
}
