<?php

namespace App\Models;

use App\Enums\MoveClassification;
use App\Enums\PlayerColour;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Move extends Model
{
    use HasUuids, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'move_number',
        'san',
        'uci',
        'fen_before',
        'fen_after',
        'colour',
        'cp_score',
        'cp_loss',
        'classification',
        'themes',
        'tactical_flags',
        'threat_awareness',
        'risk_note',
        'consecutive_miss_count',
        'coaching_version',
        'game_phase',
        'complexity_score',
        'ai_explanation',
        'ai_explanation_status',
        'ai_explanation_model',
    ];

    protected function casts(): array
    {
        return [
            'colour'           => PlayerColour::class,
            'classification'   => MoveClassification::class,
            'themes'           => 'array',
            'tactical_flags'   => 'array',
            'threat_awareness' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function engineAnalysis(): HasOne
    {
        return $this->hasOne(EngineAnalysis::class);
    }

    public function keyMoment(): HasOne
    {
        return $this->hasOne(KeyMoment::class);
    }
}
