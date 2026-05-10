<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectedAccountListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    private function createAccount(array $overrides = []): ConnectedAccount
    {
        return ConnectedAccount::create(array_merge([
            'user_id'             => $this->user->id,
            'platform'            => Platform::Chesscom,
            'username'            => 'SampleUser',
            'normalised_username' => 'sampleuser',
        ], $overrides));
    }

    public function test_returns_paginated_payload_with_empty_data_when_no_accounts(): void
    {
        $this->getJson('/api/v1/connected-accounts')
            ->assertStatus(200)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'total'        => 0,
                ],
            ]);
    }

    public function test_returns_accounts_ordered_by_platform_then_username(): void
    {
        $this->createAccount([
            'platform'            => Platform::Lichess,
            'username'            => 'zeta',
            'normalised_username' => 'zeta',
        ]);
        $this->createAccount([
            'platform'            => Platform::Chesscom,
            'username'            => 'alpha',
            'normalised_username' => 'alpha',
        ]);

        $response = $this->getJson('/api/v1/connected-accounts')->assertStatus(200);

        $this->assertSame('chesscom', $response->json('data.0.platform'));
        $this->assertSame('alpha', $response->json('data.0.username'));
        $this->assertSame('lichess', $response->json('data.1.platform'));
        $this->assertSame('zeta', $response->json('data.1.username'));
    }

    public function test_does_not_return_other_users_accounts(): void
    {
        $otherUser = User::factory()->create();
        ConnectedAccount::create([
            'user_id'             => $otherUser->id,
            'platform'            => Platform::Chesscom,
            'username'            => 'OtherUser',
            'normalised_username' => 'otheruser',
        ]);

        $this->createAccount(['username' => 'MyAccount', 'normalised_username' => 'myaccount']);

        $response = $this->getJson('/api/v1/connected-accounts')->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('MyAccount', $response->json('data.0.username'));
    }

    public function test_sync_another_users_account_returns_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        ConnectedAccount::create([
            'user_id'             => $owner->id,
            'platform'            => Platform::Chesscom,
            'username'            => 'OwnerPlayer',
            'normalised_username' => 'ownerplayer',
        ]);

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/connected-accounts/by-username/chesscom/ownerplayer/sync')
            ->assertStatus(404);
    }
}
