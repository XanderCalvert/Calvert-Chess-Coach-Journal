<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveTacticalEvent extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'move_id',
        'motif',
        'severity',
        'confidence',
        'attacker_square',
        'target_square',
        'defender_count',
        'attacker_count',
        'evidence_json',
        'detector_version',
    ];

    protected function casts(): array
    {
        return [
            'evidence_json' => 'array',
        ];
    }

    public function move(): BelongsTo
    {
        return $this->belongsTo(Move::class);
    }
}
