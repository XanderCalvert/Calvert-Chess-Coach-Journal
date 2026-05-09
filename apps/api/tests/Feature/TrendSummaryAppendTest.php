<?php

namespace Tests\Feature;

use App\Models\TrendSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendSummaryAppendTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_summaries_for_same_user_both_persist(): void
    {
        $user = User::factory()->create();

        TrendSummary::factory()->create(['user_id' => $user->id, 'computed_at' => now()->subDay()]);
        TrendSummary::factory()->create(['user_id' => $user->id, 'computed_at' => now()]);

        $this->assertCount(2, $user->trendSummaries()->get());
    }

    public function test_latest_of_many_returns_the_newer_summary(): void
    {
        $user = User::factory()->create();

        TrendSummary::factory()->create(['user_id' => $user->id, 'computed_at' => now()->subDay()]);
        $newer = TrendSummary::factory()->create(['user_id' => $user->id, 'computed_at' => now()]);

        $this->assertTrue($user->fresh()->latestTrendSummary->is($newer));
    }

    public function test_trend_summaries_are_append_only_no_updated_at(): void
    {
        $summary = TrendSummary::factory()->create();

        $this->assertFalse($summary->usesTimestamps());
        $this->assertArrayNotHasKey('updated_at', $summary->getAttributes());
    }
}
