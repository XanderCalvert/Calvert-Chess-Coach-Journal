<?php

namespace Tests\Feature;

use App\Enums\SyncStatus;
use App\Jobs\SyncChessComAccountJob;
use App\Models\ConnectedAccount;
use App\Models\User;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncFullEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ConnectedAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
        $this->user = User::factory()->create();
        $this->account = ConnectedAccount::create([
            'user_id'             => $this->user->id,
            'platform'            => 'chesscom',
            'username'            => 'TestPlayer',
            'normalised_username' => 'testplayer',
        ]);
    }

    private function asUser(): static
    {
        return $this->actingAs($this->user, 'sanctum');
    }

    public function test_sync_full_returns_202_and_dispatches_full_archive_job(): void
    {
        Queue::fake();

        $this->asUser()->postJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/sync-full')
            ->assertStatus(202)
            ->assertJsonFragment(['sync_status' => 'syncing']);

        Queue::assertPushed(SyncChessComAccountJob::class, function ($job) {
            return $job->connectedAccountId === $this->account->id
                && $job->fullArchive === true;
        });

        $this->account->refresh();
        $this->assertSame(SyncStatus::Syncing, $this->account->sync_status);
    }

    public function test_sync_full_returns_409_when_already_syncing(): void
    {
        Queue::fake();
        $this->account->update(['sync_status' => SyncStatus::Syncing->value]);

        $this->asUser()->postJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/sync-full')
            ->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_sync_full_requires_authentication(): void
    {
        Queue::fake();
        // Make request without actingAs (no auth token)
        $response = $this->postJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/sync-full');
        // Sanctum returns 401 for unauthenticated requests to auth:sanctum routes
        $response->assertStatus(401);
    }

    public function test_sync_full_returns_404_for_another_users_account(): void
    {
        Queue::fake();
        $other = User::factory()->create();
        ConnectedAccount::create([
            'user_id'             => $other->id,
            'platform'            => 'chesscom',
            'username'            => 'OtherPlayer',
            'normalised_username' => 'otherplayer',
        ]);

        $this->asUser()->postJson('/api/v1/connected-accounts/by-username/chesscom/otherplayer/sync-full')
            ->assertStatus(404);

        Queue::assertNothingPushed();
    }
}
