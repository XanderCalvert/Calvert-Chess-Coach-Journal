<?php

namespace App\Models;

use App\Enums\GamePhase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendSummary extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'computed_at',
        'games_analysed',
        'avg_accuracy',
        'blunders_per_game',
        'top_mistake_tag_id',
        'opening_weakness',
        'phase_weakness',
        'summary_json',
    ];

    protected function casts(): array
    {
        return [
            'computed_at'       => 'datetime',
            'avg_accuracy'      => 'decimal:2',
            'blunders_per_game' => 'decimal:2',
            'phase_weakness'    => GamePhase::class,
            'summary_json'      => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topMistakeTag(): BelongsTo
    {
        return $this->belongsTo(MistakeTag::class, 'top_mistake_tag_id');
    }
}
