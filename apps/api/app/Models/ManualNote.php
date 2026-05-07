<?php

namespace App\Models;

use App\Enums\CoachAgreement;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualNote extends Model
{
    use HasUuids, HasFactory;

    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'game_id',
        'key_moment_id',
        'note_text',
        'coach_agreement',
    ];

    protected function casts(): array
    {
        return [
            'created_at'      => 'datetime',
            'coach_agreement' => CoachAgreement::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function keyMoment(): BelongsTo
    {
        return $this->belongsTo(KeyMoment::class);
    }
}
