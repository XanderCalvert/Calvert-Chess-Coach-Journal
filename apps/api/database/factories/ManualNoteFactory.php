<?php

namespace Database\Factories;

use App\Enums\CoachAgreement;
use App\Models\ManualNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualNote>
 */
class ManualNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'game_id'        => null,
            'key_moment_id'  => null,
            'note_text'      => fake()->paragraph(),
            'coach_agreement' => CoachAgreement::NotSet->value,
        ];
    }
}
