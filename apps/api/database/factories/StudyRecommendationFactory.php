<?php

namespace Database\Factories;

use App\Enums\StudyStatus;
use App\Models\MistakeTag;
use App\Models\StudyRecommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyRecommendation>
 */
class StudyRecommendationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'mistake_tag_id'   => MistakeTag::factory(),
            'reason_text'      => fake()->sentence(),
            'description_text' => fake()->paragraph(),
            'status'           => StudyStatus::Active->value,
            'completed_at'     => null,
        ];
    }
}
