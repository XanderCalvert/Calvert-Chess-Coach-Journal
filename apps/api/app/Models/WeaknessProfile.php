<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeaknessProfile extends Model
{
    use HasUuids, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'connected_account_id',
        'computed_at',
        'profile_version',
        'window_size',
        'analysed_games_count',
        'computed_from_game_id',
        'computed_to_game_id',
        'weakest_phase',
        'top_motif',
        'top_mistake_tag_id',
        'threat_response_rate',
        'phase_breakdown',
        'opening_breakdown',
        'motif_frequencies',
        'threat_response_by_phase',
        'summary_json',
    ];

    protected function casts(): array
    {
        return [
            'computed_at'              => 'datetime',
            'threat_response_rate'     => 'decimal:2',
            'phase_breakdown'          => 'array',
            'opening_breakdown'        => 'array',
            'motif_frequencies'        => 'array',
            'threat_response_by_phase' => 'array',
            'summary_json'             => 'array',
        ];
    }

    public function connectedAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectedAccount::class);
    }

    public function topMistakeTag(): BelongsTo
    {
        return $this->belongsTo(MistakeTag::class, 'top_mistake_tag_id');
    }

    public function computedFromGame(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'computed_from_game_id');
    }

    public function computedToGame(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'computed_to_game_id');
    }
}
