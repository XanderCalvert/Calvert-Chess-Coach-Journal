<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\GameResult;
use App\Enums\ImportSource;
use App\Enums\Platform;
use App\Enums\PlayerColour;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'user_id',
        'pgn_raw',
        'white_player',
        'black_player',
        'played_at',
        'result',
        'user_colour',
        'opening_name',
        'eco_code',
        'move_count',
        'accuracy_pct',
        'blunder_count',
        'mistake_count',
        'inaccuracy_count',
        'summary_text',
        'analysis_status',
        'imported_from',
        'external_id',
        'share_code',
        'connected_account_id',
        'platform',
        'time_control',
        'rated',
        'user_rating_before',
        'user_rating_after',
        'opponent_username',
        'opponent_rating',
    ];

    protected function casts(): array
    {
        return [
            'played_at'       => 'datetime',
            'result'          => GameResult::class,
            'user_colour'     => PlayerColour::class,
            'accuracy_pct'    => 'decimal:2',
            'analysis_status' => AnalysisStatus::class,
            'imported_from'   => ImportSource::class,
            'platform'        => Platform::class,
            'rated'           => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connectedAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectedAccount::class);
    }

    public function moves(): HasMany
    {
        return $this->hasMany(Move::class);
    }

    public function keyMoments(): HasMany
    {
        return $this->hasMany(KeyMoment::class);
    }

    public function manualNotes(): HasMany
    {
        return $this->hasMany(ManualNote::class);
    }
}
