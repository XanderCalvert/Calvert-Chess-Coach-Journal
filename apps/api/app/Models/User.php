<?php

namespace App\Models;

use App\Enums\ExplanationDepth;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\ConnectedAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Carbon;

#[Fillable(['email', 'password', 'display_name', 'rating_estimate', 'explanation_depth', 'subscription_tier', 'analysis_quota_used', 'quota_period_start'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasUuids, HasFactory, Notifiable, HasApiTokens;

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'explanation_depth'    => ExplanationDepth::class,
            'analysis_quota_used'  => 'integer',
            'quota_period_start'   => 'date',
        ];
    }

    public function isPremium(): bool
    {
        return $this->subscription_tier === 'premium';
    }

    /** Returns the monthly analysis limit, or null for unlimited (premium). */
    public function quotaLimit(): ?int
    {
        return $this->isPremium() ? null : config('chess.free_monthly_analysis_quota');
    }

    /**
     * Returns remaining analyses this month, or null for unlimited (premium).
     * Resets the counter in-memory if the quota period has rolled over; caller
     * must save() the model (or use consumeQuota() inside a transaction).
     */
    public function resetPeriodIfRolled(): void
    {
        $currentPeriod = Carbon::now()->startOfMonth()->toDateString();
        if ($this->quota_period_start === null || $this->quota_period_start->toDateString() < $currentPeriod) {
            $this->analysis_quota_used = 0;
            $this->quota_period_start  = $currentPeriod;
        }
    }

    public function quotaRemaining(): ?int
    {
        if ($this->isPremium()) {
            return null;
        }
        $this->resetPeriodIfRolled();
        return max(0, $this->quotaLimit() - $this->analysis_quota_used);
    }

    public function connectedAccounts(): HasMany
    {
        return $this->hasMany(ConnectedAccount::class);
    }

    protected function hasConnectedAccounts(): Attribute
    {
        return Attribute::get(fn () => $this->connectedAccounts()->exists());
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function trendSummaries(): HasMany
    {
        return $this->hasMany(TrendSummary::class);
    }

    public function manualNotes(): HasMany
    {
        return $this->hasMany(ManualNote::class);
    }

    public function studyRecommendations(): HasMany
    {
        return $this->hasMany(StudyRecommendation::class);
    }

    public function latestTrendSummary(): HasOne
    {
        return $this->hasOne(TrendSummary::class)->latestOfMany('computed_at');
    }
}
