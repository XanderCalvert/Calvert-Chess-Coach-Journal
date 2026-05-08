<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\ConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectedAccountListTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(array $overrides = []): ConnectedAccount
    {
        return ConnectedAccount::create(array_merge([
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
}
