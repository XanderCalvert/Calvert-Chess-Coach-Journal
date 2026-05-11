<?php

namespace Tests\Feature;

use App\Models\ConnectedAccount;
use App\Models\MistakeTag;
use App\Models\WeaknessProfile;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeaknessProfileModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    private function createAccount(): ConnectedAccount
    {
        return ConnectedAccount::create([
            'user_id'             => DevUserSeeder::UUID,
            'platform'            => 'chesscom',
            'username'            => 'TestPlayer',
            'normalised_username' => 'testplayer',
            'sync_status'         => 'never_synced',
        ]);
    }

    private function createProfile(ConnectedAccount $account, array $overrides = []): WeaknessProfile
    {
        return WeaknessProfile::create(array_merge([
            'connected_account_id'  => $account->id,
            'computed_at'           => now(),
            'profile_version'       => '1.0',
            'window_size'           => 20,
            'analysed_games_count'  => 5,
            'weakest_phase'         => 'middlegame',
            'top_motif'             => 'hanging_piece',
            'threat_response_rate'  => 61.2,
            'phase_breakdown'       => [],
            'opening_breakdown'     => [],
            'motif_frequencies'     => [],
            'threat_response_by_phase' => [],
            'summary_json'          => ['sufficient_data' => true],
        ], $overrides));
    }

    public function test_model_has_no_timestamps(): void
    {
        $account = $this->createAccount();
        $profile = $this->createProfile($account);

        $this->assertFalse($profile->usesTimestamps());
        $this->assertArrayNotHasKey('updated_at', $profile->getAttributes());
    }

    public function test_json_columns_are_cast_to_arrays(): void
    {
        $account = $this->createAccount();
        $profile = $this->createProfile($account, [
            'phase_breakdown'  => ['opening' => ['move_count' => 10]],
            'opening_breakdown' => [['eco_code' => 'C20']],
            'motif_frequencies' => [['motif' => 'hanging_piece']],
            'threat_response_by_phase' => ['opening' => 71.4],
            'summary_json'     => ['sufficient_data' => true, 'weakest_phase' => 'middlegame'],
        ]);

        $fresh = WeaknessProfile::find($profile->id);

        $this->assertIsArray($fresh->phase_breakdown);
        $this->assertIsArray($fresh->opening_breakdown);
        $this->assertIsArray($fresh->motif_frequencies);
        $this->assertIsArray($fresh->threat_response_by_phase);
        $this->assertIsArray($fresh->summary_json);
        $this->assertSame('C20', $fresh->opening_breakdown[0]['eco_code']);
    }

    public function test_connected_account_relationship_resolves(): void
    {
        $account = $this->createAccount();
        $profile = $this->createProfile($account);

        $this->assertTrue($profile->connectedAccount->is($account));
    }

    public function test_top_mistake_tag_relationship_resolves(): void
    {
        $this->seed(\Database\Seeders\MistakeTagSeeder::class);
        $account = $this->createAccount();
        $tag     = MistakeTag::where('slug', 'hanging-piece')->firstOrFail();
        $profile = $this->createProfile($account, ['top_mistake_tag_id' => $tag->id]);

        $this->assertSame('hanging-piece', $profile->topMistakeTag->slug);
    }

    public function test_latest_weakness_profile_returns_most_recent(): void
    {
        $account = $this->createAccount();

        $older = $this->createProfile($account, ['computed_at' => now()->subDay()]);
        $newer = $this->createProfile($account, ['computed_at' => now()]);

        $this->assertTrue($account->fresh()->latestWeaknessProfile->is($newer));
    }

    public function test_two_profiles_for_same_account_both_persist(): void
    {
        $account = $this->createAccount();

        $this->createProfile($account, ['computed_at' => now()->subDay()]);
        $this->createProfile($account, ['computed_at' => now()]);

        $this->assertCount(2, $account->weaknessProfiles()->get());
    }

    public function test_computed_from_and_to_game_relationships_resolve(): void
    {
        $account = $this->createAccount();
        $game1   = \App\Models\Game::create([
            'user_id' => DevUserSeeder::UUID, 'connected_account_id' => $account->id,
            'pgn_raw' => '*', 'white_player' => 'A', 'black_player' => 'B',
            'result' => 'white', 'user_colour' => 'white', 'played_at' => now()->subDays(2),
            'eco_code' => 'C20', 'opening_name' => 'KP', 'move_count' => 1,
            'analysis_status' => 'analysed', 'imported_from' => 'chesscom',
            'share_code' => 'aabbcc11',
        ]);
        $game2 = \App\Models\Game::create(array_merge($game1->only([
            'user_id', 'connected_account_id', 'pgn_raw', 'white_player', 'black_player',
            'result', 'user_colour', 'eco_code', 'opening_name', 'move_count',
            'analysis_status', 'imported_from',
        ]), ['played_at' => now(), 'share_code' => 'aabbcc22']));

        $profile = $this->createProfile($account, [
            'computed_from_game_id' => $game1->id,
            'computed_to_game_id'   => $game2->id,
        ]);

        $this->assertTrue($profile->computedFromGame->is($game1));
        $this->assertTrue($profile->computedToGame->is($game2));
    }
}
