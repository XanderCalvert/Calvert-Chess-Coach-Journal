<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveThreatEvent extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'game_id',
        'threat_source_move_id',
        'response_move_id',
        'resolved_by_move_id',
        'threat_type',
        'threat_level',
        'response_status',
        'confidence',
        'evidence_json',
        'detector_version',
    ];

    protected function casts(): array
    {
        return [
            'evidence_json' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function threatSourceMove(): BelongsTo
    {
        return $this->belongsTo(Move::class, 'threat_source_move_id');
    }

    public function responseMove(): BelongsTo
    {
        return $this->belongsTo(Move::class, 'response_move_id');
    }

    public function resolvedByMove(): BelongsTo
    {
        return $this->belongsTo(Move::class, 'resolved_by_move_id');
    }
}
