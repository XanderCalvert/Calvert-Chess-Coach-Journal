<?php

namespace Database\Factories;

use App\Enums\PhaseHint;
use App\Models\MistakeTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MistakeTag>
 */
class MistakeTagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug'        => fake()->unique()->slug(2),
            'label'       => fake()->words(2, true),
            'description' => fake()->sentence(),
            'phase_hint'  => fake()->randomElement(PhaseHint::cases())->value,
        ];
    }
}
