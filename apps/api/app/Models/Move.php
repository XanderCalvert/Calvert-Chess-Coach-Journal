<?php

namespace App\Models;

use App\Enums\MoveClassification;
use App\Enums\PlayerColour;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Move extends Model
{
    use HasUuids;

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
    ];

    protected function casts(): array
    {
        return [
            'colour'         => PlayerColour::class,
            'classification' => MoveClassification::class,
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
