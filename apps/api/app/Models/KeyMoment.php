<?php

namespace App\Models;

use App\Enums\ExplanationStatus;
use App\Enums\GamePhase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeyMoment extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'game_id',
        'move_id',
        'mistake_tag_id',
        'rank',
        'cp_loss',
        'explanation_text',
        'explanation_status',
        'game_phase',
    ];

    protected function casts(): array
    {
        return [
            'explanation_status' => ExplanationStatus::class,
            'game_phase'         => GamePhase::class,
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function move(): BelongsTo
    {
        return $this->belongsTo(Move::class);
    }

    public function mistakeTag(): BelongsTo
    {
        return $this->belongsTo(MistakeTag::class);
    }

    public function manualNotes(): HasMany
    {
        return $this->hasMany(ManualNote::class);
    }
}
