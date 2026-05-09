<?php

namespace App\Models;

use App\Enums\StudyStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyRecommendation extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'user_id',
        'mistake_tag_id',
        'reason_text',
        'description_text',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => StudyStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mistakeTag(): BelongsTo
    {
        return $this->belongsTo(MistakeTag::class);
    }
}
