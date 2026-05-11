<?php

namespace Tests\Feature;

use App\Models\ConnectedAccount;
use App\Models\WeaknessProfile;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeaknessProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    private function createAccount(string $username = 'TestPlayer'): ConnectedAccount
    {
        return ConnectedAccount::create([
            'user_id'             => DevUserSeeder::UUID,
            'platform'            => 'chesscom',
            'username'            => $username,
            'normalised_username' => strtolower($username),
            'sync_status'         => 'never_synced',
        ]);
    }

    private function createProfile(ConnectedAccount $account, array $overrides = []): WeaknessProfile
    {
        return WeaknessProfile::create(array_merge([
            'connected_account_id'     => $account->id,
            'computed_at'              => now(),
            'profile_version'          => '1.0',
            'window_size'              => 20,
            'analysed_games_count'     => 5,
            'weakest_phase'            => 'middlegame',
            'top_motif'                => 'hanging_piece',
            'threat_response_rate'     => 61.2,
            'phase_breakdown'          => ['middlegame' => ['error_rate' => 0.5]],
            'opening_breakdown'        => [],
            'motif_frequencies'        => [['motif' => 'hanging_piece', 'severity' => 'major']],
            'threat_response_by_phase' => ['middlegame' => 61.2],
            'summary_json'             => ['sufficient_data' => true, 'weakest_phase' => 'middlegame'],
        ], $overrides));
    }

    public function test_returns_404_for_unknown_platform_username(): void
    {
        $response = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/nobody/weakness-profile');

        $response->assertNotFound();
    }

    public function test_returns_404_when_account_exists_but_no_profile(): void
    {
        $this->createAccount('EmptyPlayer');

        $response = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/emptyplayer/weakness-profile');

        $response->assertNotFound();
    }

    public function test_returns_200_with_state_ready_when_sufficient_data(): void
    {
        $account = $this->createAccount();
        $this->createProfile($account, [
            'summary_json' => ['sufficient_data' => true],
        ]);

        $response = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/weakness-profile');

        $response->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonStructure(['state', 'profile']);
    }

    public function test_returns_200_with_state_insufficient_data(): void
    {
        $account = $this->createAccount();
        $this->createProfile($account, [
            'analysed_games_count' => 2,
            'weakest_phase'        => null,
            'top_motif'            => null,
            'threat_response_rate' => null,
            'phase_breakdown'      => [],
            'opening_breakdown'    => [],
            'motif_frequencies'    => [],
            'threat_response_by_phase' => [],
            'summary_json'         => ['sufficient_data' => false, 'analysed_games_count' => 2],
        ]);

        $response = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/weakness-profile');

        $response->assertOk()
            ->assertJsonPath('state', 'insufficient_data');
    }

    public function test_profile_response_includes_required_metadata_fields(): void
    {
        $account = $this->createAccount();
        $this->createProfile($account);

        $response = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/weakness-profile');

        $response->assertOk()
            ->assertJsonStructure([
                'state',
                'profile' => [
                    'computed_at',
                    'profile_version',
                    'window_size',
                    'analysed_games_count',
                    'weakest_phase',
                    'top_motif',
                    'threat_response_rate',
                    'phase_breakdown',
                    'opening_breakdown',
                    'motif_frequencies',
                    'threat_response_by_phase',
                    'summary_json',
                ],
            ]);
    }

    public function test_returns_latest_profile_when_multiple_exist(): void
    {
        $account = $this->createAccount();
        $this->createProfile($account, ['computed_at' => now()->subDay(), 'weakest_phase' => 'opening']);
        $this->createProfile($account, ['computed_at' => now(), 'weakest_phase' => 'endgame']);

        $response = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/weakness-profile');

        $response->assertOk()
            ->assertJsonPath('profile.weakest_phase', 'endgame');
    }
}
