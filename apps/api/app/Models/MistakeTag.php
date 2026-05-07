<?php

namespace App\Models;

use App\Enums\PhaseHint;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MistakeTag extends Model
{
    use HasUuids, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'slug',
        'label',
        'description',
        'phase_hint',
    ];

    protected function casts(): array
    {
        return [
            'phase_hint' => PhaseHint::class,
        ];
    }

    public function keyMoments(): HasMany
    {
        return $this->hasMany(KeyMoment::class);
    }

    public function trendSummaries(): HasMany
    {
        return $this->hasMany(TrendSummary::class, 'top_mistake_tag_id');
    }

    public function studyRecommendations(): HasMany
    {
        return $this->hasMany(StudyRecommendation::class);
    }
}
