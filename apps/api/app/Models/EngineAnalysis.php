<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineAnalysis extends Model
{
    use HasUuids, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'move_id',
        'engine_name',
        'best_move_uci',
        'best_move_san',
        'best_line',
        'depth',
        'depth_requested',
        'depth_reached',
        'cp_evaluation',
        'analysed_at',
    ];

    protected function casts(): array
    {
        return [
            'best_line'   => 'array',
            'analysed_at' => 'datetime',
        ];
    }

    public function move(): BelongsTo
    {
        return $this->belongsTo(Move::class);
    }
}
