<?php

namespace App\Models;

use App\Enums\ExplanationDepth;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['email', 'password', 'display_name', 'rating_estimate', 'explanation_depth'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasUuids, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'explanation_depth' => ExplanationDepth::class,
        ];
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function trendSummaries(): HasMany
    {
        return $this->hasMany(TrendSummary::class);
    }

    public function manualNotes(): HasMany
    {
        return $this->hasMany(ManualNote::class);
    }

    public function studyRecommendations(): HasMany
    {
        return $this->hasMany(StudyRecommendation::class);
    }

    public function latestTrendSummary(): HasOne
    {
        return $this->hasOne(TrendSummary::class)->latestOfMany('computed_at');
    }
}
