<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectedAccount extends Model
{
    use HasUuids;

    protected $attributes = [
        'sync_status' => 'never_synced',
    ];

    protected $fillable = [
        'user_id',
        'platform',
        'username',
        'normalised_username',
        'external_id',
        'profile_url',
        'rapid_rating',
        'blitz_rating',
        'bullet_rating',
        'daily_rating',
        'last_synced_at',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'platform'       => Platform::class,
            'sync_status'    => SyncStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
